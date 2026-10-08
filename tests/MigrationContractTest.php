<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertTrue;

TestRunner::test('initial migration declares all separated aggregate tables idempotently', static function (): void {
    $source = file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000001Date202607260001.php');
    if ($source === false) {
        throw new RuntimeException('Migration source is missing');
    }

    foreach ([
        'flz_recruitment_jobs',
        'flz_recruitment_people',
        'flz_recruitment_applications',
        'flz_recruitment_status_log',
        'flz_recruitment_templates',
        'flz_recruitment_questions',
        'flz_recruitment_bubbles',
        'flz_recruitment_interviews',
    ] as $table) {
        assertTrue(str_contains($source, "hasTable('{$table}')"), "Missing idempotence guard for {$table}");
        assertTrue(str_contains($source, "createTable('{$table}')"), "Missing table {$table}");
    }

    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('flz_recruitment_people')"), 'Application/person relation missing');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('flz_recruitment_jobs')"), 'Application/job relation missing');
    assertTrue(str_contains($source, "addUniqueIndex(['person_id', 'job_id', 'received_on']"), 'Application identity guard missing');
});

TestRunner::test('permission upgrade adds scoped hiring data without modifying the published initial migration', static function (): void {
    $source = @file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000002Date202608010001.php');
    assertTrue($source !== false, 'Additive permission migration is missing');
    foreach (['area_key', 'first_guide_access', 'flz_recruitment_hiring_data', 'flz_recruitment_permission_audit'] as $contract) {
        assertTrue(str_contains($source, $contract), "Permission migration contract is missing: {$contract}");
    }
    assertTrue(str_contains($source, "hasTable('flz_recruitment_hiring_data')"), 'Fresh installation guard for hiring data is missing');
    assertTrue(str_contains($source, "hasColumn('area_key')"), 'Upgrade guard for existing applications is missing');
    assertTrue(
        str_contains($source, "addColumn('area_key', Types::STRING, ['length' => 64, 'notnull' => false])"),
        'Existing applications need a nullable area column until hire approval assigns an area',
    );
    assertTrue(str_contains($source, "hasIndex('flz_recruitment_app_area_status')"), 'Partially applied area index is not guarded idempotently');
    assertTrue(str_contains($source, "addUniqueIndex(['application_id']"), 'Hiring data is not constrained to one record per application');
});

TestRunner::test('basis qualification upgrade is additive and preserves existing jobs and applications', static function (): void {
    $source = @file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000003Date202608010002.php');
    assertTrue($source !== false, 'Additive basis qualification migration is missing');
    foreach (['basis_qualification_required', 'flz_recruitment_bq_runs', 'flz_recruitment_bq_assignments'] as $contract) {
        assertTrue(str_contains($source, $contract), "Basis qualification migration contract is missing: {$contract}");
    }
    assertTrue(str_contains($source, "hasColumn('basis_qualification_required')"), 'Upgrade guard for existing jobs is missing');
    assertTrue(str_contains($source, "'default' => false"), 'Existing jobs must remain non-BQ by default');
    assertTrue(str_contains($source, "hasTable('flz_recruitment_bq_runs')"), 'Fresh installation guard for BQ runs is missing');
    assertTrue(str_contains($source, "hasTable('flz_recruitment_bq_assignments')"), 'Fresh installation guard for BQ assignments is missing');
    assertTrue(str_contains($source, "addUniqueIndex(['application_id', 'run_id']"), 'Duplicate assignment to the same run is not prevented');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('flz_recruitment_applications')"), 'BQ/application relation is missing');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('flz_recruitment_bq_runs')"), 'BQ/run relation is missing');
});

