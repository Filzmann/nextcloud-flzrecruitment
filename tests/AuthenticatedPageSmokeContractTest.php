<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('authenticated DDEV smoke targets the explicitly selected isolated instance', static function (): void {
    $source = file_get_contents(__DIR__ . '/integration/AuthenticatedPageSmoke.php');

    assertTrue($source !== false, 'AuthenticatedPageSmoke.php must be readable');
    assertTrue(str_contains($source, "getenv('RECR_BASE_URL')"), 'isolated base URL override is required');
    assertTrue(str_contains($source, '$baseUrl . \'/index.php/apps/flzrecruitment/\''), 'page request must use the selected base URL');
    assertTrue(str_contains($source, '$baseUrl . \'/index.php/apps/flzrecruitment/api/bootstrap\''), 'API request must use the selected base URL');
    assertTrue(str_contains($source, '$password = $uid;'), 'local test accounts must use their username as password');
    assertTrue(!str_contains($source, '$password = bin2hex('), 'local test accounts must not use an undisclosed random password');
});
