<?php

declare(strict_types=1);

namespace OCA\Recruitment\AppInfo {
    if (!class_exists(Application::class, false)) {
        final class Application { public const APP_ID = 'adrecruitment'; }
    }
}

namespace {
    use OCA\Recruitment\Exception\ConflictException;
    use OCA\Recruitment\Exception\ValidationException;
    use OCA\Recruitment\Service\ResumeExtractionSettingsService;
    use RecruitmentTests\TestRunner;

    use function RecruitmentTests\assertSame;
    use function RecruitmentTests\assertThrows;

    TestRunner::test('resume extraction defaults to local rules and exposes future local models honestly', static function (): void {
        $config = new class implements \OCP\IAppConfig {
            public array $values = [];
            public function getValueString(string $appId, string $key, string $default = ''): string { return $this->values[$appId][$key] ?? $default; }
            public function setValueString(string $appId, string $key, string $value): void { $this->values[$appId][$key] = $value; }
        };
        $pdfText = new class implements \OCA\Recruitment\Contract\PdfTextExtractor {
            public function available(): bool { return true; }
            public function engineLabel(): string { return 'Test-PDF-Extraktor'; }
            public function extract(string $pdfContent): string { return ''; }
        };
        $service = new ResumeExtractionSettingsService($config, $pdfText);
        assertSame([
            'method' => 'rules',
            'revision' => 0,
            'options' => [
                ['value' => 'rules', 'label' => 'Lokale Stichworterkennung', 'available' => true],
                ['value' => 'local_model', 'label' => 'Lokales Server-Modell', 'available' => false],
            ],
            'runtime' => ['pdfTextAvailable' => true, 'engine' => 'Test-PDF-Extraktor'],
        ], $service->settings());
        assertSame('rules', $service->save('rules', 0)['method']);
        assertThrows(static fn() => $service->save('rules', 0), ConflictException::class);
    });

    TestRunner::test('an unavailable local model cannot be activated or mutate configuration', static function (): void {
        $config = new class implements \OCP\IAppConfig {
            public array $values = [];
            public function getValueString(string $appId, string $key, string $default = ''): string { return $this->values[$appId][$key] ?? $default; }
            public function setValueString(string $appId, string $key, string $value): void { $this->values[$appId][$key] = $value; }
        };
        $service = new ResumeExtractionSettingsService($config, new class implements \OCA\Recruitment\Contract\PdfTextExtractor {
            public function available(): bool { return false; }
            public function engineLabel(): string { return 'Nicht verfügbar'; }
            public function extract(string $pdfContent): string { return ''; }
        });
        assertThrows(static fn() => $service->save('local_model', 0), ValidationException::class);
        assertSame([], $config->values);
    });
}
