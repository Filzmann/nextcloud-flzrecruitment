<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\Recruitment\AppInfo\Application;
use OCA\Recruitment\Exception\ConflictException;
use OCA\Recruitment\Exception\ValidationException;
use OCP\IAppConfig;

final class StatusMailSettingsService {
    public function __construct(private IAppConfig $config) {
    }

    /** @return array{testMode:bool,testRecipient:string,revision:int} */
    public function settings(): array {
        return [
            'testMode' => $this->config->getValueString(Application::APP_ID, 'mail_test_mode', '0') === '1',
            'testRecipient' => $this->config->getValueString(Application::APP_ID, 'mail_test_recipient', ''),
            'revision' => (int)$this->config->getValueString(Application::APP_ID, 'mail_settings_revision', '0'),
        ];
    }

    public function save(bool $testMode, string $testRecipient, int $expectedRevision): array {
        $current = $this->settings();
        if ($current['revision'] !== $expectedRevision) throw new ConflictException('Die Mail-Testeinstellung wurde zwischenzeitlich geändert.');
        $testRecipient = trim($testRecipient);
        if ($testMode && ($testRecipient === '' || filter_var($testRecipient, FILTER_VALIDATE_EMAIL) === false || strlen($testRecipient) > 320)) {
            throw new ValidationException('Für den Mail-Testmodus ist eine gültige Standardadresse erforderlich.');
        }
        $revision = $expectedRevision + 1;
        $this->config->setValueString(Application::APP_ID, 'mail_test_mode', $testMode ? '1' : '0');
        $this->config->setValueString(Application::APP_ID, 'mail_test_recipient', $testRecipient);
        $this->config->setValueString(Application::APP_ID, 'mail_settings_revision', (string)$revision);
        return ['testMode' => $testMode, 'testRecipient' => $testRecipient, 'revision' => $revision];
    }
}
