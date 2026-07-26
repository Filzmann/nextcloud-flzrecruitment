<?php

declare(strict_types=1);

namespace OCA\Recruitment\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Additive Erstmigration für den getrennten Recruiting-Datenbestand.
 */
final class Version000001Date202607260001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('rec_jobs')) {
            $table = $schema->createTable('rec_jobs');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('internal_title', Types::STRING, ['length' => 255, 'notnull' => true]);
            $table->addColumn('public_title', Types::STRING, ['length' => 255, 'notnull' => true, 'default' => '']);
            $table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
            $table->addColumn('responsible_users', Types::TEXT, ['notnull' => true, 'default' => '[]']);
            $table->addColumn('responsible_groups', Types::TEXT, ['notnull' => true, 'default' => '[]']);
            $table->addColumn('assignment_key', Types::STRING, ['length' => 255, 'notnull' => false]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['active', 'internal_title'], 'rec_job_active');
            $table->addUniqueIndex(['assignment_key'], 'rec_job_key');
        }

        if (!$schema->hasTable('rec_people')) {
            $table = $schema->createTable('rec_people');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('given_name', Types::STRING, ['length' => 255, 'notnull' => true]);
            $table->addColumn('family_name', Types::STRING, ['length' => 255, 'notnull' => true]);
            $table->addColumn('email', Types::STRING, ['length' => 320, 'notnull' => true, 'default' => '']);
            $table->addColumn('phone', Types::STRING, ['length' => 100, 'notnull' => true, 'default' => '']);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['family_name', 'given_name'], 'rec_person_name');
        }

        if (!$schema->hasTable('rec_applications')) {
            $table = $schema->createTable('rec_applications');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('person_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('job_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('source', Types::STRING, ['length' => 100, 'notnull' => true]);
            $table->addColumn('received_on', Types::DATE_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('status', Types::STRING, ['length' => 64, 'notnull' => true, 'default' => 'received']);
            $table->addColumn('assignee_uid', Types::STRING, ['length' => 64, 'notnull' => true, 'default' => '']);
            $table->addColumn('closure_reason', Types::STRING, ['length' => 255, 'notnull' => true, 'default' => '']);
            $table->addColumn('retention_state', Types::STRING, ['length' => 64, 'notnull' => true, 'default' => 'active']);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint($schema->getTable('rec_people'), ['person_id'], ['id'], ['onDelete' => 'RESTRICT'], 'rec_app_person_fk');
            $table->addForeignKeyConstraint($schema->getTable('rec_jobs'), ['job_id'], ['id'], ['onDelete' => 'RESTRICT'], 'rec_app_job_fk');
            $table->addUniqueIndex(['person_id', 'job_id', 'received_on'], 'rec_app_identity');
            $table->addIndex(['status', 'received_on'], 'rec_app_status');
        }

        if (!$schema->hasTable('rec_status_log')) {
            $table = $schema->createTable('rec_status_log');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('application_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('from_status', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('to_status', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('changed_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint($schema->getTable('rec_applications'), ['application_id'], ['id'], ['onDelete' => 'CASCADE'], 'rec_log_app_fk');
            $table->addIndex(['application_id', 'changed_at'], 'rec_log_history');
        }

        if (!$schema->hasTable('rec_templates')) {
            $table = $schema->createTable('rec_templates');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
            $table->addColumn('type', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('description', Types::TEXT, ['notnull' => true, 'default' => '']);
            $table->addColumn('audience', Types::STRING, ['length' => 32, 'notnull' => true]);
            $table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
            $table->addColumn('revision', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['active', 'name'], 'rec_tpl_active');
        }

        if (!$schema->hasTable('rec_questions')) {
            $table = $schema->createTable('rec_questions');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('template_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('prompt', Types::TEXT, ['notnull' => true]);
            $table->addColumn('hint', Types::TEXT, ['notnull' => true, 'default' => '']);
            $table->addColumn('type', Types::STRING, ['length' => 32, 'notnull' => true]);
            $table->addColumn('required', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
            $table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $table->addColumn('options_json', Types::TEXT, ['notnull' => true, 'default' => '[]']);
            $table->addColumn('visibility', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'internal']);
            $table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint($schema->getTable('rec_templates'), ['template_id'], ['id'], ['onDelete' => 'CASCADE'], 'rec_question_tpl_fk');
            $table->addIndex(['template_id', 'sort_order'], 'rec_question_order');
        }

        if (!$schema->hasTable('rec_bubbles')) {
            $table = $schema->createTable('rec_bubbles');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('question_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('label', Types::STRING, ['length' => 100, 'notnull' => true]);
            $table->addColumn('insert_text', Types::TEXT, ['notnull' => true]);
            $table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint($schema->getTable('rec_questions'), ['question_id'], ['id'], ['onDelete' => 'CASCADE'], 'rec_bubble_question_fk');
            $table->addIndex(['question_id', 'sort_order'], 'rec_bubble_order');
        }

        if (!$schema->hasTable('rec_interviews')) {
            $table = $schema->createTable('rec_interviews');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('application_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('template_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('template_revision', Types::INTEGER, ['notnull' => true]);
            $table->addColumn('snapshot_json', Types::TEXT, ['notnull' => true]);
            $table->addColumn('answers_json', Types::TEXT, ['notnull' => true, 'default' => '{}']);
            $table->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'not_started']);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('completed_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint($schema->getTable('rec_applications'), ['application_id'], ['id'], ['onDelete' => 'CASCADE'], 'rec_interview_app_fk');
            $table->addForeignKeyConstraint($schema->getTable('rec_templates'), ['template_id'], ['id'], ['onDelete' => 'RESTRICT'], 'rec_interview_tpl_fk');
            $table->addIndex(['application_id', 'created_at'], 'rec_interview_app');
        }

        return $schema;
    }
}
