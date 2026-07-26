<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/lib/base.php';

use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

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
$uid = 'recruitment-page-smoke-' . bin2hex(random_bytes(5));
$password = bin2hex(random_bytes(24));
$user = $users->createUser($uid, $password);
if ($user === null) {
    throw new RuntimeException('Das synthetische Browser-Smoke-Konto konnte nicht angelegt werden.');
}

$groupId = 'recruitment-editors';
$group = $groups->get($groupId);
$createdGroup = $group === null;
$group ??= $groups->createGroup($groupId);
if ($group === null) {
    $user->delete();
    throw new RuntimeException('Die temporäre Recruitment-Rollengruppe konnte nicht bereitgestellt werden.');
}
$group->addUser($user);
$personId = null;

try {
    $curl = curl_init('https://nextcloud-dev.ddev.site/index.php/login');
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
    ]);
    $loginBody = curl_exec($curl);
    $loginStatus = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $loginError = curl_error($curl);
    $assert($loginBody !== false, 'Die Nextcloud-Anmeldeseite ist nicht erreichbar: ' . $loginError);
    $assert($loginStatus === 200, "Die Nextcloud-Anmeldeseite antwortet mit HTTP {$loginStatus}.");
    $assert(
        preg_match('/<head[^>]*data-requesttoken="([^"]+)"/i', $loginBody, $loginTokenMatch) === 1,
        'Der CSRF-Token fehlt auf der Nextcloud-Anmeldeseite.',
    );
    $loginToken = html_entity_decode($loginTokenMatch[1], ENT_QUOTES | ENT_HTML5);

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://nextcloud-dev.ddev.site/index.php/login',
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'user' => $uid,
            'password' => $password,
            'requesttoken' => $loginToken,
            'timezone' => 'Europe/Berlin',
            'timezone_offset' => '-2',
        ]),
    ]);
    $authenticatedBody = curl_exec($curl);
    $authenticatedStatus = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $authenticatedError = curl_error($curl);
    $assert($authenticatedBody !== false, 'Die synthetische Nextcloud-Anmeldung ist fehlgeschlagen: ' . $authenticatedError);
    $assert($authenticatedStatus === 200, "Die synthetische Nextcloud-Anmeldung antwortet mit HTTP {$authenticatedStatus}.");

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://nextcloud-dev.ddev.site/index.php/apps/recruitment/',
        CURLOPT_POST => false,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => [],
        CURLOPT_POSTFIELDS => null,
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $contentType = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    $error = curl_error($curl);

    $assert($body !== false, 'Der authentifizierte HTTPS-Aufruf ist fehlgeschlagen: ' . $error);
    $assert($status === 200, "Die authentifizierte App-URL antwortet mit HTTP {$status}.");
    $assert(str_starts_with($contentType, 'text/html'), 'Die App-URL liefert kein HTML.');
    $assert(str_contains($body, 'id="recruitment-app"'), 'Der sichtbare Recruitment-App-Root fehlt.');
    $assert(str_contains($body, '/custom_apps/recruitment/css/style.css'), 'Das Recruitment-CSS ist nicht eingebunden.');
    $assert(str_contains($body, '/custom_apps/recruitment/js/main.js'), 'Das Recruitment-JavaScript ist nicht eingebunden.');

    $assert(
        preg_match('/<head[^>]*data-requesttoken="([^"]+)"/i', $body, $tokenMatch) === 1,
        'Der aktuelle Nextcloud-CSRF-Token fehlt im authentifizierten HTML.',
    );
    $requestToken = html_entity_decode($tokenMatch[1], ENT_QUOTES | ENT_HTML5);
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://nextcloud-dev.ddev.site/index.php/apps/recruitment/api/people',
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

    fwrite(STDOUT, "Recruitment authenticated page HTTPS smoke: OK\n");
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
}
