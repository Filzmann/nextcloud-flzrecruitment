<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('initial migration declares all separated aggregate tables idempotently', static function (): void {
    $source = file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000001Date202607260001.php');
    if ($source === false) {
        throw new RuntimeException('Migration source is missing');
    }

    foreach ([
        'rec_jobs',
        'rec_people',
        'rec_applications',
        'rec_status_log',
        'rec_templates',
        'rec_questions',
        'rec_bubbles',
        'rec_interviews',
    ] as $table) {
        assertTrue(str_contains($source, "hasTable('{$table}')"), "Missing idempotence guard for {$table}");
        assertTrue(str_contains($source, "createTable('{$table}')"), "Missing table {$table}");
    }

    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('rec_people')"), 'Application/person relation missing');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('rec_jobs')"), 'Application/job relation missing');
    assertTrue(str_contains($source, "addUniqueIndex(['person_id', 'job_id', 'received_on']"), 'Application identity guard missing');
});

TestRunner::test('permission upgrade adds scoped hiring data without modifying the published initial migration', static function (): void {
    $source = @file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000002Date202608010001.php');
    assertTrue($source !== false, 'Additive permission migration is missing');
    foreach (['area_key', 'first_guide_access', 'rec_hiring_data', 'rec_permission_audit'] as $contract) {
        assertTrue(str_contains($source, $contract), "Permission migration contract is missing: {$contract}");
    }
    assertTrue(str_contains($source, "hasTable('rec_hiring_data')"), 'Fresh installation guard for hiring data is missing');
    assertTrue(str_contains($source, "hasColumn('area_key')"), 'Upgrade guard for existing applications is missing');
    assertTrue(
        str_contains($source, "addColumn('area_key', Types::STRING, ['length' => 64, 'notnull' => false])"),
        'Existing applications need a nullable area column until hire approval assigns an area',
    );
    assertTrue(str_contains($source, "hasIndex('rec_app_area_status')"), 'Partially applied area index is not guarded idempotently');
    assertTrue(str_contains($source, "addUniqueIndex(['application_id']"), 'Hiring data is not constrained to one record per application');
});

TestRunner::test('basis qualification upgrade is additive and preserves existing jobs and applications', static function (): void {
    $source = @file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000003Date202608010002.php');
    assertTrue($source !== false, 'Additive basis qualification migration is missing');
    foreach (['basis_qualification_required', 'rec_bq_runs', 'rec_bq_assignments'] as $contract) {
        assertTrue(str_contains($source, $contract), "Basis qualification migration contract is missing: {$contract}");
    }
    assertTrue(str_contains($source, "hasColumn('basis_qualification_required')"), 'Upgrade guard for existing jobs is missing');
    assertTrue(str_contains($source, "'default' => false"), 'Existing jobs must remain non-BQ by default');
    assertTrue(str_contains($source, "hasTable('rec_bq_runs')"), 'Fresh installation guard for BQ runs is missing');
    assertTrue(str_contains($source, "hasTable('rec_bq_assignments')"), 'Fresh installation guard for BQ assignments is missing');
    assertTrue(str_contains($source, "addUniqueIndex(['application_id', 'run_id']"), 'Duplicate assignment to the same run is not prevented');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('rec_applications')"), 'BQ/application relation is missing');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('rec_bq_runs')"), 'BQ/run relation is missing');
});

TestRunner::test('application preference upgrade adds nullable hours and a stable job category', static function (): void {
    $source = @file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000004Date202608010003.php');
    assertTrue($source !== false, 'Additive application preference migration is missing');
    assertTrue(str_contains($source, "hasColumn('desired_weekly_hours')"), 'Existing applications are not guarded during preference upgrade');
    assertTrue(str_contains($source, "addColumn('desired_weekly_hours', Types::DECIMAL"), 'Approximate desired hours are not stored precisely');
    assertTrue(str_contains($source, "'notnull' => false"), 'Existing applications must keep an empty desired-hours value');
    assertTrue(str_contains($source, "hasColumn('profession_category')"), 'Existing jobs are not guarded during category upgrade');
    assertTrue(str_contains($source, "'default' => 'other'"), 'Existing non-BQ jobs need a neutral category');
    assertTrue(str_contains($source, "hasIndex('rec_job_category_active')"), 'Partially applied job category index is not guarded');
});

