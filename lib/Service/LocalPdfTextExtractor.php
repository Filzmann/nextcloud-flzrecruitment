<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\Recruitment\Contract\PdfTextExtractor;

/** Lokale, KI-freie PDF-Textextraktion ohne Netzwerkzugriff. */
final class LocalPdfTextExtractor implements PdfTextExtractor {
    private const MAX_TEXT_BYTES = 2 * 1024 * 1024;
    private const TIMEOUT_SECONDS = 15.0;
    private ?string $engine = null;

    public function __construct(
        private ?string $executable = null,
        private int $maxTextBytes = self::MAX_TEXT_BYTES,
        private float $timeoutSeconds = self::TIMEOUT_SECONDS,
    ) {
        $this->maxTextBytes = max(1, $this->maxTextBytes);
        $this->timeoutSeconds = max(0.001, $this->timeoutSeconds);
        if ($this->executable !== null) {
            if (is_file($this->executable) && is_executable($this->executable)) {
                $this->engine = $this->engineForExecutable($this->executable);
            }
            if ($this->engine === null) {
                $this->executable = null;
            }
            return;
        }

        $path = getenv('PATH');
        foreach ($path === false ? [] : explode(PATH_SEPARATOR, $path) as $directory) {
            $directory = rtrim(trim($directory), DIRECTORY_SEPARATOR);
            if ($directory === '') continue;
            foreach (['pdftotext', 'gs'] as $command) {
                $candidate = $directory . DIRECTORY_SEPARATOR . $command;
                if (!is_file($candidate) || !is_executable($candidate)) continue;
                $this->executable = $candidate;
                $this->engine = $this->engineForExecutable($candidate);
                break 2;
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
            $deadline = microtime(true) + $this->timeoutSeconds;
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
            $text = file_get_contents($outputPath, false, null, 0, $this->maxTextBytes + 1);
            if ($text === false || strlen($text) > $this->maxTextBytes) return '';
            return trim(str_replace("\0", '', str_replace(["\r\n", "\r"], "\n", $text)));
        } finally {
            if (is_file($inputPath)) unlink($inputPath);
            if (is_file($outputPath)) unlink($outputPath);
        }
    }

    private function engineForExecutable(string $executable): ?string {
        return match (strtolower(basename($executable))) {
            'pdftotext' => 'pdftotext',
            'gs', 'ghostscript' => 'ghostscript',
            default => null,
        };
    }
}
