<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\BackgroundJob;

use DateTimeImmutable;
use OCA\FlzRecruitment\Contract\CandidatePoolStore;
use OCA\FlzRecruitment\Service\CandidatePoolMatchingService;
use OCA\FlzRecruitment\Service\CandidatePoolService;
use OCA\FlzRecruitment\Service\CandidatePoolSettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

final class CandidatePoolMaintenanceJob extends TimedJob {
    public function __construct(ITimeFactory $time, private CandidatePoolStore $store, private CandidatePoolService $pool, private CandidatePoolMatchingService $matching, private CandidatePoolSettingsService $settings) {
        parent::__construct($time); $this->setInterval(86400);
    }
    protected function run($argument): void {
        $settings = $this->settings->settings();
        if (!$settings['enabled']) return;
        $this->pool->markRemindersDue($this->store, new DateTimeImmutable('now'), $settings['reminderDays']);
        $this->pool->expireDue($this->store, new DateTimeImmutable('now'));
        $this->matching->refresh($this->store);
    }
}
