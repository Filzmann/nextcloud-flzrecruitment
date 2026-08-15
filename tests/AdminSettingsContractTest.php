<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('system configuration lives in the native Nextcloud admin section', static function (): void {
    $root = dirname(__DIR__);
    $info = file_get_contents($root . '/appinfo/info.xml');
    $admin = file_get_contents($root . '/lib/Settings/Admin.php');
    $section = file_get_contents($root . '/lib/Settings/AdminSection.php');
    $template = file_get_contents($root . '/templates/admin.php');
    $main = file_get_contents($root . '/js/main.js');
    $navigation = file_get_contents($root . '/js/modules/settings-navigation.js');

    foreach ([$info, $admin, $section, $template, $main, $navigation] as $source) {
        assertTrue($source !== false, 'Eine Admin-Vertragsdatei fehlt.');
    }
    assertTrue(str_contains($info, '<admin>OCA\Recruitment\Settings\Admin</admin>'));
    assertTrue(str_contains($info, '<admin-section>OCA\Recruitment\Settings\AdminSection</admin-section>'));
    assertTrue(str_contains($admin, "new TemplateResponse(Application::APP_ID, 'admin'"));
    assertTrue(str_contains($section, 'implements IIconSection'));
    assertTrue(str_contains($section, 'return Application::APP_ID;'));

    foreach (['recr-admin-mail-form', 'recr-admin-extraction-form', 'recr-admin-first-guide-form'] as $id) {
        assertTrue(str_contains($template, 'id="' . $id . '"'), "Adminformular fehlt: {$id}");
    }
    assertTrue(!str_contains($template, 'id="recr-admin-pool-form"'), 'Bewerberpool-Grundkonfiguration steht noch im Nextcloud-Adminbereich.');
    assertTrue(str_contains($navigation, "id: 'candidate-pool'"), 'Bewerberpool-Grundkonfiguration fehlt im operativen Einstellungsmenü.');
    assertTrue(str_contains($main, "settingsPanel('candidate-pool', 'Datenschutz & Rückstellungen')"));
    assertTrue(str_contains($main, 'api.saveCandidatePoolSettings'));
    assertTrue(!str_contains($navigation, "id: 'data-extraction'"), 'Datenextraktion steht noch im operativen Einstellungsmenü.');
    assertTrue(!str_contains($main, "element('h3', { text: 'Mail-Testmodus' })"), 'Mail-Testmodus steht noch bei den PersRef-Mailvorlagen.');
    assertTrue(!str_contains($main, "field('Nextcloud-Gruppe für Erstbegleitungen'"), 'Strukturelle Gruppe steht noch bei der PersRef-Delegation.');
});
