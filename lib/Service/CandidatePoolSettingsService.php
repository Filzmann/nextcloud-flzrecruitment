<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;

use OCA\FlzRecruitment\AppInfo\Application;
use OCA\FlzRecruitment\Exception\ConflictException;
use OCA\FlzRecruitment\Exception\ValidationException;
use OCP\IAppConfig;

final class CandidatePoolSettingsService {
    public function __construct(private IAppConfig $config) {}

    /** @return array{enabled:bool,noticeVersion:string,consentMonths:int,reminderDays:int,revision:int} */
    public function settings(): array {
        return [
            'enabled' => $this->config->getValueString(Application::APP_ID, 'candidate_pool_enabled', '0') === '1',
            'noticeVersion' => $this->config->getValueString(Application::APP_ID, 'candidate_pool_notice_version', ''),
            'consentMonths' => (int)$this->config->getValueString(Application::APP_ID, 'candidate_pool_consent_months', '12'),
            'reminderDays' => (int)$this->config->getValueString(Application::APP_ID, 'candidate_pool_reminder_days', '30'),
            'revision' => (int)$this->config->getValueString(Application::APP_ID, 'candidate_pool_revision', '0'),
        ];
    }

    public function save(bool $enabled, string $noticeVersion, int $consentMonths, int $reminderDays, int $expectedRevision): array {
        $current = $this->settings();
        if ($current['revision'] !== $expectedRevision) throw new ConflictException('Die Bewerberpool-Einstellung wurde zwischenzeitlich geändert.');
        $noticeVersion = trim($noticeVersion);
        if ($enabled && ($noticeVersion === '' || strlen($noticeVersion) > 64)) throw new ValidationException('Vor der Aktivierung ist eine gültige Version des Datenschutzhinweises erforderlich.');
        if ($consentMonths < 1 || $consentMonths > 24 || $reminderDays < 1 || $reminderDays > 90) throw new ValidationException('Aufbewahrungs- oder Erinnerungsfrist liegt außerhalb des zulässigen Bereichs.');
        $revision = $expectedRevision + 1;
        foreach ([
            'candidate_pool_enabled' => $enabled ? '1' : '0',
            'candidate_pool_notice_version' => $noticeVersion,
            'candidate_pool_consent_months' => (string)$consentMonths,
            'candidate_pool_reminder_days' => (string)$reminderDays,
            'candidate_pool_revision' => (string)$revision,
        ] as $key => $value) $this->config->setValueString(Application::APP_ID, $key, $value);
        return ['enabled' => $enabled, 'noticeVersion' => $noticeVersion, 'consentMonths' => $consentMonths, 'reminderDays' => $reminderDays, 'revision' => $revision];
    }
}
