<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('app shell exposes accessible tabs, status and error regions', static function (): void {
    $template = file_get_contents(dirname(__DIR__) . '/templates/index.php');
    $css = file_get_contents(dirname(__DIR__) . '/css/style.css');
    if ($template === false || $css === false) {
        throw new RuntimeException('UI source is missing');
    }

    assertTrue(str_contains($template, 'role="status"'));
    assertTrue(str_contains($template, 'role="alert"'));
    assertTrue(str_contains($template, 'aria-label="Recruitment-Bereiche"'));
    assertTrue(str_contains($css, 'overflow-y: auto'));
    assertTrue(str_contains($css, ':focus-visible'));
});
