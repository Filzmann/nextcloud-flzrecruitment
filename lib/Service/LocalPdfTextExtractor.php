<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\Recruitment\Contract\PdfTextExtractor;

/** Lokale, KI-freie PDF-Textextraktion ohne Netzwerkzugriff. */
final class LocalPdfTextExtractor implements PdfTextExtractor {
    private const MAX_TEXT_BYTES = 2 * 1024 * 1024;
    private const TIMEOUT_SECONDS = 15.0;
    private ?string $engine = null;

    public function __construct(private ?string $executable = null) {
        if ($this->executable !== null) {
            if (is_file($this->executable) && is_executable($this->executable)) {
                $this->engine = str_contains(basename($this->executable), 'pdftotext') ? 'pdftotext' : 'ghostscript';
            } else {
                $this->executable = null;
            }
            return;
        }
        foreach ([['/usr/bin/pdftotext', 'pdftotext'], ['/usr/local/bin/pdftotext', 'pdftotext'], ['/usr/bin/gs', 'ghostscript'], ['/usr/local/bin/gs', 'ghostscript']] as [$candidate, $engine]) {
            if (is_file($candidate) && is_executable($candidate)) {
                $this->executable = $candidate;
                $this->engine = $engine;
                break;
            }
        }
    }

    public function available(): bool { return $this->executable !== null && function_exists('proc_open'); }
    public function engineLabel(): string {
        return match ($this->engine) {
            'pdftotext' => 'Poppler pdftotext',
            'ghostscript' => 'Ghostscript txtwrite',
            default => 'Nicht verfügbar',
        };
    }

    public function extract(string $pdfContent): string {
        if (!$this->available()) return '';
        $inputPath = tempnam(sys_get_temp_dir(), 'adrecruitment-pdf-');
        if ($inputPath === false) return '';
        $outputPath = $inputPath . '.txt';
        try {
            if (file_put_contents($inputPath, $pdfContent, LOCK_EX) !== strlen($pdfContent)) return '';
            $command = $this->engine === 'pdftotext'
                ? [$this->executable, '-enc', 'UTF-8', '-nopgbrk', $inputPath, $outputPath]
                : [$this->executable, '-q', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=txtwrite', '-sOutputFile=' . $outputPath, $inputPath];
            $pipes = [];
            $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            if (!is_resource($process)) return '';
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $deadline = microtime(true) + self::TIMEOUT_SECONDS;
            do {
                stream_get_contents($pipes[1]);
                stream_get_contents($pipes[2]);
                $status = proc_get_status($process);
                if (!$status['running']) break;
                if (microtime(true) >= $deadline) {
                    proc_terminate($process);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($process);
                    return '';
                }
                usleep(10_000);
            } while (true);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = $status['exitcode'];
            proc_close($process);
            if ($exitCode !== 0 || !is_file($outputPath)) return '';
            $text = file_get_contents($outputPath, false, null, 0, self::MAX_TEXT_BYTES + 1);
            if ($text === false || strlen($text) > self::MAX_TEXT_BYTES) return '';
            return trim(str_replace("\0", '', str_replace(["\r\n", "\r"], "\n", $text)));
        } finally {
            if (is_file($inputPath)) unlink($inputPath);
            if (is_file($outputPath)) unlink($outputPath);
        }
    }
}
