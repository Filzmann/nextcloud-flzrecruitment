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
$baseUrl = rtrim(getenv('RECR_BASE_URL') ?: 'https://nextcloud-dev.ddev.site', '/');
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
$password = $uid;
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
    $curl = curl_init($baseUrl . '/index.php/apps/adrecruitment/');
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

    curl_setopt_array($curl, [
        CURLOPT_URL => $baseUrl . '/index.php/settings/admin/adrecruitment',
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => ['Accept: text/html'],
    ]);
    $nonAdminSettingsBody = curl_exec($curl);
    $nonAdminSettingsStatus = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $assert(
        $nonAdminSettingsBody !== false
            && ($nonAdminSettingsStatus === 403 || !str_contains($nonAdminSettingsBody, 'id="adrecruitment-admin"')),
        'Das synthetische PersRef-Konto erreicht den Nextcloud-Adminabschnitt.',
    );

    $adminGroup = $groups->get('admin');
    $assert($adminGroup !== null, 'Die lokale Nextcloud-Admin-Gruppe fehlt.');
    $adminGroup->addUser($user);
    curl_setopt_array($curl, [
        CURLOPT_URL => $baseUrl . '/index.php/settings/admin/adrecruitment',
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => ['Accept: text/html'],
    ]);
    $adminSettingsBody = curl_exec($curl);
    $adminSettingsStatus = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $adminSettingsError = curl_error($curl);
    $assert($adminSettingsBody !== false, 'Der native Adminabschnitt konnte nicht geladen werden: ' . $adminSettingsError);
    $assert($adminSettingsStatus === 200, "Der native Adminabschnitt antwortet mit HTTP {$adminSettingsStatus}.");
    foreach (['recr-admin-mail-form', 'recr-admin-extraction-form', 'recr-admin-first-guide-form'] as $formId) {
        $assert(str_contains($adminSettingsBody, 'id="' . $formId . '"'), "Der native Adminabschnitt enthält {$formId} nicht.");
    }
    $assert(!str_contains($adminSettingsBody, 'id="recr-admin-pool-form"'), 'Der Bewerberpool wird weiterhin doppelt im Nextcloud-Adminbereich angeboten.');
    $assert(str_contains($adminSettingsBody, '/custom_apps/adrecruitment/js/admin.js'), 'Das Admin-JavaScript ist nicht eingebunden.');

    $assert(
        preg_match('/<head[^>]*data-requesttoken="([^"]+)"/i', $body, $tokenMatch) === 1,
        'Der aktuelle Nextcloud-CSRF-Token fehlt im authentifizierten HTML.',
    );
    $requestToken = html_entity_decode($tokenMatch[1], ENT_QUOTES | ENT_HTML5);
    curl_setopt_array($curl, [
        CURLOPT_URL => $baseUrl . '/index.php/apps/adrecruitment/api/bootstrap',
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
        in_array($groupId, array_column($bootstrapPayload['jobResponsibilityGroups'] ?? [], 'id'), true),
        'Der Bootstrap liefert die fachlich zulässige HR-Beteiligungsgruppe nicht.',
    );
    $assert(
        isset($bootstrapPayload['data']['applicationStatuses'])
            && in_array('received', $bootstrapPayload['data']['applicationStatuses'], true)
            && in_array('approved_for_hire', $bootstrapPayload['data']['applicationStatuses'], true),
        'Der Bootstrap liefert keine geordnete Statuskonfiguration für Board und Tastaturalternative.',
    );
    $mailConfiguration = $bootstrapPayload['mailConfiguration'] ?? [];
    $expectedMailRuleCount = array_sum(array_map(
        'count',
        (new \OCA\Recruitment\Service\ApplicationStatusService())->transitions(),
    ));
    $assert(
        count($mailConfiguration['rules'] ?? []) === $expectedMailRuleCount,
        "Der Bootstrap liefert nicht alle {$expectedMailRuleCount} vorbereiteten Statusmail-Regeln.",
    );
    $assert(
        count(array_filter($mailConfiguration['rules'], static fn(array $rule): bool => ($rule['actorUid'] ?? '') === 'system' && $rule['enabled'])) === 0,
        'Neu vorbereitete Statusmail-Regeln wurden ohne bewusste Freigabe aktiviert.',
    );
    $htmlTemplateIds = array_map(
        static fn(array $template): int => (int)$template['id'],
        array_filter($mailConfiguration['templates'] ?? [], static fn(array $template): bool => ($template['bodyFormat'] ?? '') === 'html'),
    );
    $assert(
        count(array_filter(
            $mailConfiguration['rules'] ?? [],
            static fn(array $rule): bool => !in_array((int)($rule['templateId'] ?? 0), $htmlTemplateIds, true),
        )) === 0,
        'Nicht alle vorbereiteten Statusmail-Regeln verweisen auf eine HTML-Vorlage.',
    );

    curl_setopt_array($curl, [
        CURLOPT_URL => $baseUrl . '/index.php/apps/adrecruitment/api/job-responsibility-users?' . http_build_query([
            'professionCategory' => 'assistance',
            'groupIds' => [$groupId],
            'query' => substr($uid, 0, 12),
        ]),
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $searchBody = curl_exec($curl);
    $searchStatus = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $searchError = curl_error($curl);
    $assert($searchBody !== false, 'Die gruppengebundene Benutzersuche ist fehlgeschlagen: ' . $searchError);
    $assert($searchStatus === 200, "Die gruppengebundene Benutzersuche antwortet mit HTTP {$searchStatus}: {$searchBody}");
    $searchPayload = json_decode($searchBody, true, 512, JSON_THROW_ON_ERROR);
    $assert(
        in_array($uid, array_column($searchPayload['users'] ?? [], 'uid'), true),
        'Die gruppengebundene Benutzersuche findet das Mitglied der ausgewählten Gruppe nicht.',
    );

    curl_setopt_array($curl, [
        CURLOPT_URL => $baseUrl . '/index.php/apps/adrecruitment/api/people',
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
