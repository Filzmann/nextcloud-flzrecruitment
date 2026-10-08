<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertTrue;

TestRunner::test('pinned app-local PDF.js assets and license remain reproducible', static function (): void {
    $appRoot = dirname(__DIR__);
    $bundleRoot = $appRoot . '/js/vendor/pdfjs';
    $inventoryPath = $appRoot . '/resources/third-party-components.cdx.json';

    assertTrue(is_file($inventoryPath), 'Canonical third-party component inventory is missing');
    $inventory = json_decode((string)file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);
    assertSame('CycloneDX', $inventory['bomFormat'] ?? null, 'Third-party inventory must use CycloneDX');
    assertSame('1.6', $inventory['specVersion'] ?? null, 'Third-party inventory must use CycloneDX 1.6');
    assertSame('flzrecruitment', $inventory['metadata']['properties'][0]['value'] ?? null, 'Third-party inventory owner changed');
    assertSame(1, count($inventory['components'] ?? []), 'Third-party inventory must contain exactly PDF.js');

    $component = $inventory['components'][0] ?? [];
    assertSame('library', $component['type'] ?? null, 'PDF.js must be classified as a library');
    assertSame('pdfjs-dist', $component['name'] ?? null, 'PDF.js package name changed');
    assertSame('6.2.108', $component['version'] ?? null, 'PDF.js version changed');
    assertSame('required', $component['scope'] ?? null, 'PDF.js runtime scope changed');
    assertSame('pkg:npm/pdfjs-dist@6.2.108', $component['purl'] ?? null, 'PDF.js PURL changed');
    assertSame('Apache-2.0', $component['licenses'][0]['license']['id'] ?? null, 'PDF.js license changed');
    assertSame('SHA-512', $component['hashes'][0]['alg'] ?? null, 'PDF.js package hash algorithm changed');
    assertSame(
        '63115bf9241ca1d376ae75fd4e7ddd1d896a7dbecd8e5cf37ce34fa4977e00aa0ab548c475ebd37db0b4edde5371ccf338aeb6eb56ae4643ff33c3811ecf6f15',
        $component['hashes'][0]['content'] ?? null,
        'PDF.js package archive hash changed',
    );

    $externalReferences = [];
    foreach ($component['externalReferences'] ?? [] as $externalReference) {
        $externalReferences[$externalReference['type'] ?? ''] = $externalReference['url'] ?? null;
    }
    assertSame(
        'https://registry.npmjs.org/pdfjs-dist/-/pdfjs-dist-6.2.108.tgz',
        $externalReferences['distribution'] ?? null,
        'PDF.js distribution source changed',
    );
    assertSame(
        'https://github.com/mozilla/pdf.js/tree/v6.2.108',
        $externalReferences['vcs'] ?? null,
        'PDF.js source tag changed',
    );

    $properties = [];
    foreach ($component['properties'] ?? [] as $property) {
        $properties[$property['name'] ?? ''] = $property['value'] ?? null;
    }
    assertSame('js/vendor/pdfjs', $properties['filzmann:bundled-root'] ?? null, 'PDF.js bundle root changed');
    assertSame('app-local-browser', $properties['filzmann:runtime-scope'] ?? null, 'PDF.js runtime use changed');
    assertSame('osv-by-purl', $properties['filzmann:dependency-scan'] ?? null, 'PDF.js dependency scanner contract changed');
    assertSame('pinned-third-party-source', $properties['filzmann:sast-treatment'] ?? null, 'PDF.js SAST scope changed');
    $npmIntegrity = $properties['filzmann:npm-integrity'] ?? null;
    assertSame(
        'sha512-YxFb+SQcodN2rnX9Tn3dHYlqfb7NjlzzfONPpJd+AKoKtUjEdevTfbC07d5TcczzOK6261auRkP/M8OBHs9vFQ==',
        $npmIntegrity,
        'PDF.js npm integrity changed',
    );
    $integrityBytes = is_string($npmIntegrity) ? base64_decode(substr($npmIntegrity, strlen('sha512-')), true) : false;
    assertTrue($integrityBytes !== false, 'PDF.js npm integrity is not valid base64');
    assertSame(
        $component['hashes'][0]['content'] ?? null,
        bin2hex($integrityBytes),
        'PDF.js npm integrity does not match its CycloneDX SHA-512 hash',
    );

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($bundleRoot, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = substr($file->getPathname(), strlen($bundleRoot) + 1);
        }
    }
    sort($files, SORT_STRING);
    assertSame((string)count($files), $properties['filzmann:bundled-file-count'] ?? null, 'PDF.js bundle file count changed');

    $treeHash = hash_init('sha256');
    foreach ($files as $file) {
        hash_update($treeHash, $file . "\0");
        assertTrue(hash_update_file($treeHash, $bundleRoot . '/' . $file), "PDF.js asset cannot be hashed: {$file}");
    }
    assertSame(
        $properties['filzmann:bundled-tree-sha256'] ?? null,
        hash_final($treeHash),
        'PDF.js bundle tree differs from the canonical component inventory',
    );

    $fileComponents = [];
    foreach ($component['components'] ?? [] as $fileComponent) {
        $fileComponents[$fileComponent['name'] ?? ''] = $fileComponent;
    }
    foreach ([
        'js/vendor/pdfjs/pdf.min.mjs' => 'e0be3863c23c8af2305b16548febd58e7f8874a460253317d7771cddbc1c0f6d',
        'js/vendor/pdfjs/pdf.worker.min.mjs' => '0613f41490dd6aaceed7a93fbbd38c85e6d6aa60474b6588c6e7709cfbe18cb3',
    ] as $path => $expectedHash) {
        assertSame('file', $fileComponents[$path]['type'] ?? null, "PDF.js SBOM file component is missing: {$path}");
        assertSame($expectedHash, $fileComponents[$path]['hashes'][0]['content'] ?? null, "PDF.js SBOM hash changed: {$path}");
        assertSame($expectedHash, hash_file('sha256', $appRoot . '/' . $path), "Pinned PDF.js asset changed: {$path}");
    }

    $version = file_get_contents($bundleRoot . '/VERSION');
    assertTrue($version !== false && str_contains($version, 'PDF.js 6.2.108'), 'Pinned PDF.js version is not documented');
});
