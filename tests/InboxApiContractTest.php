<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('inbox HTTP surface separates global inbox access from scoped dossier reads', static function (): void {
    $root = dirname(__DIR__);
    $routes = file_get_contents($root . '/appinfo/routes.php');
    $controller = file_get_contents($root . '/lib/Controller/ApiController.php');
    assertTrue($routes !== false && $controller !== false);
    foreach (['api#inbox', 'api#inboxMessage', 'api#applicationMessages', 'api#assignInboxMessage', 'api#ignoreInboxMessage'] as $route) {
        assertTrue(str_contains($routes, $route), "Missing inbox route {$route}");
    }
    assertTrue(str_contains($controller, 'requireManageUnassignedInbox()'), 'Unassigned inbox actions have no global server-side gate');
    assertTrue(str_contains($controller, 'RecruitmentAccessService::VIEW'), 'Assigned mail has no application dossier gate');
    assertTrue(str_contains($controller, '#[NoCSRFRequired]'), 'Read endpoints are not declared explicitly');
});
