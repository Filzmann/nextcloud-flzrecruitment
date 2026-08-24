<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertTrue;

TestRunner::test('pinned app-local PDF.js assets and license remain reproducible', static function (): void {
    $root = dirname(__DIR__) . '/js/vendor/pdfjs';
    $hashes = [
        'pdf.min.mjs' => 'e0be3863c23c8af2305b16548febd58e7f8874a460253317d7771cddbc1c0f6d',
        'pdf.worker.min.mjs' => '0613f41490dd6aaceed7a93fbbd38c85e6d6aa60474b6588c6e7709cfbe18cb3',
        'LICENSE' => '0d542e0c8804e39aa7f37eb00da5a762149dc682d7829451287e11b938e94594',
    ];
    foreach ($hashes as $file => $expectedHash) {
        assertTrue(is_file($root . '/' . $file), "Pinned PDF.js asset is missing: {$file}");
        assertSame($expectedHash, hash_file('sha256', $root . '/' . $file), "Pinned PDF.js asset changed: {$file}");
    }
    foreach (['cmaps', 'standard_fonts', 'wasm'] as $directory) {
        assertTrue(is_dir($root . '/' . $directory), "PDF.js support assets are missing: {$directory}");
    }
    $version = file_get_contents($root . '/VERSION');
    assertTrue($version !== false && str_contains($version, 'PDF.js 6.2.108'), 'Pinned PDF.js version is not documented');
});
