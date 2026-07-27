<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/lib/base.php';

use OCA\Recruitment\Repository\RecruitmentRepository;
use OCA\Recruitment\Controller\ApiController;
use OCA\Recruitment\Controller\PageController;
use OCA\Recruitment\Service\ApplicationStatusService;
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
    );
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
    $changed = $statuses->transition(
        $repository,
        $ids['application'],
        'screening',
        1,
        'admin',
    );
    $detail = $repository->applicationDetail($ids['application']);

    $assert($completed['status'] === 'completed', 'Interviewabschluss wurde nicht persistiert.');
    $assert($changed['status'] === 'screening', 'Statusübergang wurde nicht persistiert.');
    $assert(count($detail['statusHistory']) === 1, 'Statusprotokoll fehlt.');
    $assert(count($detail['interviews']) === 1, 'Interview fehlt in der Bewerbungsakte.');
    $assert(
        $detail['interviews'][0]['snapshot']['questions'][0]['bubbles'][0]['insertText']
            === 'Eine neutrale Beobachtung wurde dokumentiert.',
        'Vorlagen-Snapshot enthält die Antwort-Bubble nicht.',
    );

    echo "AD Recruitment DDEV vertical slice: OK\n";
} finally {
    if ($ids['application'] !== null) {
        $delete('rec_status_log', 'application_id', $ids['application']);
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
}
