<?php

declare(strict_types=1);

namespace OCA\Recruitment\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Additive Grundlage für einen revisionssicheren, app-privaten Bewerbungs-Posteingang. */
final class Version000006Date202608020001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('rec_mailboxes')) {
            $table = $schema->createTable('rec_mailboxes');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('technical_key', Types::STRING, ['length' => 100, 'notnull' => true]);
            $table->addColumn('label', Types::STRING, ['length' => 255, 'notnull' => true]);
            $table->addColumn('address', Types::STRING, ['length' => 320, 'notnull' => true]);
            $table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['technical_key'], 'rec_mailbox_key');
        }

        if (!$schema->hasTable('rec_messages')) {
            $table = $schema->createTable('rec_messages');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('mailbox_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('external_message_id', Types::STRING, ['length' => 255, 'notnull' => false]);
            $table->addColumn('content_hash', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('state', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'new']);
            $table->addColumn('sender_address', Types::STRING, ['length' => 320, 'notnull' => true]);
            $table->addColumn('recipients_json', Types::TEXT, ['notnull' => true, 'default' => '[]']);
            $table->addColumn('subject', Types::STRING, ['length' => 998, 'notnull' => true, 'default' => '']);
            $table->addColumn('received_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('body_text', Types::TEXT, ['notnull' => true, 'default' => '']);
            $table->addColumn('field_suggestions_json', Types::TEXT, ['notnull' => true, 'default' => '{}']);
            $table->addColumn('application_id', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('imported_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint($schema->getTable('rec_mailboxes'), ['mailbox_id'], ['id'], ['onDelete' => 'RESTRICT'], 'rec_message_mailbox_fk');
            $table->addForeignKeyConstraint($schema->getTable('rec_applications'), ['application_id'], ['id'], ['onDelete' => 'RESTRICT'], 'rec_message_app_fk');
            $table->addUniqueIndex(['mailbox_id', 'external_message_id'], 'rec_message_external');
            $table->addUniqueIndex(['mailbox_id', 'content_hash'], 'rec_message_content');
            $table->addIndex(['state', 'received_at'], 'rec_message_state');
            $table->addIndex(['application_id', 'received_at'], 'rec_message_app');
        }

        if (!$schema->hasTable('rec_attachments')) {
            $table = $schema->createTable('rec_attachments');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('message_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('original_name', Types::STRING, ['length' => 255, 'notnull' => true]);
            $table->addColumn('stored_name', Types::STRING, ['length' => 68, 'notnull' => true]);
            $table->addColumn('mime_type', Types::STRING, ['length' => 100, 'notnull' => true]);
            $table->addColumn('size_bytes', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('content_hash', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('storage_path', Types::STRING, ['length' => 255, 'notnull' => true]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint($schema->getTable('rec_messages'), ['message_id'], ['id'], ['onDelete' => 'CASCADE'], 'rec_attachment_message_fk');
            $table->addUniqueIndex(['message_id', 'content_hash'], 'rec_attachment_content');
        }

        if (!$schema->hasTable('rec_message_audit')) {
            $table = $schema->createTable('rec_message_audit');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('message_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('from_state', Types::STRING, ['length' => 32, 'notnull' => true]);
            $table->addColumn('to_state', Types::STRING, ['length' => 32, 'notnull' => true]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('details_json', Types::TEXT, ['notnull' => true, 'default' => '{}']);
            $table->addColumn('changed_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint($schema->getTable('rec_messages'), ['message_id'], ['id'], ['onDelete' => 'CASCADE'], 'rec_message_audit_fk');
            $table->addIndex(['message_id', 'changed_at'], 'rec_message_audit_history');
        }

        return $schema;
    }
}
