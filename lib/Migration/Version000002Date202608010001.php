<?php

declare(strict_types=1);

namespace OCA\Recruitment\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Additive Grundlage für Bereichsscope, Vertragsvorbereitung und Berechtigungsaudit. */
final class Version000002Date202608010001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('rec_applications')) {
            $applications = $schema->getTable('rec_applications');
            if (!$applications->hasColumn('area_key')) {
                $applications->addColumn('area_key', Types::STRING, ['length' => 64, 'notnull' => false]);
            }
            if (!$applications->hasIndex('rec_app_area_status')) {
                $applications->addIndex(['area_key', 'status'], 'rec_app_area_status');
            }
            if (!$applications->hasColumn('first_guide_access')) {
                $applications->addColumn('first_guide_access', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
            }
        }

        if (!$schema->hasTable('rec_hiring_data')) {
            $table = $schema->createTable('rec_hiring_data');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('application_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('data_json', Types::TEXT, ['notnull' => true, 'default' => '{}']);
            $table->addColumn('version', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['application_id'], 'rec_hiring_app_unique');
            $table->addForeignKeyConstraint($schema->getTable('rec_applications'), ['application_id'], ['id'], ['onDelete' => 'CASCADE'], 'rec_hiring_app_fk');
        }

        if (!$schema->hasTable('rec_permission_audit')) {
            $table = $schema->createTable('rec_permission_audit');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('action', Types::STRING, ['length' => 64, 'notnull' => true]);
            $table->addColumn('subject_uid', Types::STRING, ['length' => 64, 'notnull' => true, 'default' => '']);
            $table->addColumn('application_id', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('details_json', Types::TEXT, ['notnull' => true, 'default' => '{}']);
            $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['application_id', 'created_at'], 'rec_permission_app');
            $table->addIndex(['subject_uid', 'created_at'], 'rec_permission_subject');
        }

        return $schema;
    }
}
