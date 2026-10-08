<?php

declare(strict_types=1);

use OCA\FlzRecruitment\Service\LocalPdfTextExtractor;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertTrue;

/** @return array{directory:string,executable:string} */
function pdfExtractorFixture(string $program): array {
    $directory = sys_get_temp_dir() . '/flzrecruitment-extractor-test-' . bin2hex(random_bytes(8));
    if (!mkdir($directory, 0700) || file_put_contents($directory . '/pdftotext', '#!' . PHP_BINARY . "\n<?php\n" . $program) === false) {
        throw new RuntimeException('Die synthetische PDF-Engine konnte nicht angelegt werden.');
    }
    chmod($directory . '/pdftotext', 0700);
    return ['directory' => $directory, 'executable' => $directory . '/pdftotext'];
}

function removePdfExtractorFixture(array $fixture): void {
    if (is_file($fixture['executable'])) unlink($fixture['executable']);
    if (is_dir($fixture['directory'])) rmdir($fixture['directory']);
}

TestRunner::test('missing local PDF tools are reported unavailable and fail closed', static function (): void {
    $extractor = new LocalPdfTextExtractor('/definitely/not/installed/pdftotext');
    assertSame(false, $extractor->available());
    assertSame('Nicht verfügbar', $extractor->engineLabel());
    assertSame('', $extractor->extract("%PDF-1.4\nsynthetisch"));
});

TestRunner::test('pdftotext is discovered through PATH and temporary files are removed', static function (): void {
    $marker = sys_get_temp_dir() . '/flzrecruitment-extractor-marker-' . bin2hex(random_bytes(8));
    $fixture = pdfExtractorFixture(
        'file_put_contents(' . var_export($marker, true) . ', json_encode($argv));' . "\n"
        . 'file_put_contents($argv[5], "Name: Beispiel\\0\\r\\nWohnort: Berlin\\n");' . "\n",
    );
    $previousPath = getenv('PATH');
    try {
        putenv('PATH=' . $fixture['directory']);
        $extractor = new LocalPdfTextExtractor();
        putenv($previousPath === false ? 'PATH' : 'PATH=' . $previousPath);

        assertSame(true, $extractor->available());
        assertSame('Poppler pdftotext', $extractor->engineLabel());
        assertSame("Name: Beispiel\nWohnort: Berlin", $extractor->extract("%PDF-1.4\nsynthetisch"));
        $arguments = json_decode((string)file_get_contents($marker), true, 512, JSON_THROW_ON_ERROR);
        assertTrue(!is_file($arguments[4]), 'Die temporäre PDF-Eingabe muss nach Erfolg gelöscht sein.');
        assertTrue(!is_file($arguments[5]), 'Die temporäre Textausgabe muss nach Erfolg gelöscht sein.');
    } finally {
        putenv($previousPath === false ? 'PATH' : 'PATH=' . $previousPath);
        if (is_file($marker)) unlink($marker);
        removePdfExtractorFixture($fixture);
    }
});

TestRunner::test('a local PDF engine timeout fails closed within the configured test boundary', static function (): void {
    $fixture = pdfExtractorFixture('usleep(5_000_000);' . "\n");
    try {
        $startedAt = microtime(true);
        $text = (new LocalPdfTextExtractor($fixture['executable'], timeoutSeconds: 0.05))->extract("%PDF-1.4\nsynthetisch");
        assertSame('', $text);
        assertTrue(microtime(true) - $startedAt < 1.0, 'Der kontrollierte Timeout darf nicht auf das Prozessende warten.');
    } finally {
        removePdfExtractorFixture($fixture);
    }
});

TestRunner::test('oversized extracted text is rejected instead of being truncated', static function (): void {
    $fixture = pdfExtractorFixture('file_put_contents($argv[5], str_repeat("x", 33));' . "\n");
    try {
        $extractor = new LocalPdfTextExtractor($fixture['executable'], maxTextBytes: 32);
        assertSame('', $extractor->extract("%PDF-1.4\nsynthetisch"));
    } finally {
        removePdfExtractorFixture($fixture);
    }
});

TestRunner::test('runtime discovery does not promise fixed host paths', static function (): void {
    $source = (string)file_get_contents(__DIR__ . '/../lib/Service/LocalPdfTextExtractor.php');
    foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext', '/usr/bin/gs', '/usr/local/bin/gs'] as $fixedPath) {
        assertSame(false, str_contains($source, $fixedPath), 'Feste Hostpfade dürfen keine PDF-Fähigkeit versprechen.');
    }
});
