<?php

declare(strict_types=1);

namespace OCA\Recruitment\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Additive Bewerbungspräferenzen und stabile Berufsgruppen der Stellen. */
final class Version000004Date202608010003 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('rec_applications')) {
            $applications = $schema->getTable('rec_applications');
            if (!$applications->hasColumn('desired_weekly_hours')) {
                $applications->addColumn('desired_weekly_hours', Types::DECIMAL, [
                    'precision' => 5,
                    'scale' => 2,
                    'notnull' => false,
                ]);
            }
        }

        if ($schema->hasTable('rec_jobs')) {
            $jobs = $schema->getTable('rec_jobs');
            if (!$jobs->hasColumn('profession_category')) {
                $jobs->addColumn('profession_category', Types::STRING, [
                    'length' => 32,
                    'notnull' => true,
                    'default' => 'other',
                ]);
            }
            if (!$jobs->hasIndex('rec_job_category_active')) {
                $jobs->addIndex(['profession_category', 'active'], 'rec_job_category_active');
            }
        }

        return $schema;
    }
}
