<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Ergänzt Bewerbungsfelder und unveränderliche Herkunftsnachweise aus PDFs. */
final class Version000008Date202608020003 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('flz_recruitment_applications')) {
            $applications = $schema->getTable('flz_recruitment_applications');
            if (!$applications->hasColumn('previous_experience')) {
                $applications->addColumn('previous_experience', Types::TEXT, ['notnull' => false, 'default' => '']);
            }
            if (!$applications->hasColumn('german_language_level')) {
                $applications->addColumn('german_language_level', Types::STRING, ['length' => 32, 'notnull' => false, 'default' => '']);
            }
            if (!$applications->hasColumn('free_comment')) {
                $applications->addColumn('free_comment', Types::TEXT, ['notnull' => false, 'default' => '']);
            }
        }

        if (!$schema->hasTable('flz_recruitment_document_field_links')) {
            $table = $schema->createTable('flz_recruitment_document_field_links');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('attachment_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('application_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('target_field', Types::STRING, ['length' => 32, 'notnull' => true]);
            $table->addColumn('selected_text', Types::TEXT, ['notnull' => true, 'default' => '']);
            $table->addColumn('applied_value', Types::TEXT, ['notnull' => true]);
            $table->addColumn('result_value', Types::TEXT, ['notnull' => true]);
            $table->addColumn('page_number', Types::INTEGER, ['notnull' => true]);
            $table->addColumn('rectangles_json', Types::TEXT, ['notnull' => true, 'default' => '[]']);
            $table->addColumn('replaced_existing', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('client_key', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint($schema->getTable('flz_recruitment_attachments'), ['attachment_id'], ['id'], ['onDelete' => 'CASCADE'], 'flz_recruitment_field_link_attachment_fk');
            $table->addForeignKeyConstraint($schema->getTable('flz_recruitment_applications'), ['application_id'], ['id'], ['onDelete' => 'CASCADE'], 'flz_recruitment_field_link_application_fk');
            $table->addUniqueIndex(['attachment_id', 'client_key'], 'flz_recruitment_field_link_request');
            $table->addIndex(['application_id', 'created_at'], 'flz_recruitment_field_link_history');
        }

        return $schema;
    }
}
