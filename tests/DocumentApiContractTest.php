<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('document HTTP surface gates inline PDFs and field links by attachment application scope', static function (): void {
    $root = dirname(__DIR__);
    $routes = file_get_contents($root . '/appinfo/routes.php');
    $controller = file_get_contents($root . '/lib/Controller/ApiController.php');
    assertTrue($routes !== false && $controller !== false);
    foreach (['api#attachmentDocument', 'api#attachmentComments', 'api#createAttachmentComment', 'api#attachmentFieldContext', 'api#createAttachmentFieldLink'] as $route) {
        assertTrue(str_contains($routes, $route), "Missing document-review route {$route}");
    }
    assertTrue(str_contains($controller, 'DataDisplayResponse'), 'PDFs are not delivered as inline data');
    assertTrue(str_contains($controller, "'Content-Type' => 'application/pdf'"), 'Inline documents do not force the PDF content type');
    assertTrue(str_contains($controller, 'RecruitmentAccessService::VIEW'), 'Document reads have no dossier gate');
    assertTrue(str_contains($controller, 'RecruitmentAccessService::MANAGE_DOCUMENTS'), 'Document comments have no write capability gate');
    assertTrue(str_contains($controller, 'requiredCapability($targetField)'), 'Field links do not require the target field capability');
    assertTrue(str_contains($controller, 'createAttachmentFieldLink'), 'PDF selections have no controlled write endpoint');
    assertTrue(str_contains($controller, 'requireManageUnassignedInbox()'), 'Unassigned document reads have no inbox gate');
});
