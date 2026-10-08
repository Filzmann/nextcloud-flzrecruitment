<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Settings;

use OCA\FlzRecruitment\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/** Registriert den ausschließlich für Nextcloud-Admins sichtbaren App-Abschnitt. */
final class AdminSection implements IIconSection {
    public function __construct(private IURLGenerator $url) {}

    public function getIcon(): string {
        return $this->url->imagePath(Application::APP_ID, 'app.svg');
    }

    public function getID(): string {
        return Application::APP_ID;
    }

    public function getName(): string {
        return 'Filzmann Recruitment';
    }

    public function getPriority(): int {
        return 64;
    }
}
