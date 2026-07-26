<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('read-only app page is explicitly CSRF-free while retaining server-side access control', static function (): void {
    $source = file_get_contents(dirname(__DIR__) . '/lib/Controller/PageController.php');
    if ($source === false) {
        throw new RuntimeException('PageController source is missing.');
    }

    $indexPosition = strpos($source, 'public function index(): TemplateResponse');
    assertTrue($indexPosition !== false, 'PageController::index fehlt.');
    $attributeBlock = substr($source, max(0, $indexPosition - 240), 240);

    assertTrue(str_contains($source, 'use OCP\\AppFramework\\Http\\Attribute\\NoCSRFRequired;'));
    assertTrue(str_contains($attributeBlock, '#[NoCSRFRequired]'));
    assertTrue(str_contains($attributeBlock, '#[NoAdminRequired]'));
    assertTrue(str_contains($source, '$this->access->require(RecruitmentAccessService::VIEW);'));
});
