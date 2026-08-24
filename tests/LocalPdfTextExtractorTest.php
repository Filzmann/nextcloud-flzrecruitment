<?php

declare(strict_types=1);

use OCA\Recruitment\Service\LocalPdfTextExtractor;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;

TestRunner::test('missing local PDF tools are reported unavailable and fail closed', static function (): void {
    $extractor = new LocalPdfTextExtractor('/definitely/not/installed/pdftotext');
    assertSame(false, $extractor->available());
    assertSame('Nicht verfügbar', $extractor->engineLabel());
    assertSame('', $extractor->extract("%PDF-1.4\nsynthetisch"));
});
