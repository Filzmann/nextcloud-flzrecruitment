<?php
declare(strict_types=1);
namespace OCA\Recruitment\AppInfo { if (!class_exists(Application::class, false)) { final class Application { public const APP_ID = 'adrecruitment'; } } }
namespace {
use OCA\Recruitment\Exception\ValidationException;
use OCA\Recruitment\Service\CandidatePoolSettingsService;
use RecruitmentTests\TestRunner;
use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;
TestRunner::test('candidate pool stays disabled until a versioned privacy notice is configured', static function (): void {
    $config = new class implements \OCP\IAppConfig { public array $values = []; public function getValueString(string $a, string $k, string $d = ''): string { return $this->values[$a][$k] ?? $d; } public function setValueString(string $a, string $k, string $v): void { $this->values[$a][$k] = $v; } };
    $service = new CandidatePoolSettingsService($config);
    assertSame(['enabled' => false, 'noticeVersion' => '', 'consentMonths' => 12, 'reminderDays' => 30, 'revision' => 0], $service->settings());
    assertThrows(fn() => $service->save(true, '', 12, 30, 0), ValidationException::class);
    assertSame(true, $service->save(true, '2026-08', 12, 30, 0)['enabled']);
});
}
