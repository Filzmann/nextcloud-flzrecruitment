<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Contract;

/** Liest ausschließlich Text aus einem bereits validierten PDF-Original. */
interface PdfTextExtractor {
    public function available(): bool;
    public function engineLabel(): string;
    public function extract(string $pdfContent): string;
}
