<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Settings;

use OCA\FlzRecruitment\AppInfo\Application;
use OCA\FlzRecruitment\Service\RecruitmentPermissionSettingsService;
use OCA\FlzRecruitment\Service\ResumeExtractionSettingsService;
use OCA\FlzRecruitment\Service\StatusMailSettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

/** Bindet ausschließlich systemweite Recruitment-Konfiguration in den Nextcloud-Adminbereich ein. */
final class Admin implements ISettings {
    public function __construct(
        private StatusMailSettingsService $mailSettings,
        private ResumeExtractionSettingsService $resumeExtractionSettings,
        private RecruitmentPermissionSettingsService $permissionSettings,
    ) {}

    public function getForm(): TemplateResponse {
        return new TemplateResponse(Application::APP_ID, 'admin', [
            'mailSettings' => $this->mailSettings->settings(),
            'resumeExtractionSettings' => $this->resumeExtractionSettings->settings(),
            'permissionSettings' => $this->permissionSettings->settings(),
        ]);
    }

    public function getSection(): string {
        return Application::APP_ID;
    }

    public function getPriority(): int {
        return 30;
    }
}
