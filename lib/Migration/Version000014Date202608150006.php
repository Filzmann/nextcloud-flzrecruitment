<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000014Date202608150006 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $jobs = $schema->getTable('flz_recruitment_jobs');
        foreach ([
            'contract_term' => [Types::STRING, ['length' => 32, 'notnull' => false]],
            'pay_grade' => [Types::STRING, ['length' => 8, 'notnull' => false]],
            'advertised_weekly_hours' => [Types::DECIMAL, ['precision' => 5, 'scale' => 2, 'notnull' => false]],
            'full_time_weekly_hours' => [Types::DECIMAL, ['precision' => 5, 'scale' => 2, 'notnull' => false]],
            'vacation_days' => [Types::DECIMAL, ['precision' => 5, 'scale' => 2, 'notnull' => false]],
            'work_location' => [Types::STRING, ['length' => 128, 'notnull' => true, 'default' => 'Berlin']],
        ] as $column => [$type, $definition]) {
            if (!$jobs->hasColumn($column)) {
                $jobs->addColumn($column, $type, $definition);
            }
        }
        return $schema;
    }
}
