<?php

declare(strict_types=1);

if (!defined('OC_CONSOLE')) define('OC_CONSOLE', true);
require dirname(__DIR__, 4) . '/lib/base.php';

use OCA\Recruitment\Repository\RecruitmentRepository;
use OCA\Recruitment\Service\ApplicationStatusService;
use OCA\Recruitment\Service\RecruitmentService;
use OCA\Recruitment\Service\StatusMailService;
use OCA\Recruitment\Service\StatusMailWorkflow;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Reale, versandfreie Persistenzprüfung für Statusmail-Regel, Snapshot, Freigabe und Outbox. */

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$db = \OCP\Server::get(IDBConnection::class);
$repository = \OCP\Server::get(RecruitmentRepository::class);
$recruitment = \OCP\Server::get(RecruitmentService::class);
$statuses = \OCP\Server::get(ApplicationStatusService::class);
$mail = \OCP\Server::get(StatusMailService::class);
$suffix = bin2hex(random_bytes(5));
$ids = ['job' => null, 'person' => null, 'application' => null, 'template' => null, 'draft' => null];
$ruleRestore = null;

$delete = static function (string $table, string $column, int $id) use ($db): void {
    $qb = $db->getQueryBuilder();
    $qb->delete($table)->where($qb->expr()->eq($column, $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))->executeStatement();
};

try {
    $ids['job'] = $recruitment->createJob($repository, 'Statusmail-Teststelle', '', true, [], [], 'mail-smoke-' . $suffix, false, 'other');
    $ids['person'] = $recruitment->createPerson($repository, 'Ari', 'Beispiel', "ari-{$suffix}@example.invalid", '');
    $ids['application'] = $recruitment->createApplication($repository, $ids['person'], $ids['job'], 'manual', '2026-08-15', 'admin');
    $template = $mail->createTemplate(
        'Statusmail-Smoke ' . $suffix,
        'Zwischenstand {{job_title}}',
        '<p>Guten Tag {{given_name}} {{family_name}}.</p>',
        'html',
        'admin',
    );
    $ids['template'] = (int)$template['id'];
    $ruleRestore = array_values(array_filter(
        $mail->configuration()['rules'],
        static fn(array $item): bool => $item['fromStatus'] === 'received' && $item['toStatus'] === 'screening',
    ))[0] ?? null;
    $assert(is_array($ruleRestore), 'Die vorbereitete Regel Eingegangen → Vorprüfung fehlt.');
    $mail->configureRule(
        'received', 'screening', $ids['template'], true, StatusMailWorkflow::TIMING_SCHEDULED,
        (int)$ruleRestore['version'], 'admin',
    );

    $changed = $statuses->transition($repository, $ids['application'], 'screening', 1, 'admin', '', [], 'mail-smoke-' . $suffix);
    $draft = $changed['mailDraft'] ?? null;
    $assert(is_array($draft), 'Der konfigurierte Statuswechsel hat keinen Mailentwurf erzeugt.');
    $ids['draft'] = (int)$draft['id'];
    $assert($draft['subject'] === 'Zwischenstand Statusmail-Teststelle', 'Der Vorlagen-Snapshot wurde nicht vollständig aufgelöst.');

    $scheduledAt = new DateTimeImmutable('+1 day', new DateTimeZone('Europe/Berlin'));
    $approved = $mail->approveDraft(
        $ids['draft'],
        'Geprüfter Zwischenstand',
        '<p>Geprüfter synthetischer Nachrichtentext.</p>',
        'html',
        "ari-neu-{$suffix}@example.invalid",
        StatusMailWorkflow::TIMING_SCHEDULED,
        $scheduledAt->format(DATE_ATOM),
        (int)$draft['version'],
        'admin',
        'mail-job-' . $suffix,
    );
    $assert($approved['status'] === 'approved', 'Die Statusmail-Freigabe wurde nicht persistiert.');
    $assert($approved['originalRecipient'] !== $approved['intendedRecipient'], 'Die Empfängerkorrektur verdeckt den ursprünglichen Snapshot.');
    $assert($approved['deliveryRecipient'] === $approved['intendedRecipient'], 'Ohne Testmodus weicht der Zustellempfänger von der Freigabe ab.');

    $qb = $db->getQueryBuilder();
    $outboxCount = (int)$qb->select($qb->createFunction('COUNT(*)'))->from('rec_mail_outbox')
        ->where($qb->expr()->eq('draft_id', $qb->createNamedParameter($ids['draft'], IQueryBuilder::PARAM_INT)))
        ->executeQuery()->fetchOne();
    $assert($outboxCount === 1, 'Die Freigabe hat nicht genau einen Outbox-Auftrag erzeugt.');

    echo "AD Recruitment DDEV status mail persistence smoke: OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . "\n");
    throw $error;
} finally {
    if ($ids['draft'] !== null) {
        $delete('rec_mail_outbox', 'draft_id', $ids['draft']);
        $delete('rec_mail_drafts', 'id', $ids['draft']);
    }
    if (is_array($ruleRestore)) {
        $current = array_values(array_filter(
            $mail->configuration()['rules'],
            static fn(array $item): bool => $item['fromStatus'] === 'received' && $item['toStatus'] === 'screening',
        ))[0] ?? null;
        if (is_array($current)) {
            $mail->configureRule(
                'received', 'screening', (int)$ruleRestore['templateId'], (bool)$ruleRestore['enabled'],
                (string)$ruleRestore['defaultTiming'], (int)$current['version'], 'system',
            );
        }
    }
    if ($ids['application'] !== null) {
        $delete('rec_status_log', 'application_id', $ids['application']);
        $delete('rec_applications', 'id', $ids['application']);
    }
    if ($ids['template'] !== null) {
        $delete('rec_mail_template_revisions', 'template_id', $ids['template']);
        $delete('rec_mail_templates', 'id', $ids['template']);
    }
    if ($ids['person'] !== null) $delete('rec_people', 'id', $ids['person']);
    if ($ids['job'] !== null) $delete('rec_jobs', 'id', $ids['job']);
}
