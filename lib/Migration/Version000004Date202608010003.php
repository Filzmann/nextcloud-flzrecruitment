<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Migration;

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

        if ($schema->hasTable('flz_recruitment_applications')) {
            $applications = $schema->getTable('flz_recruitment_applications');
            if (!$applications->hasColumn('desired_weekly_hours')) {
                $applications->addColumn('desired_weekly_hours', Types::DECIMAL, [
                    'precision' => 5,
                    'scale' => 2,
                    'notnull' => false,
                ]);
            }
        }

        if ($schema->hasTable('flz_recruitment_jobs')) {
            $jobs = $schema->getTable('flz_recruitment_jobs');
            if (!$jobs->hasColumn('profession_category')) {
                $jobs->addColumn('profession_category', Types::STRING, [
                    'length' => 32,
                    'notnull' => true,
                    'default' => 'other',
                ]);
            }
            if (!$jobs->hasIndex('flz_recruitment_job_category_active')) {
                $jobs->addIndex(['profession_category', 'active'], 'flz_recruitment_job_category_active');
            }
        }

        return $schema;
    }
}
