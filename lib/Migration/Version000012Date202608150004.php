<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Migration;

use Closure;
use OCA\FlzRecruitment\BackgroundJob\CandidatePoolMaintenanceJob;
use OCP\BackgroundJob\IJobList;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Additive, datensparsame Rückstellung mit separatem Einwilligungsnachweis. */
final class Version000012Date202608150004 extends SimpleMigrationStep {
    public function __construct(private IJobList $jobs) {}

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();
        if (!$schema->hasTable('flz_recruitment_pool_entries')) {
            $table = $schema->createTable('flz_recruitment_pool_entries');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('source_application_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('status', Types::STRING, ['length' => 24, 'notnull' => true]);
            $table->addColumn('profession_category', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('desired_weekly_hours', Types::DECIMAL, ['precision' => 5, 'scale' => 2, 'notnull' => false]);
            $table->addColumn('desired_weekly_hours_max', Types::DECIMAL, ['precision' => 5, 'scale' => 2, 'notnull' => false]);
            $table->addColumn('area_keys_json', Types::TEXT, ['notnull' => true]);
            $table->addColumn('consent_notice_version', Types::STRING, ['length' => 64, 'notnull' => true]);
            foreach (['requested_at', 'consented_at', 'expires_at', 'reminder_sent_at', 'withdrawn_at'] as $column) $table->addColumn($column, Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['source_application_id'], 'flz_recruitment_pool_source_app');
            $table->addIndex(['status', 'expires_at'], 'flz_recruitment_pool_status_expiry');
            $table->addForeignKeyConstraint($schema->getTable('flz_recruitment_people'), ['person_id'], ['id'], ['onDelete' => 'RESTRICT'], 'flz_recruitment_pool_person_fk');
            $table->addForeignKeyConstraint($schema->getTable('flz_recruitment_applications'), ['source_application_id'], ['id'], ['onDelete' => 'RESTRICT'], 'flz_recruitment_pool_app_fk');
        }
        if (!$schema->hasTable('flz_recruitment_pool_consents')) {
            $table = $schema->createTable('flz_recruitment_pool_consents');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('entry_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('action', Types::STRING, ['length' => 24, 'notnull' => true]);
            $table->addColumn('notice_version', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('evidence_type', Types::STRING, ['length' => 32, 'notnull' => true]);
            $table->addColumn('evidence_reference', Types::STRING, ['length' => 255, 'notnull' => true]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('occurred_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('valid_until', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['entry_id', 'occurred_at'], 'flz_recruitment_pool_consent_history');
            $table->addForeignKeyConstraint($schema->getTable('flz_recruitment_pool_entries'), ['entry_id'], ['id'], ['onDelete' => 'CASCADE'], 'flz_recruitment_pool_consent_entry_fk');
        }
        if (!$schema->hasTable('flz_recruitment_pool_matches')) {
            $table = $schema->createTable('flz_recruitment_pool_matches');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('entry_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('job_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('state', Types::STRING, ['length' => 24, 'notnull' => true, 'default' => 'suggested']);
            $table->addColumn('reasons_json', Types::TEXT, ['notnull' => true]);
            $table->addColumn('reviewed_by_uid', Types::STRING, ['length' => 64, 'notnull' => false]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['entry_id', 'job_id'], 'flz_recruitment_pool_entry_job');
            $table->addIndex(['state', 'created_at'], 'flz_recruitment_pool_match_state');
            $table->addForeignKeyConstraint($schema->getTable('flz_recruitment_pool_entries'), ['entry_id'], ['id'], ['onDelete' => 'CASCADE'], 'flz_recruitment_pool_match_entry_fk');
            $table->addForeignKeyConstraint($schema->getTable('flz_recruitment_jobs'), ['job_id'], ['id'], ['onDelete' => 'CASCADE'], 'flz_recruitment_pool_match_job_fk');
        }
        return $schema;
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        if (!$this->jobs->has(CandidatePoolMaintenanceJob::class, null)) $this->jobs->add(CandidatePoolMaintenanceJob::class);
    }
}
