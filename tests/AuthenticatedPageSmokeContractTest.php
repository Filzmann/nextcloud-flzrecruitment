<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('authenticated DDEV smoke targets the explicitly selected isolated instance', static function (): void {
    $source = file_get_contents(__DIR__ . '/integration/AuthenticatedPageSmoke.php');

    assertTrue($source !== false, 'AuthenticatedPageSmoke.php must be readable');
    assertTrue(str_contains($source, "getenv('RECR_BASE_URL')"), 'isolated base URL override is required');
    assertTrue(str_contains($source, '$baseUrl . \'/index.php/apps/adrecruitment/\''), 'page request must use the selected base URL');
    assertTrue(str_contains($source, '$baseUrl . \'/index.php/apps/adrecruitment/api/bootstrap\''), 'API request must use the selected base URL');
});
