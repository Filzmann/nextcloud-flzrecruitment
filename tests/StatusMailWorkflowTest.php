<?php

declare(strict_types=1);

use OCA\Recruitment\Exception\ValidationException;
use OCA\Recruitment\Service\StatusMailWorkflow;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;

TestRunner::test('status mail renders an editable draft for the canonical applicant address', static function (): void {
    $workflow = new StatusMailWorkflow();
    $draft = $workflow->renderDraft([
        'id' => 7,
        'revision' => 3,
        'subject' => 'Ihre Bewerbung als {{job_title}}',
        'body' => "Guten Tag {{given_name}} {{family_name}},\nwir haben Neuigkeiten.",
    ], [
        'given_name' => 'Ari',
        'family_name' => 'Beispiel',
        'job_title' => 'Assistenz',
    ], 'ari@example.invalid');

    assertSame('draft', $draft['status']);
    assertSame('ari@example.invalid', $draft['originalRecipient']);
    assertSame('Ihre Bewerbung als Assistenz', $draft['subject']);
    assertSame('<p>Guten Tag Ari Beispiel,<br>wir haben Neuigkeiten.</p>', $draft['body']);
    assertSame('html', $draft['bodyFormat']);
    assertSame(3, $draft['templateRevision']);
});

TestRunner::test('approved status mail can be scheduled for next Monday and redirected in test mode', static function (): void {
    $workflow = new StatusMailWorkflow();
    $draft = [
        'status' => 'draft',
        'originalRecipient' => 'ari@example.invalid',
        'subject' => 'Zwischenstand',
        'body' => 'Bearbeitbarer Text',
        'templateId' => 7,
        'templateRevision' => 3,
    ];
    $now = new DateTimeImmutable('2026-08-15 14:30:00', new DateTimeZone('Europe/Berlin'));

    $approved = $workflow->approveDraft(
        $draft,
        'Geänderter Betreff',
        'Geänderter Text kurz vor Versand.',
        'ari.neu@example.invalid',
        StatusMailWorkflow::TIMING_NEXT_MONDAY,
        null,
        $now,
        'mailtest@example.invalid',
    );

    assertSame('approved', $approved['status']);
    assertSame('ari@example.invalid', $approved['originalRecipient']);
    assertSame('ari.neu@example.invalid', $approved['intendedRecipient']);
    assertSame('mailtest@example.invalid', $approved['deliveryRecipient']);
    assertSame(true, $approved['testMode']);
    assertSame('2026-08-17T09:00:00+02:00', $approved['scheduledAt']);
    assertSame('Geänderter Betreff', $approved['subject']);
    assertSame('<p>Geänderter Text kurz vor Versand.</p>', $approved['body']);
    assertSame('html', $approved['bodyFormat']);
});

TestRunner::test('status mail rejects unknown placeholders, past schedules and invalid test routing', static function (): void {
    $workflow = new StatusMailWorkflow();
    assertThrows(
        static fn () => $workflow->renderDraft([
            'id' => 1, 'revision' => 1, 'subject' => '{{unknown}}', 'body' => 'Text',
        ], [], 'ari@example.invalid'),
        ValidationException::class,
    );

    $draft = [
        'status' => 'draft', 'originalRecipient' => 'ari@example.invalid',
        'subject' => 'Betreff', 'body' => 'Text', 'templateId' => 1, 'templateRevision' => 1,
    ];
    $now = new DateTimeImmutable('2026-08-15 14:30:00', new DateTimeZone('Europe/Berlin'));
    assertThrows(
        static fn () => $workflow->approveDraft(
            $draft, 'Betreff', 'Text', 'ari@example.invalid', StatusMailWorkflow::TIMING_SCHEDULED,
            new DateTimeImmutable('2026-08-15 14:29:59', new DateTimeZone('Europe/Berlin')),
            $now,
        ),
        ValidationException::class,
    );
    assertThrows(
        static fn () => $workflow->approveDraft(
            $draft, 'Betreff', 'Text', 'ari@example.invalid', StatusMailWorkflow::TIMING_IMMEDIATE, null, $now, 'ungültig',
        ),
        ValidationException::class,
    );
    assertThrows(
        static fn () => $workflow->approveDraft(
            $draft, 'Betreff', 'Text', 'ungültig', StatusMailWorkflow::TIMING_IMMEDIATE, null, $now,
        ),
        ValidationException::class,
    );
});

TestRunner::test('HTML template placeholders cannot inject applicant-controlled markup', static function (): void {
    $workflow = new StatusMailWorkflow();
    $draft = $workflow->renderDraft([
        'id' => 1,
        'revision' => 1,
        'subject' => 'Zwischenstand {{given_name}}',
        'body' => '<p>Guten Tag {{given_name}} {{family_name}}</p>',
        'bodyFormat' => 'html',
    ], [
        'given_name' => '<strong onclick="evil()">Ari</strong>',
        'family_name' => '<a href="https://evil.invalid">Beispiel</a>',
        'job_title' => 'Stelle',
    ], 'ari@example.invalid');

    assertSame('Zwischenstand <strong onclick="evil()">Ari</strong>', $draft['subject']);
    assertSame('<p>Guten Tag &lt;strong onclick="evil()"&gt;Ari&lt;/strong&gt; &lt;a href="https://evil.invalid"&gt;Beispiel&lt;/a&gt;</p>', $draft['body']);
});

TestRunner::test('missing canonical address does not block the draft but requires a valid final recipient', static function (): void {
    $workflow = new StatusMailWorkflow();
    $draft = $workflow->renderDraft(
        ['id' => 1, 'revision' => 1, 'subject' => 'Zwischenstand', 'body' => 'Text'],
        ['given_name' => 'Ari', 'family_name' => 'Beispiel', 'job_title' => 'Stelle'],
        '',
    );
    assertSame('', $draft['originalRecipient']);
    $approved = $workflow->approveDraft(
        $draft, 'Zwischenstand', 'Text', 'ari@example.invalid', StatusMailWorkflow::TIMING_IMMEDIATE,
        null, new DateTimeImmutable('2026-08-15 14:30:00', new DateTimeZone('Europe/Berlin')),
    );
    assertSame('ari@example.invalid', $approved['intendedRecipient']);
});
