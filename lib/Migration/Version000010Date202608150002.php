<?php

declare(strict_types=1);

namespace OCA\Recruitment\Migration;

use Closure;
use OCA\Recruitment\BackgroundJob\DeliverStatusMailJob;
use OCP\BackgroundJob\IJobList;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Registriert den Statusmail-Versandjob additiv auch bei bereits installierten App-Versionen. */
final class Version000010Date202608150002 extends SimpleMigrationStep {
    public function __construct(private IJobList $jobs) {}

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        if (!$this->jobs->has(DeliverStatusMailJob::class, null)) {
            $this->jobs->add(DeliverStatusMailJob::class);
        }
    }
}