TestRunner::test('application preference upgrade adds nullable hours and a stable job category', static function (): void {
    $source = @file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000004Date202608010003.php');
    assertTrue($source !== false, 'Additive application preference migration is missing');
    assertTrue(str_contains($source, "hasColumn('desired_weekly_hours')"), 'Existing applications are not guarded during preference upgrade');
    assertTrue(str_contains($source, "addColumn('desired_weekly_hours', Types::DECIMAL"), 'Approximate desired hours are not stored precisely');
    assertTrue(str_contains($source, "'notnull' => false"), 'Existing applications must keep an empty desired-hours value');
    assertTrue(str_contains($source, "hasColumn('profession_category')"), 'Existing jobs are not guarded during category upgrade');
    assertTrue(str_contains($source, "'default' => 'other'"), 'Existing non-BQ jobs need a neutral category');
    assertTrue(str_contains($source, "hasIndex('flz_recruitment_job_category_active')"), 'Partially applied job category index is not guarded');
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
    foreach (['flz_recruitment_mailboxes', 'flz_recruitment_messages', 'flz_recruitment_attachments', 'flz_recruitment_message_audit'] as $table) {
        assertTrue(str_contains($source, "hasTable('{$table}')"), "Inbox migration lacks an idempotence guard for {$table}");
        assertTrue(str_contains($source, "createTable('{$table}')"), "Inbox migration lacks table {$table}");
    }
    assertTrue(str_contains($source, "addUniqueIndex(['mailbox_id', 'external_message_id']"), 'Provider message IDs are not protected against duplicate import');
    assertTrue(str_contains($source, "addUniqueIndex(['mailbox_id', 'content_hash']"), 'Content hashes are not protected against duplicate import');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('flz_recruitment_applications')"), 'Assigned messages are not related to applications');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('flz_recruitment_messages')"), 'Attachments and audit entries are not related to messages');
});

TestRunner::test('document review upgrade adds append-only scoped comments without changing attachments', static function (): void {
    $source = @file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000007Date202608020002.php');
    assertTrue($source !== false, 'Additive document-comment migration is missing');
    assertTrue(str_contains($source, "hasTable('flz_recruitment_document_comments')"), 'Document-comment migration lacks an idempotence guard');
    assertTrue(str_contains($source, "createTable('flz_recruitment_document_comments')"), 'Document-comment table is missing');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('flz_recruitment_attachments')"), 'Comments are not related to immutable attachments');
    assertTrue(str_contains($source, "addUniqueIndex(['attachment_id', 'client_key']"), 'Retried comment writes are not idempotent');
    assertTrue(str_contains($source, "addIndex(['attachment_id', 'created_at']"), 'Document comment history is not indexed');
    assertTrue(!str_contains($source, "getTable('flz_recruitment_attachments')->addColumn"), 'Published attachment records must not be rewritten for comments');
});

TestRunner::test('document field-link upgrade preserves existing applications and immutable PDFs', static function (): void {
    $source = @file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000008Date202608020003.php');
    assertTrue($source !== false, 'Additive document field-link migration is missing');
    foreach (['previous_experience', 'german_language_level', 'free_comment'] as $column) {
        assertTrue(str_contains($source, "hasColumn('{$column}')"), "Existing applications are not guarded for {$column}");
        assertTrue(str_contains($source, "addColumn('{$column}'"), "Application field is missing: {$column}");
    }
    assertTrue(str_contains($source, "hasTable('flz_recruitment_document_field_links')"), 'Field-link fresh-install guard is missing');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('flz_recruitment_attachments')"), 'Field links are not related to immutable PDF attachments');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('flz_recruitment_applications')"), 'Field links are not frozen to their application');
    assertTrue(str_contains($source, "addUniqueIndex(['attachment_id', 'client_key']"), 'Retried field linking is not idempotent');
    assertTrue(str_contains($source, "addIndex(['application_id', 'created_at']"), 'Application field-link history is not indexed');
    assertTrue(!str_contains($source, "getTable('flz_recruitment_attachments')->addColumn"), 'Immutable attachment records must not be changed for field links');
});

