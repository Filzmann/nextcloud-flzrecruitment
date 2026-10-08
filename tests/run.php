<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use OCA\LocalBase\Tests\Support\PhpTestRunner;

PhpTestRunner::run(
    root: dirname(__DIR__),
    lintDirectories: ['appinfo', 'lib', 'templates', 'tests'],
    testDirectories: ['tests'],
    testSuffixes: ['Test.php'],
    successMessage: 'Filzmann Recruitment PHP tests passed',
    prependBootstrap: true,
);
