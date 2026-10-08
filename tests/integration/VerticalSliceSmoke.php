<?php

declare(strict_types=1);

if (!defined('OC_CONSOLE')) define('OC_CONSOLE', true);
require dirname(__DIR__, 4) . '/lib/base.php';

use OCA\FlzRecruitment\Repository\RecruitmentRepository;
use OCA\FlzRecruitment\Controller\ApiController;
use OCA\FlzRecruitment\Controller\PageController;
use OCA\FlzRecruitment\Exception\ConflictException;
use OCA\FlzRecruitment\Service\ApplicationStatusService;
use OCA\FlzRecruitment\Service\BasisQualificationService;
use OCA\FlzRecruitment\Service\InterviewService;
use OCA\FlzRecruitment\Service\MailInboxService;
use OCA\FlzRecruitment\Service\RecruitmentService;
use OCA\FlzRecruitment\Service\TemplateService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Reale DDEV-Persistenzprüfung des vertikalen Durchstichs mit vollständiger Bereinigung.
 */

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$db = \OCP\Server::get(IDBConnection::class);
$repository = \OCP\Server::get(RecruitmentRepository::class);
$flzrecruitment = \OCP\Server::get(RecruitmentService::class);
$templates = \OCP\Server::get(TemplateService::class);
$interviews = \OCP\Server::get(InterviewService::class);
$statuses = \OCP\Server::get(ApplicationStatusService::class);
$basisQualifications = \OCP\Server::get(BasisQualificationService::class);
$inbox = \OCP\Server::get(MailInboxService::class);
$apiController = \OCP\Server::get(ApiController::class);
$pageController = \OCP\Server::get(PageController::class);
$suffix = bin2hex(random_bytes(5));
$ids = [
    'job' => null,
    'person' => null,
    'application' => null,
    'template' => null,
    'question' => null,
    'bubble' => null,
    'interview' => null,
    'basisQualificationRun' => null,
    'basisQualificationAssignment' => null,
    'mailbox' => null,
    'message' => null,
    'createdMessage' => null,
    'createdPerson' => null,
    'createdApplication' => null,
];

