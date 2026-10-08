<?php

declare(strict_types=1);

use OCA\FlzRecruitment\Exception\ConflictException;
use OCA\FlzRecruitment\Exception\ValidationException;
use OCA\FlzRecruitment\Service\ApplicationStatusService;
use OCA\FlzRecruitment\Service\InterviewWorkflow;
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

TestRunner::test('questionnaire can be skipped normally while other shortcuts require an explicit override', static function (): void {
    $workflow = new ApplicationStatusService();
    assertSame('phone_planned', $workflow->targetStatus('screening', 'phone_planned'));
    assertSame('live_planned', $workflow->targetStatus('screening', 'live_planned'));
    assertSame('decision_pending', $workflow->targetStatus('questionnaire_pending', 'decision_pending'));
    assertThrows(static fn () => $workflow->targetStatus('received', 'decision_pending'), ValidationException::class);
    assertSame('decision_pending', $workflow->targetStatus('received', 'decision_pending', true));
    assertThrows(static fn () => $workflow->targetStatus('received', 'hired', true), ValidationException::class);
    assertThrows(static fn () => $workflow->targetStatus('received', 'invented', true), ValidationException::class);
    assertThrows(static fn () => $workflow->targetStatus('received', 'received', true), ValidationException::class);
});

TestRunner::test('approval for hire requires a configured area and enables first-guide access atomically', static function (): void {
    $workflow = new ApplicationStatusService();
    assertSame('approved_for_hire', $workflow->targetStatus('decision_pending', 'approved_for_hire'));
    assertThrows(
        static fn () => $workflow->approvalArea('approved_for_hire', '', ['west', 'south']),
        ValidationException::class,
    );
    assertThrows(
        static fn () => $workflow->approvalArea('approved_for_hire', 'unknown', ['west', 'south']),
        ValidationException::class,
    );
    assertSame('west', $workflow->approvalArea('approved_for_hire', 'west', ['west', 'south']));
    assertSame(null, $workflow->approvalArea('screening', '', ['west', 'south']));
    assertThrows(
        static fn () => $workflow->targetStatus('accepted', 'hired'),
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