TestRunner::test('desired-hours range upgrade preserves existing single values', static function (): void {
    $source = @file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000005Date202608010004.php');
    assertTrue($source !== false, 'Additive desired-hours range migration is missing');
    assertTrue(str_contains($source, "hasColumn('desired_weekly_hours_max')"), 'Existing applications are not guarded during range upgrade');
    assertTrue(str_contains($source, "addColumn('desired_weekly_hours_max', Types::DECIMAL"), 'The optional upper desired-hours value is not stored precisely');
    assertTrue(str_contains($source, "'notnull' => false"), 'Existing single desired-hours values must remain valid without an upper bound');
});

TestRunner::test('mail inbox upgrade keeps immutable messages, private attachments and audit relations separate', static function (): void {
    $source = @file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000006Date202608020001.php');
    assertTrue($source !== false, 'Additive mail inbox migration is missing');
    foreach (['rec_mailboxes', 'rec_messages', 'rec_attachments', 'rec_message_audit'] as $table) {
        assertTrue(str_contains($source, "hasTable('{$table}')"), "Inbox migration lacks an idempotence guard for {$table}");
        assertTrue(str_contains($source, "createTable('{$table}')"), "Inbox migration lacks table {$table}");
    }
    assertTrue(str_contains($source, "addUniqueIndex(['mailbox_id', 'external_message_id']"), 'Provider message IDs are not protected against duplicate import');
    assertTrue(str_contains($source, "addUniqueIndex(['mailbox_id', 'content_hash']"), 'Content hashes are not protected against duplicate import');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('rec_applications')"), 'Assigned messages are not related to applications');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('rec_messages')"), 'Attachments and audit entries are not related to messages');
});

TestRunner::test('document review upgrade adds append-only scoped comments without changing attachments', static function (): void {
    $source = @file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000007Date202608020002.php');
    assertTrue($source !== false, 'Additive document-comment migration is missing');
    assertTrue(str_contains($source, "hasTable('rec_document_comments')"), 'Document-comment migration lacks an idempotence guard');
    assertTrue(str_contains($source, "createTable('rec_document_comments')"), 'Document-comment table is missing');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('rec_attachments')"), 'Comments are not related to immutable attachments');
    assertTrue(str_contains($source, "addUniqueIndex(['attachment_id', 'client_key']"), 'Retried comment writes are not idempotent');
    assertTrue(str_contains($source, "addIndex(['attachment_id', 'created_at']"), 'Document comment history is not indexed');
    assertTrue(!str_contains($source, "getTable('rec_attachments')->addColumn"), 'Published attachment records must not be rewritten for comments');
});

TestRunner::test('document field-link upgrade preserves existing applications and immutable PDFs', static function (): void {
    $source = @file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000008Date202608020003.php');
    assertTrue($source !== false, 'Additive document field-link migration is missing');
    foreach (['previous_experience', 'german_language_level', 'free_comment'] as $column) {
        assertTrue(str_contains($source, "hasColumn('{$column}')"), "Existing applications are not guarded for {$column}");
        assertTrue(str_contains($source, "addColumn('{$column}'"), "Application field is missing: {$column}");
    }
    assertTrue(str_contains($source, "hasTable('rec_document_field_links')"), 'Field-link fresh-install guard is missing');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('rec_attachments')"), 'Field links are not related to immutable PDF attachments');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('rec_applications')"), 'Field links are not frozen to their application');
    assertTrue(str_contains($source, "addUniqueIndex(['attachment_id', 'client_key']"), 'Retried field linking is not idempotent');
    assertTrue(str_contains($source, "addIndex(['application_id', 'created_at']"), 'Application field-link history is not indexed');
    assertTrue(!str_contains($source, "getTable('rec_attachments')->addColumn"), 'Immutable attachment records must not be changed for field links');
});