$delete = static function (string $table, string $column, int $id) use ($db): void {
    $qb = $db->getQueryBuilder();
    $qb
        ->delete($table)
        ->where($qb->expr()->eq($column, $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
        ->executeStatement();
};

try {
    $assert($apiController->bootstrap()->getStatus() === 403, 'Anonyme API-Nutzung wurde nicht serverseitig abgewiesen.');
    $assert($pageController->index()->getStatus() === 403, 'Anonymer Seitenzugriff wurde nicht serverseitig abgewiesen.');
    $peopleBeforeDeniedWrite = count($repository->overview()['people']);
    $assert(
        $apiController->createPerson('Nicht', 'Gespeichert', 'denied@example.invalid', '')->getStatus() === 403,
        'Anonymer Schreibzugriff wurde nicht serverseitig abgewiesen.',
    );
    $assert(
        count($repository->overview()['people']) === $peopleBeforeDeniedWrite,
        'Ein abgewiesener Schreibzugriff hat Daten verändert.',
    );

    $ids['job'] = $flzrecruitment->createJob(
        $repository,
        'Synthetische Teststelle',
        '',
        true,
        [],
        [],
        'flzrecruitment-smoke-' . $suffix,
        true,
        'assistance',
    );
    $ids['person'] = $flzrecruitment->createPerson(
        $repository,
        'Alex',
        'Beispiel',
        "alex-{$suffix}@example.invalid",
        '',
    );
    $ids['application'] = $flzrecruitment->createApplication(
        $repository,
        $ids['person'],
        $ids['job'],
        'manual',
        '2026-07-26',
        'admin',
        20.0,
        30.0,
    );
    $repository->saveHiringData($ids['application'], ['city' => 'Potsdam'], 0, 'admin');
    $ids['mailbox'] = $repository->ensureMailbox([
        'technicalKey' => 'smoke-' . $suffix,
        'label' => 'Synthetischer Smoke-Eingang',
        'address' => "smoke-{$suffix}@example.invalid",
    ]);
    $ids['message'] = $repository->createInboxMessage([
        'mailboxId' => $ids['mailbox'], 'externalMessageId' => "<smoke-{$suffix}@example.invalid>",
        'contentHash' => hash('sha256', $suffix), 'state' => 'new',
        'senderAddress' => "alex-{$suffix}@example.invalid", 'recipients' => ['bewerbung@example.invalid'],
        'subject' => 'Synthetische Bewerbung', 'receivedAt' => new DateTimeImmutable('2026-08-15T09:00:00+02:00'),
        'bodyText' => 'Synthetischer Inhalt', 'fieldSuggestions' => [], 'actorUid' => 'admin',
    ]);
    $repository->assignInboxMessage($ids['message'], $ids['application'], 1, 'admin', [
        'city' => 'Berlin', 'privateEmail' => "vertrag-{$suffix}@example.invalid",
    ]);
    $prefilledHiring = $repository->hiringData($ids['application']);
    $assert($prefilledHiring['data']['city'] === 'Potsdam', 'Die Mailzuordnung überschreibt vorhandene Vertragsdaten.');
    $assert($prefilledHiring['data']['privateEmail'] === "vertrag-{$suffix}@example.invalid", 'Die Mailzuordnung befüllt ein leeres Vertragsfeld nicht.');
    $ids['createdMessage'] = $repository->createInboxMessage([
        'mailboxId' => $ids['mailbox'], 'externalMessageId' => "<create-{$suffix}@example.invalid>",
        'contentHash' => hash('sha256', 'create-' . $suffix), 'state' => 'new',
        'senderAddress' => "neu-{$suffix}@example.invalid", 'recipients' => ['bewerbung@example.invalid'],
        'subject' => 'Synthetische Neuanlage', 'receivedAt' => new DateTimeImmutable('2026-08-16T10:00:00+02:00'),
        'bodyText' => 'Synthetischer Inhalt zur Neuanlage',
        'fieldSuggestions' => ['email' => ['value' => "neu-{$suffix}@example.invalid"]],
        'actorUid' => 'admin',
    ]);
    $created = $inbox->createAndAssignApplication(
        $ids['createdMessage'],
        1,
        $ids['job'],
        'Robin',
        'Muster',
        "neu-{$suffix}@example.invalid",
        '',
        'admin',
        ['email' => "neu-{$suffix}@example.invalid"],
        'admin',
    );
    $ids['createdPerson'] = $created['personId'];
    $ids['createdApplication'] = $created['applicationId'];
    $createdDetail = $repository->applicationDetail($ids['createdApplication']);
    $assert($created['message']['state'] === 'assigned', 'Die Eingangsnachricht wurde bei der Neuanlage nicht atomar zugeordnet.');
    $assert($createdDetail['application']['source'] === 'email_import', 'Die aus dem Eingang erzeugte Bewerbung hat nicht die kanonische Quelle.');
    $assert($createdDetail['application']['receivedOn'] === '2026-08-16', 'Das Empfangsdatum wurde nicht als Bewerbungsdatum übernommen.');
    $assert(
        $repository->hiringData($ids['createdApplication'])['data']['privateEmail'] === "neu-{$suffix}@example.invalid",
        'Ein bestätigter Vorschlag wurde bei der atomaren Neuanlage nicht übernommen.',
    );
    $overviewBeforeRetry = $repository->overview();
    $retryConflict = false;
    try {
        $repository->createAndAssignInboxApplication(
            $ids['createdMessage'],
            1,
            'admin',
            $flzrecruitment->personData('Robin', 'Muster', "neu-{$suffix}@example.invalid", ''),
            $flzrecruitment->applicationData($ids['job'], 'email_import', '2026-08-16', 'admin'),
        );
    } catch (ConflictException) {
        $retryConflict = true;
    }
    $overviewAfterRetry = $repository->overview();
    $assert($retryConflict, 'Ein veralteter Neuanlageversuch wurde nicht als Konflikt abgewiesen.');
    $assert(count($overviewAfterRetry['people']) === count($overviewBeforeRetry['people']), 'Der Konflikt hat eine zweite Person hinterlassen.');
    $assert(count($overviewAfterRetry['applications']) === count($overviewBeforeRetry['applications']), 'Der Konflikt hat eine zweite Bewerbung hinterlassen.');
    $ids['template'] = $templates->create(
        $repository,
        'Synthetisches Interview',
        'phone',
        '',
        'interviewer',
    );
    $ids['question'] = $templates->addQuestion(
        $repository,
        $ids['template'],
        'Welche neutrale Beobachtung wurde gemacht?',
        '',
        'textarea',
        true,
        10,
        [],
        'internal',
    );
    $ids['bubble'] = $templates->addBubble(
        $repository,
        $ids['question'],
        'neutral',
        'Eine neutrale Beobachtung wurde dokumentiert.',
        10,
        true,
    );
    $ids['interview'] = $interviews->instantiate(
        $repository,
        $ids['application'],
        $ids['template'],
        'admin',
    );
    $draft = $interviews->saveDraft(
        $repository,
        $ids['interview'],
        [(string)$ids['question'] => 'Eine neutrale Beobachtung wurde dokumentiert.'],
        1,
    );
    $completed = $interviews->complete(
        $repository,
        $ids['interview'],
        $draft['answers'],
        2,
    );
    $changed = null;
    $version = 1;
    foreach (['screening', 'phone_planned', 'phone_completed', 'decision_pending'] as $targetStatus) {
        $changed = $statuses->transition(
            $repository,
            $ids['application'],
            $targetStatus,
            $version,
            'admin',
        );
        $version = (int)$changed['version'];
    }
    $ids['basisQualificationRun'] = $basisQualifications->createRun(
        $repository,
        '2026-09-07',
        '2026-09-18',
        'admin',
    );
    $assignment = $basisQualifications->assign(
        $repository,
        $ids['application'],
        $ids['basisQualificationRun'],
        $version,
        'admin',
    );
    $ids['basisQualificationAssignment'] = (int)$assignment['id'];
    $evaluated = $basisQualifications->recordResult(
        $repository,
        $ids['basisQualificationAssignment'],
        'suitable',
        'Synthetisches geeignetes Ergebnis.',
        1,
        'admin',
    );
    $detail = $repository->applicationDetail($ids['application']);

    $assert($completed['status'] === 'completed', 'Interviewabschluss wurde nicht persistiert.');
    $assert($changed['status'] === 'decision_pending', 'Statusübergänge bis zur BQ-Entscheidung wurden nicht persistiert.');
    $assert($evaluated['result'] === 'suitable', 'BQ-Ergebnis wurde nicht persistiert.');
    $assert($detail['application']['status'] === 'basis_qualification', 'BQ-Zuordnung hat den Bewerbungsstatus nicht atomar geändert.');
    $assert($detail['job']['professionCategory'] === 'assistance', 'Die Berufsgruppe der Assistenz-Stelle wurde nicht persistiert.');
    $assert($detail['application']['desiredWeeklyHours'] === 20.0, 'Die ungefähren Wunschwochenstunden wurden nicht persistiert.');
    $assert($detail['application']['desiredWeeklyHoursMax'] === 30.0, 'Die Obergrenze der ungefähren Wunschwochenstunden wurde nicht persistiert.');
    $assert($detail['application']['basisQualification']['label'] === 'BQ 09/26', 'Sichtbare BQ-Bezeichnung fehlt.');
    $assert(!array_key_exists('result', $detail['application']['basisQualification']), 'BQ-Bewertung ist in die normale Bewerbungsansicht gelangt.');
    $assert(count($detail['statusHistory']) === 5, 'BQ-Statusprotokoll ist unvollständig.');
    $assert(count($detail['interviews']) === 1, 'Interview fehlt in der Bewerbungsakte.');
    $assert(
        $detail['interviews'][0]['snapshot']['questions'][0]['bubbles'][0]['insertText']
            === 'Eine neutrale Beobachtung wurde dokumentiert.',
        'Vorlagen-Snapshot enthält die Antwort-Bubble nicht.',
    );

    echo "Filzmann Recruitment DDEV vertical slice: OK\n";
} finally {
    if ($ids['createdMessage'] !== null) {
        $delete('flz_recruitment_message_audit', 'message_id', $ids['createdMessage']);
        $delete('flz_recruitment_messages', 'id', $ids['createdMessage']);
    }
    if ($ids['message'] !== null) {
        $delete('flz_recruitment_message_audit', 'message_id', $ids['message']);
        $delete('flz_recruitment_messages', 'id', $ids['message']);
    }
    if ($ids['createdApplication'] !== null) {
        $delete('flz_recruitment_hiring_data', 'application_id', $ids['createdApplication']);
        $delete('flz_recruitment_applications', 'id', $ids['createdApplication']);
    }
    if ($ids['application'] !== null) {
        $delete('flz_recruitment_status_log', 'application_id', $ids['application']);
        $delete('flz_recruitment_bq_assignments', 'application_id', $ids['application']);
        $delete('flz_recruitment_interviews', 'application_id', $ids['application']);
        $delete('flz_recruitment_applications', 'id', $ids['application']);
    }
    if ($ids['question'] !== null) {
        $delete('flz_recruitment_bubbles', 'question_id', $ids['question']);
        $delete('flz_recruitment_questions', 'id', $ids['question']);
    }
    if ($ids['template'] !== null) {
        $delete('flz_recruitment_templates', 'id', $ids['template']);
    }
    if ($ids['person'] !== null) {
        $delete('flz_recruitment_people', 'id', $ids['person']);
    }
    if ($ids['createdPerson'] !== null) {
        $delete('flz_recruitment_people', 'id', $ids['createdPerson']);
    }
    if ($ids['job'] !== null) {
        $delete('flz_recruitment_jobs', 'id', $ids['job']);
    }
    if ($ids['basisQualificationRun'] !== null) {
        $delete('flz_recruitment_bq_runs', 'id', $ids['basisQualificationRun']);
    }
    if ($ids['mailbox'] !== null) {
        $delete('flz_recruitment_mailboxes', 'id', $ids['mailbox']);
    }
}
