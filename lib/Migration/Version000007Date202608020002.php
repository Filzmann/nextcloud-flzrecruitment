<?php

declare(strict_types=1);

namespace OCA\Recruitment\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Ergänzt append-only Kommentare, ohne die unveränderlichen PDF-Anhänge umzuschreiben. */
final class Version000007Date202608020002 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('rec_document_comments')) {
            $table = $schema->createTable('rec_document_comments');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('attachment_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('kind', Types::STRING, ['length' => 16, 'notnull' => true]);
            $table->addColumn('body', Types::TEXT, ['notnull' => true]);
            $table->addColumn('page_number', Types::INTEGER, ['notnull' => false]);
            $table->addColumn('anchor_column', Types::STRING, ['length' => 16, 'notnull' => false]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('client_key', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addForeignKeyConstraint($schema->getTable('rec_attachments'), ['attachment_id'], ['id'], ['onDelete' => 'CASCADE'], 'rec_doc_comment_attachment_fk');
            $table->addUniqueIndex(['attachment_id', 'client_key'], 'rec_doc_comment_request');
            $table->addIndex(['attachment_id', 'created_at'], 'rec_doc_comment_history');
        }

        return $schema;
    }
}
