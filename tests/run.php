<?php

declare(strict_types=1);

require_once __DIR__ . '/../../localbase/tests/Support/PhpTestRunner.php';

use OCA\LocalBase\Tests\Support\PhpTestRunner;

PhpTestRunner::run(
    root: dirname(__DIR__),
    lintDirectories: ['appinfo', 'lib', 'templates', 'tests'],
    testDirectories: ['tests'],
    testSuffixes: ['Test.php'],
    successMessage: 'AD Recruitment PHP tests passed',
);
