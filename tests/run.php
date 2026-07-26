<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$failures = [];

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/.git/')) {
        continue;
    }

    $command = sprintf('php -l %s 2>&1', escapeshellarg($file->getPathname()));
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        $failures[] = implode("\n", $output);
    }
    $output = [];
}

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $testFile) {
    require $testFile;
}

$testFailures = \RecruitmentTests\TestRunner::failures();
array_push($failures, ...$testFailures);

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "Recruitment PHP tests passed\n");
