<?php

declare(strict_types=1);

namespace OCA\Recruitment\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Additive Grundlage für lokale Basisqualifikationsdurchläufe und -bewertungen. */
final class Version000003Date202608010002 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('rec_jobs')) {
            $jobs = $schema->getTable('rec_jobs');
            if (!$jobs->hasColumn('basis_qualification_required')) {
                $jobs->addColumn('basis_qualification_required', Types::BOOLEAN, [
                    'notnull' => true,
                    'default' => false,
                ]);
            }
            if (!$jobs->hasIndex('rec_job_bq_active')) {
                $jobs->addIndex(['basis_qualification_required', 'active'], 'rec_job_bq_active');
            }
        }

        if (!$schema->hasTable('rec_bq_runs')) {
            $table = $schema->createTable('rec_bq_runs');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('label', Types::STRING, ['length' => 32, 'notnull' => true]);
            $table->addColumn('starts_on', Types::DATE_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('ends_on', Types::DATE_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['starts_on', 'ends_on'], 'rec_bq_run_dates');
        }

        if (!$schema->hasTable('rec_bq_assignments')) {
            $table = $schema->createTable('rec_bq_assignments');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('application_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('run_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('result', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'pending']);
            $table->addColumn('evaluation_note', Types::TEXT, ['notnull' => true, 'default' => '']);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('evaluated_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['application_id', 'run_id'], 'rec_bq_app_run_unique');
            $table->addIndex(['application_id', 'result'], 'rec_bq_app_result');
            $table->addIndex(['run_id', 'result'], 'rec_bq_run_result');
            $table->addForeignKeyConstraint($schema->getTable('rec_applications'), ['application_id'], ['id'], ['onDelete' => 'CASCADE'], 'rec_bq_app_fk');
            $table->addForeignKeyConstraint($schema->getTable('rec_bq_runs'), ['run_id'], ['id'], ['onDelete' => 'RESTRICT'], 'rec_bq_run_fk');
        }

        return $schema;
    }
}