TestRunner::test('status mail upgrade separates revisions, rules, drafts and idempotent outbox jobs', static function (): void {
    $source = @file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000009Date202608150001.php');
    assertTrue($source !== false, 'Additive status-mail migration is missing');
    foreach ([
        'flz_recruitment_mail_templates', 'flz_recruitment_mail_template_revisions', 'flz_recruitment_mail_text_blocks',
        'flz_recruitment_status_mail_rules', 'flz_recruitment_mail_drafts', 'flz_recruitment_mail_outbox',
    ] as $table) {
        assertTrue(str_contains($source, "hasTable('{$table}')"), "Status-mail migration lacks an idempotence guard for {$table}");
        assertTrue(str_contains($source, "createTable('{$table}')"), "Status-mail migration lacks table {$table}");
    }
    assertTrue(str_contains($source, "addUniqueIndex(['template_id', 'revision']"), 'Template revisions are not immutable and uniquely addressable');
    assertTrue(str_contains($source, "addUniqueIndex(['from_status', 'to_status']"), 'A transition can have conflicting mail rules');
    assertTrue(str_contains($source, "addUniqueIndex(['application_id', 'client_key']"), 'Retried status actions can duplicate drafts');
    assertTrue(substr_count($source, "addColumn('default_timing'") === 2, 'Drafts do not preserve the configured scheduling default');
    assertTrue(str_contains($source, "addColumn('intended_recipient'"), 'Draft approval does not preserve an explicit recipient correction');
    assertTrue(str_contains($source, "addUniqueIndex(['draft_id']"), 'One approved draft can create duplicate outbox jobs');
    assertTrue(str_contains($source, "addIndex(['state', 'scheduled_at']"), 'Due outbox jobs cannot be selected safely');
    assertTrue(str_contains($source, "addForeignKeyConstraint(\$schema->getTable('flz_recruitment_applications')"), 'Mail drafts are not scoped to an application');
});

TestRunner::test('status mail delivery job is idempotently registered for existing installations', static function (): void {
    $source = file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000010Date202608150002.php');
    assertTrue($source !== false, 'Status-mail job registration migration is missing');
    assertTrue(str_contains($source, 'IJobList'), 'Status-mail job registration does not use the public Nextcloud job list');
    assertTrue(str_contains($source, 'DeliverStatusMailJob::class'), 'Status-mail delivery job is not registered by the upgrade');
    assertTrue(str_contains($source, '->has('), 'Repeated upgrades can duplicate the status-mail delivery job');
});

TestRunner::test('HTML status-mail upgrade preserves legacy text and fills only missing transition defaults', static function (): void {
    $source = file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000011Date202608150003.php');
    assertTrue($source !== false, 'Additive HTML status-mail migration is missing');
    assertTrue(substr_count($source, "hasColumn('body_format')") === 2, 'Legacy template revisions and drafts lack guarded format columns');
    assertTrue(substr_count($source, "addColumn('body_format'") === 2, 'HTML body formats are not persisted in both snapshots');
    assertTrue(str_contains($source, "'default' => 'plain'"), 'Existing plain-text data is not preserved safely');
    assertTrue(str_contains($source, 'DefaultStatusMailTemplateCatalog'), 'Default transition content has no single catalog');
    assertTrue(str_contains($source, "from('flz_recruitment_status_mail_rules')"), 'Existing transition rules are not checked before seeding');
    assertTrue(str_contains($source, 'if ($existingRule !== false) continue;'), 'Existing transition rules can be overwritten');
});

TestRunner::test('candidate-pool upgrade separates consent evidence and human-reviewed matches', static function (): void {
    $source = file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000012Date202608150004.php');
    assertTrue($source !== false, 'Additive candidate-pool migration is missing');
    foreach (['flz_recruitment_pool_entries', 'flz_recruitment_pool_consents', 'flz_recruitment_pool_matches'] as $table) {
        assertTrue(str_contains($source, "hasTable('{$table}')"), "Candidate-pool migration lacks guard for {$table}");
        assertTrue(str_contains($source, "createTable('{$table}')"), "Candidate-pool table is missing: {$table}");
    }
    assertTrue(str_contains($source, "addUniqueIndex(['source_application_id']"), 'One application can create conflicting pool entries');
    assertTrue(str_contains($source, "addUniqueIndex(['entry_id', 'job_id']"), 'Repeated matching can create duplicate suggestions');
    assertTrue(str_contains($source, "addIndex(['status', 'expires_at']"), 'Expiry maintenance is not indexed');
    assertTrue(str_contains($source, 'CandidatePoolMaintenanceJob::class'), 'Candidate-pool maintenance job is not registered');
});

