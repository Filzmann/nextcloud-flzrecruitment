<?php

declare(strict_types=1);

if (!defined('OC_CONSOLE')) define('OC_CONSOLE', true);
require dirname(__DIR__, 4) . '/lib/base.php';

use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IAppConfig;
use OCA\LocalBase\Organization\AdOrganizationDefinition;
use OCA\LocalBase\Organization\AdOrganizationSettingsService;

/**
 * Authentifizierter HTTPS-Smoke mit synthetischem, garantiert bereinigtem Konto.
 */

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$users = \OCP\Server::get(IUserManager::class);
$groups = \OCP\Server::get(IGroupManager::class);
$db = \OCP\Server::get(IDBConnection::class);
$appConfig = \OCP\Server::get(IAppConfig::class);
$organization = \OCP\Server::get(AdOrganizationSettingsService::class);
$previousOrganization = $appConfig->getValueString('localbase', 'ad_organization_definition', '');
$temporaryOrganization = !$organization->state()['valid'];
if ($temporaryOrganization) {
    $organization->save(AdOrganizationDefinition::defaults()->toArray());
}
$restoreOrganization = static function () use ($temporaryOrganization, $previousOrganization, $appConfig): void {
    if (!$temporaryOrganization) return;
    if ($previousOrganization === '') {
        $appConfig->deleteKey('localbase', 'ad_organization_definition');
    } else {
        $appConfig->setValueString('localbase', 'ad_organization_definition', $previousOrganization);
    }
};
$uid = 'adrecruitment-page-smoke-' . bin2hex(random_bytes(5));
$password = bin2hex(random_bytes(24));
$user = $users->createUser($uid, $password);
if ($user === null) {
    $restoreOrganization();
    throw new RuntimeException('Das synthetische Browser-Smoke-Konto konnte nicht angelegt werden.');
}

$groupId = (string)$organization->definition()->roleGroupId('staff_hr');
$group = $groups->get($groupId);
$createdGroup = $group === null;
$group ??= $groups->createGroup($groupId);
if ($group === null) {
    $user->delete();
    $restoreOrganization();
    throw new RuntimeException('Die temporäre AD-Recruitment-Rollengruppe konnte nicht bereitgestellt werden.');
}
$group->addUser($user);
$personId = null;

try {
    $curl = curl_init('https://nextcloud-dev.ddev.site/index.php/apps/adrecruitment/');
    if ($curl === false) {
        throw new RuntimeException('Der HTTPS-Smoke konnte nicht initialisiert werden.');
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEFILE => '',
        CURLOPT_USERPWD => $uid . ':' . $password,
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $contentType = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    $error = curl_error($curl);

    $assert($body !== false, 'Der authentifizierte HTTPS-Aufruf ist fehlgeschlagen: ' . $error);
    $assert($status === 200, "Die authentifizierte App-URL antwortet mit HTTP {$status}.");
    $assert(str_starts_with($contentType, 'text/html'), 'Die App-URL liefert kein HTML.');
    $assert(str_contains($body, 'id="adrecruitment-app"'), 'Der sichtbare AD-Recruitment-App-Root fehlt.');
    $assert(str_contains($body, '/custom_apps/adrecruitment/css/style.css'), 'Das AD-Recruitment-CSS ist nicht eingebunden.');
    $assert(str_contains($body, '/custom_apps/adrecruitment/js/main.js'), 'Das AD-Recruitment-JavaScript ist nicht eingebunden.');
    $assert(str_contains($body, '/custom_apps/adrecruitment/js/modules/dialog-overlay.js'), 'Das Dialog-Overlay-Modul ist nicht eingebunden.');
    $assert(str_contains($body, '/custom_apps/adrecruitment/js/modules/application-workbench.js'), 'Das Bewerbungs-Workbench-Modul ist nicht eingebunden.');

    $assert(
        preg_match('/<head[^>]*data-requesttoken="([^"]+)"/i', $body, $tokenMatch) === 1,
        'Der aktuelle Nextcloud-CSRF-Token fehlt im authentifizierten HTML.',
    );
    $requestToken = html_entity_decode($tokenMatch[1], ENT_QUOTES | ENT_HTML5);
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://nextcloud-dev.ddev.site/index.php/apps/adrecruitment/api/bootstrap',
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $bootstrapBody = curl_exec($curl);
    $bootstrapStatus = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $bootstrapError = curl_error($curl);
    $assert($bootstrapBody !== false, 'Der authentifizierte Bootstrap-Aufruf ist fehlgeschlagen: ' . $bootstrapError);
    $assert($bootstrapStatus === 200, "Der authentifizierte Bootstrap-Aufruf antwortet mit HTTP {$bootstrapStatus}: {$bootstrapBody}");
    $bootstrapPayload = json_decode($bootstrapBody, true, 512, JSON_THROW_ON_ERROR);
    $assert(isset($bootstrapPayload['data']['jobs'], $bootstrapPayload['data']['people'], $bootstrapPayload['data']['applications']), 'Der Bootstrap liefert keinen vollständigen Bewerbungsarbeitsplatz.');
    $assert(
        isset($bootstrapPayload['data']['applicationStatuses'])
            && in_array('received', $bootstrapPayload['data']['applicationStatuses'], true)
            && in_array('approved_for_hire', $bootstrapPayload['data']['applicationStatuses'], true),
        'Der Bootstrap liefert keine geordnete Statuskonfiguration für Board und Tastaturalternative.',
    );

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://nextcloud-dev.ddev.site/index.php/apps/adrecruitment/api/people',
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Requested-With: XMLHttpRequest',
            'requesttoken: ' . $requestToken,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'givenName' => 'Alex',
            'familyName' => 'CSRF-Smoke',
            'email' => '',
            'phone' => '',
        ], JSON_THROW_ON_ERROR),
    ]);
    $writeBody = curl_exec($curl);
    $writeStatus = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $writeError = curl_error($curl);
    curl_close($curl);

    $assert($writeBody !== false, 'Der CSRF-geschützte Schreibrequest ist fehlgeschlagen: ' . $writeError);
    $assert($writeStatus === 201, "Der CSRF-geschützte Schreibrequest antwortet mit HTTP {$writeStatus}: {$writeBody}");
    $writePayload = json_decode($writeBody, true, 512, JSON_THROW_ON_ERROR);
    $personId = isset($writePayload['id']) ? (int)$writePayload['id'] : null;
    $assert($personId !== null && $personId > 0, 'Der Schreibrequest hat keine Personen-ID geliefert.');

    fwrite(STDOUT, "AD Recruitment authenticated page HTTPS smoke: OK\n");
} finally {
    if ($personId !== null) {
        $qb = $db->getQueryBuilder();
        $qb
            ->delete('rec_people')
            ->where($qb->expr()->eq(
                'id',
                $qb->createNamedParameter($personId, IQueryBuilder::PARAM_INT),
            ))
            ->executeStatement();
    }
    $user->delete();
    if ($createdGroup) {
        $group->delete();
    }
    $restoreOrganization();
}
