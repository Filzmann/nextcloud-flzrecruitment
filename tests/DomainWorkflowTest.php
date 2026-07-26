<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/Exception/ConflictException.php';
require_once dirname(__DIR__) . '/lib/Exception/ValidationException.php';
require_once dirname(__DIR__) . '/lib/Service/ApplicationStatusService.php';
require_once dirname(__DIR__) . '/lib/Service/InterviewWorkflow.php';

use OCA\Recruitment\Exception\ConflictException;
use OCA\Recruitment\Exception\ValidationException;
use OCA\Recruitment\Service\ApplicationStatusService;
use OCA\Recruitment\Service\InterviewWorkflow;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;

TestRunner::test('controlled application status transitions accept only declared edges', static function (): void {
    $workflow = new ApplicationStatusService();

    assertSame('screening', $workflow->targetStatus('received', 'screening'));
    assertThrows(
        static fn () => $workflow->targetStatus('received', 'hired'),
        ValidationException::class,
    );
});

TestRunner::test('template snapshots remain independent from later template edits', static function (): void {
    $workflow = new InterviewWorkflow();
    $template = [
        'id' => 8,
        'revision' => 3,
        'questions' => [[
            'id' => 11,
            'text' => 'Welche Rahmenbedingungen sind wichtig?',
            'type' => 'textarea',
            'required' => true,
            'bubbles' => [['label' => 'flexibel', 'insertText' => 'Zeitlich flexibel.']],
        ]],
    ];

    $snapshot = $workflow->snapshot($template);
    $template['questions'][0]['text'] = 'Geänderter Text';
    $template['questions'][0]['bubbles'][0]['insertText'] = 'Geändert.';

    assertSame('Welche Rahmenbedingungen sind wichtig?', $snapshot['questions'][0]['text']);
    assertSame('Zeitlich flexibel.', $snapshot['questions'][0]['bubbles'][0]['insertText']);
});

TestRunner::test('required questions prevent completion', static function (): void {
    $workflow = new InterviewWorkflow();
    $snapshot = [
        'questions' => [
            ['id' => 11, 'type' => 'textarea', 'required' => true],
            ['id' => 12, 'type' => 'boolean', 'required' => false],
        ],
    ];

    assertThrows(
        static fn () => $workflow->complete($snapshot, ['11' => '']),
        ValidationException::class,
    );
    assertSame('completed', $workflow->complete($snapshot, ['11' => 'Eine neutrale Antwort.']));
});

TestRunner::test('completed interviews and stale versions cannot be overwritten', static function (): void {
    $workflow = new InterviewWorkflow();

    assertThrows(
        static fn () => $workflow->assertDraftWritable('completed', 4, 4),
        ConflictException::class,
    );
    assertThrows(
        static fn () => $workflow->assertDraftWritable('in_progress', 4, 3),
        ConflictException::class,
    );
    assertSame('in_progress', $workflow->assertDraftWritable('not_started', 4, 4));
});
