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
