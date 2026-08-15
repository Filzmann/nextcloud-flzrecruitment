<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('technical app identity is consistently adrecruitment', static function (): void {
    $root = dirname(__DIR__);
    $info = file_get_contents($root . '/appinfo/info.xml');
    $application = file_get_contents($root . '/lib/AppInfo/Application.php');
    $listener = @file_get_contents($root . '/lib/Listener/StandaloneNavigationListener.php');
    $template = file_get_contents($root . '/templates/index.php');
    $api = file_get_contents($root . '/js/modules/api.js');
    $access = file_get_contents($root . '/lib/Service/RecruitmentAccessService.php');

    foreach ([
        'appinfo/info.xml' => $info,
        'lib/AppInfo/Application.php' => $application,
        'lib/Listener/StandaloneNavigationListener.php' => $listener,
        'templates/index.php' => $template,
        'js/modules/api.js' => $api,
        'lib/Service/RecruitmentAccessService.php' => $access,
    ] as $path => $source) {
        assertTrue($source !== false, "Identity contract source is missing: {$path}");
    }

    assertTrue(str_contains($info, '<id>adrecruitment</id>'), 'Nextcloud app ID is not adrecruitment');
    assertTrue(str_contains($info, '<name>AD Recruitment</name>'), 'Display name is not AD Recruitment');
    assertTrue(!str_contains($info, '<navigations>'), 'Static navigation would bypass the standalone/suite contract');
    assertTrue(str_contains($application, "public const APP_ID = 'adrecruitment';"), 'Bootstrap app ID is not adrecruitment');
    assertTrue(str_contains($application, 'LoadAdditionalEntriesEvent::class'), 'Standalone navigation listener is not registered');
    assertTrue(str_contains($listener, "addCatalogProductWhenStandalone('adrecruitment'"), 'Standalone navigation does not consume the product catalog');
    assertTrue(str_contains($template, "Util::addStyle('adrecruitment', 'style');"), 'Template assets use the old app ID');
    assertTrue(str_contains($api, "generateUrl('/apps/adrecruitment' + path)"), 'API client uses the old app route');
    assertTrue(str_contains($access, 'AdOrganizationSnapshotService'), 'Access does not consume the shared LocalBase organization contract');
    assertTrue(!str_contains($access, "'adrecruitment-admin'"), 'Access still maintains a parallel app-specific role hierarchy');
});
