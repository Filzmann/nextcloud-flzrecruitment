<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('release metadata supports standalone and full-suite packaging', static function (): void {
    $root = dirname(__DIR__);
    $info = file_get_contents($root . '/appinfo/info.xml');
    $readme = file_get_contents($root . '/README.md');

    assertTrue($info !== false && $readme !== false, 'Release contract sources are missing');
    foreach (['<website>https://github.com/Filzmann/flz-full-suite</website>',
        '<bugs>https://github.com/Filzmann/nextcloud-flzrecruitment/issues</bugs>',
        '<repository type="git">https://github.com/Filzmann/nextcloud-flzrecruitment</repository>'] as $metadata) {
        assertTrue(str_contains($info, $metadata), "Release metadata is missing: {$metadata}");
    }
    assertTrue(is_file($root . '/LICENSE'), 'AGPL license file is missing');
    assertTrue(is_file($root . '/CHANGELOG.md'), 'Changelog is missing');
    assertTrue(str_contains($readme, 'eigenständig installierbar'), 'Standalone capability is undocumented');
    assertTrue(str_contains($readme, 'Filzmann Nextcloud Plugins'), 'Suite membership is undocumented');
});

TestRunner::test('deliverable production sources contain no invalid placeholder domain', static function (): void {
    $root = dirname(__DIR__);
    $violations = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/lib'));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') continue;
        $source = file_get_contents($file->getPathname());
        if (is_string($source) && str_contains($source, 'example.invalid')) {
            $violations[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }
    sort($violations);
    assertTrue($violations === [], 'Invalid delivery placeholders remain: ' . implode(', ', $violations));
});
