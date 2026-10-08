<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;

TestRunner::test('PHP tests use the central app-local bootstrap without distributed includes', static function (): void {
    $violations = [];
    foreach (new DirectoryIterator(__DIR__) as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), 'Test.php')) {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if (is_string($contents) && preg_match('/^\s*require(?:_once)?\b/m', $contents) === 1) {
            $violations[] = $file->getFilename();
        }
    }

    sort($violations);
    assertSame([], $violations, 'Distributed test bootstrap includes remain: ' . implode(', ', $violations));
});
