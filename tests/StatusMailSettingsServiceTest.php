<?php

declare(strict_types=1);

namespace OCA\Recruitment\AppInfo {
    if (!class_exists(Application::class, false)) {
        final class Application {
            public const APP_ID = 'adrecruitment';
        }
    }
}

namespace {
    use OCA\Recruitment\Exception\ConflictException;
    use OCA\Recruitment\Exception\ValidationException;
    use OCA\Recruitment\Service\StatusMailSettingsService;
    use RecruitmentTests\TestRunner;

    use function RecruitmentTests\assertSame;
    use function RecruitmentTests\assertThrows;

    TestRunner::test('mail test routing is disabled by default and saved with optimistic revision', static function (): void {
    $config = new class implements \OCP\IAppConfig {
        public array $values = [];
        public function getValueString(string $appId, string $key, string $default = ''): string { return $this->values[$appId][$key] ?? $default; }
        public function setValueString(string $appId, string $key, string $value): void { $this->values[$appId][$key] = $value; }
    };
    $service = new StatusMailSettingsService($config);
    assertSame(['testMode' => false, 'testRecipient' => '', 'revision' => 0], $service->settings());
    assertSame(['testMode' => true, 'testRecipient' => 'test@example.invalid', 'revision' => 1], $service->save(true, ' test@example.invalid ', 0));
    assertThrows(static fn () => $service->save(false, '', 0), ConflictException::class);
    });

    TestRunner::test('mail test mode rejects an invalid standard recipient without mutation', static function (): void {
    $config = new class implements \OCP\IAppConfig {
        public array $values = [];
        public function getValueString(string $appId, string $key, string $default = ''): string { return $this->values[$appId][$key] ?? $default; }
        public function setValueString(string $appId, string $key, string $value): void { $this->values[$appId][$key] = $value; }
    };
    $service = new StatusMailSettingsService($config);
    assertThrows(static fn () => $service->save(true, 'ungültig', 0), ValidationException::class);
    assertSame([], $config->values);
    });
}
