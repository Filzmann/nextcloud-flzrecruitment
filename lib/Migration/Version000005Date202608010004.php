<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Ergänzt einen optionalen oberen Wert für unverbindliche Wunschstundenbereiche. */
final class Version000005Date202608010004 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('flz_recruitment_applications')) {
            $applications = $schema->getTable('flz_recruitment_applications');
            if (!$applications->hasColumn('desired_weekly_hours_max')) {
                $applications->addColumn('desired_weekly_hours_max', Types::DECIMAL, [
                    'precision' => 5,
                    'scale' => 2,
                    'notnull' => false,
                ]);
            }
        }

        return $schema;
    }
}
