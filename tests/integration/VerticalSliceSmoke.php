<?php

declare(strict_types=1);

if (!defined('OC_CONSOLE')) define('OC_CONSOLE', true);
require dirname(__DIR__, 4) . '/lib/base.php';

use OCA\Recruitment\Repository\RecruitmentRepository;
use OCA\Recruitment\Controller\ApiController;
use OCA\Recruitment\Controller\PageController;
use OCA\Recruitment\Service\ApplicationStatusService;
use OCA\Recruitment\Service\BasisQualificationService;
use OCA\Recruitment\Service\InterviewService;
use OCA\Recruitment\Service\RecruitmentService;
use OCA\Recruitment\Service\TemplateService;
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
$adrecruitment = \OCP\Server::get(RecruitmentService::class);
$templates = \OCP\Server::get(TemplateService::class);
$interviews = \OCP\Server::get(InterviewService::class);
$statuses = \OCP\Server::get(ApplicationStatusService::class);
$basisQualifications = \OCP\Server::get(BasisQualificationService::class);
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

    $ids['job'] = $adrecruitment->createJob(
        $repository,
        'Synthetische Teststelle',
        '',
        true,
        [],
        [],
        'adrecruitment-smoke-' . $suffix,
        true,
        'assistance',
    );
    $ids['person'] = $adrecruitment->createPerson(
        $repository,
        'Alex',
        'Beispiel',
        "alex-{$suffix}@example.invalid",
        '',
    );
    $ids['application'] = $adrecruitment->createApplication(
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

    echo "AD Recruitment DDEV vertical slice: OK\n";
} finally {
    if ($ids['message'] !== null) {
        $delete('rec_message_audit', 'message_id', $ids['message']);
        $delete('rec_messages', 'id', $ids['message']);
    }
    if ($ids['application'] !== null) {
        $delete('rec_status_log', 'application_id', $ids['application']);
        $delete('rec_bq_assignments', 'application_id', $ids['application']);
        $delete('rec_interviews', 'application_id', $ids['application']);
        $delete('rec_applications', 'id', $ids['application']);
    }
    if ($ids['question'] !== null) {
        $delete('rec_bubbles', 'question_id', $ids['question']);
        $delete('rec_questions', 'id', $ids['question']);
    }
    if ($ids['template'] !== null) {
        $delete('rec_templates', 'id', $ids['template']);
    }
    if ($ids['person'] !== null) {
        $delete('rec_people', 'id', $ids['person']);
    }
    if ($ids['job'] !== null) {
        $delete('rec_jobs', 'id', $ids['job']);
    }
    if ($ids['basisQualificationRun'] !== null) {
        $delete('rec_bq_runs', 'id', $ids['basisQualificationRun']);
    }
    if ($ids['mailbox'] !== null) {
        $delete('rec_mailboxes', 'id', $ids['mailbox']);
    }
}
