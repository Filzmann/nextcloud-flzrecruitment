<?php

declare(strict_types=1);

namespace OCA\Recruitment\BackgroundJob;

use OCA\Recruitment\Service\StatusMailDeliveryService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

final class DeliverStatusMailJob extends TimedJob {
    public function __construct(ITimeFactory $time, private StatusMailDeliveryService $delivery) {
        parent::__construct($time);
        $this->setInterval(60);
    }

    protected function run($argument): void {
        for ($processed = 0; $processed < 25 && $this->delivery->deliverOne(); $processed++) {
        }
    }
}
