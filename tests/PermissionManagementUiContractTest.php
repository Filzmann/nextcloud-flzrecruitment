<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('privacy officers manage role capability assignments through an accessible settings matrix', static function (): void {
    $root = dirname(__DIR__);
    $main = (string)file_get_contents($root . '/js/main.js');
    $api = (string)file_get_contents($root . '/js/modules/api.js');
    $admin = (string)file_get_contents($root . '/templates/admin.php');

    assertTrue(str_contains($main, "capabilities.manage_permission_matrix"), 'Die UI prüft die Verwaltungsberechtigung der Rollenmatrix nicht.');
    assertTrue(str_contains($main, "capability === 'manage_delegations' && role.key !== 'staff_hr'"), 'Die UI bietet die Verwaltung weiterer Vertretungen noch für andere Organigrammrollen an.');
    assertTrue(str_contains($main, 'settings.valid === false'), 'Eine ungültige gespeicherte Berechtigungskonfiguration bleibt in der Fachoberfläche unsichtbar.');
    assertTrue(str_contains($main, "api.saveRoleCapabilities"), 'Die Rollenmatrix besitzt keinen versionierten Speicherpfad.');
    assertTrue(str_contains($main, "type: 'checkbox'"), 'Die Rollenmatrix verwendet keine Auswahlfelder.');
    assertTrue(str_contains($main, 'Erstbegleitungsakte lesen'), 'Das getrennte Erstbegleitungsrecht ist nicht selbsterklärend beschriftet.');
    assertTrue(str_contains($api, '/api/permissions/roles'), 'Der API-Adapter enthält keinen Rollenmatrix-Endpunkt.');
    assertTrue(!str_contains($admin, 'recr-admin-first-guide-form'), 'Die fachliche Erstbegleitungsberechtigung steht noch in der technischen Nextcloud-Administration.');
});