TestRunner::test('mail-rule consolidation reuses one template for every withdrawal edge', static function (): void {
    $source = file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000013Date202608150005.php');
    assertTrue($source !== false, 'Withdrawal-template consolidation migration is missing');
    assertTrue(str_contains($source, "eq('to_status'"), 'Withdrawal rules are not selected by their target status.');
    assertTrue(str_contains($source, "set('template_id'"), 'Withdrawal rules are not assigned to one reusable template.');
    assertTrue(str_contains($source, "eq('to_status',"), 'Migration could rewrite unrelated status transitions.');
});

TestRunner::test('job-contract upgrade adds nullable tariff facts without rewriting existing hiring records', static function (): void {
    $source = file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000014Date202608150006.php');
    assertTrue($source !== false, 'Additive job-contract migration is missing');
    foreach (['contract_term', 'pay_grade', 'advertised_weekly_hours', 'full_time_weekly_hours', 'vacation_days', 'work_location'] as $column) {
        assertTrue(str_contains($source, "'{$column}' =>"), "Job contract definition missing: {$column}");
    }
    assertTrue(str_contains($source, 'hasColumn($column)'), 'Partially applied job upgrades are not guarded.');
    assertTrue(str_contains($source, 'addColumn($column'), 'Configured job contract columns are not added.');
    assertTrue(!str_contains($source, 'flz_recruitment_hiring_data'), 'Existing applicant hiring records must not be rewritten.');
});

TestRunner::test('name suggestion backfill updates only messages without an existing name', static function (): void {
    $source = file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000015Date202608150007.php');
    assertTrue($source !== false, 'Name-suggestion backfill migration is missing');
    assertTrue(str_contains($source, "['name']['value']"), 'Existing name suggestions are not protected.');
    assertTrue(str_contains($source, 'ApplicationMailFieldExtractor'), 'Backfill does not reuse the canonical extractor.');
    assertTrue(str_contains($source, "set('field_suggestions_json'"), 'Derived names are not persisted.');
    assertTrue(str_contains($source, "set('version'"), 'Backfilled messages do not invalidate stale clients.');
});

TestRunner::test('questionnaire-skip upgrade adds only missing disabled mail rules and reuses templates', static function (): void {
    $source = file_get_contents(dirname(__DIR__) . '/lib/Migration/Version000016Date202608150008.php');
    assertTrue($source !== false, 'Questionnaire-skip mail-rule migration is missing.');
    foreach ([
        "['screening', 'live_planned']",
        "['screening', 'decision_pending']",
        "['questionnaire_pending', 'phone_planned']",
        "['questionnaire_pending', 'live_planned']",
        "['questionnaire_pending', 'decision_pending']",
        "['questionnaire_pending', 'rejected']",
    ] as $edge) assertTrue(str_contains($source, $edge), "Missing questionnaire-skip edge {$edge}.");
    assertTrue(str_contains($source, "from('flz_recruitment_status_mail_rules')"), 'Existing transition rules are not checked.');
    assertTrue(str_contains($source, "select('template_id')"), 'Reusable target templates are not selected.');
    assertTrue(str_contains($source, "createNamedParameter(false, IQueryBuilder::PARAM_BOOL)"), 'New skip rules are not disabled by default.');
    assertTrue(!str_contains($source, "insert('flz_recruitment_mail_templates')"), 'The migration duplicates reusable templates.');
});
