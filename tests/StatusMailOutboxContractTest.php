<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('stale claimed mail jobs are recovered after a bounded worker lease', static function (): void {
    $repository = file_get_contents(dirname(__DIR__) . '/lib/Repository/RecruitmentRepository.php');
    if ($repository === false) throw new RuntimeException('Repository source is missing.');
    assertTrue(str_contains($repository, "modify('-15 minutes')"), 'Claimed mail jobs have no bounded worker lease.');
    assertTrue(str_contains($repository, 'worker_interrupted'), 'Interrupted mail workers leave no diagnosable retry state.');
});
