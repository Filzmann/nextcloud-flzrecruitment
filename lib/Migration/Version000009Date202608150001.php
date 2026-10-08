<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Ergänzt versionierte Statusmail-Vorlagen, bearbeitbare Entwürfe und eine terminierte Outbox. */
final class Version000009Date202608150001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('flz_recruitment_mail_templates')) {
            $table = $schema->createTable('flz_recruitment_mail_templates');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
            $table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
            $table->addColumn('current_revision', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['name'], 'flz_recruitment_mail_tpl_name');
        }

        if (!$schema->hasTable('flz_recruitment_mail_template_revisions')) {
            $table = $schema->createTable('flz_recruitment_mail_template_revisions');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('template_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('revision', Types::INTEGER, ['notnull' => true]);
            $table->addColumn('subject_template', Types::STRING, ['length' => 998, 'notnull' => true]);
            $table->addColumn('body_template', Types::TEXT, ['notnull' => true]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['template_id', 'revision'], 'flz_recruitment_mail_tpl_revision');
            $table->addForeignKeyConstraint($schema->getTable('flz_recruitment_mail_templates'), ['template_id'], ['id'], ['onDelete' => 'CASCADE'], 'flz_recruitment_mail_rev_tpl_fk');
        }

        if (!$schema->hasTable('flz_recruitment_mail_text_blocks')) {
            $table = $schema->createTable('flz_recruitment_mail_text_blocks');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('label', Types::STRING, ['length' => 255, 'notnull' => true]);
            $table->addColumn('insert_text', Types::TEXT, ['notnull' => true]);
            $table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['label'], 'flz_recruitment_mail_block_label');
        }

        if (!$schema->hasTable('flz_recruitment_status_mail_rules')) {
            $table = $schema->createTable('flz_recruitment_status_mail_rules');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('from_status', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('to_status', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('template_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('enabled', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
            $table->addColumn('default_timing', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'immediate']);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['from_status', 'to_status'], 'flz_recruitment_mail_rule_edge');
            $table->addForeignKeyConstraint($schema->getTable('flz_recruitment_mail_templates'), ['template_id'], ['id'], ['onDelete' => 'RESTRICT'], 'flz_recruitment_mail_rule_tpl_fk');
        }

        if (!$schema->hasTable('flz_recruitment_mail_drafts')) {
            $table = $schema->createTable('flz_recruitment_mail_drafts');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('application_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('from_status', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('to_status', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('template_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('template_revision', Types::INTEGER, ['notnull' => true]);
            $table->addColumn('original_recipient', Types::STRING, ['length' => 320, 'notnull' => true]);
            $table->addColumn('intended_recipient', Types::STRING, ['length' => 320, 'notnull' => false]);
            $table->addColumn('delivery_recipient', Types::STRING, ['length' => 320, 'notnull' => false]);
            $table->addColumn('subject', Types::STRING, ['length' => 998, 'notnull' => true]);
            $table->addColumn('body', Types::TEXT, ['notnull' => true]);
            $table->addColumn('state', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'draft']);
            $table->addColumn('default_timing', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'immediate']);
            $table->addColumn('test_mode', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
            $table->addColumn('scheduled_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('approved_by', Types::STRING, ['length' => 64, 'notnull' => false]);
            $table->addColumn('client_key', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('approved_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['application_id', 'client_key'], 'flz_recruitment_mail_draft_request');
            $table->addIndex(['application_id', 'created_at'], 'flz_recruitment_mail_draft_history');
            $table->addForeignKeyConstraint($schema->getTable('flz_recruitment_applications'), ['application_id'], ['id'], ['onDelete' => 'CASCADE'], 'flz_recruitment_mail_draft_app_fk');
            $table->addForeignKeyConstraint($schema->getTable('flz_recruitment_mail_templates'), ['template_id'], ['id'], ['onDelete' => 'RESTRICT'], 'flz_recruitment_mail_draft_tpl_fk');
        }

        if (!$schema->hasTable('flz_recruitment_mail_outbox')) {
            $table = $schema->createTable('flz_recruitment_mail_outbox');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('draft_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('job_key', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('state', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'pending']);
            $table->addColumn('attempts', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            $table->addColumn('scheduled_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('last_error_code', Types::STRING, ['length' => 64, 'notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('sent_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['draft_id'], 'flz_recruitment_mail_outbox_draft');
            $table->addUniqueIndex(['job_key'], 'flz_recruitment_mail_outbox_job');
            $table->addIndex(['state', 'scheduled_at'], 'flz_recruitment_mail_outbox_due');
            $table->addForeignKeyConstraint($schema->getTable('flz_recruitment_mail_drafts'), ['draft_id'], ['id'], ['onDelete' => 'CASCADE'], 'flz_recruitment_mail_outbox_draft_fk');
        }

        return $schema;
    }
}
