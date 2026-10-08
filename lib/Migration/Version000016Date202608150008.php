<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Migration;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Ergänzt nur die neuen regulären Kurzfragebogen-Abkürzungen um deaktivierte Mailregeln. */
final class Version000016Date202608150008 extends SimpleMigrationStep {
    private const EDGES = [
        ['screening', 'live_planned'],
        ['screening', 'decision_pending'],
        ['questionnaire_pending', 'phone_planned'],
        ['questionnaire_pending', 'live_planned'],
        ['questionnaire_pending', 'decision_pending'],
        ['questionnaire_pending', 'rejected'],
    ];

    public function __construct(private IDBConnection $db) {}

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        $this->db->beginTransaction();
        try {
            foreach (self::EDGES as [$fromStatus, $toStatus]) {
                if ($this->ruleExists($fromStatus, $toStatus)) continue;
                $templateId = $this->templateIdForTarget($toStatus);
                if ($templateId === null) {
                    throw new \RuntimeException('Für eine neue Statuskante fehlt eine wiederverwendbare Mailvorlage.');
                }
                $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
                $insert = $this->db->getQueryBuilder();
                $insert->insert('flz_recruitment_status_mail_rules')
                    ->setValue('from_status', $insert->createNamedParameter($fromStatus, IQueryBuilder::PARAM_STR))
                    ->setValue('to_status', $insert->createNamedParameter($toStatus, IQueryBuilder::PARAM_STR))
                    ->setValue('template_id', $insert->createNamedParameter($templateId, IQueryBuilder::PARAM_INT))
                    ->setValue('enabled', $insert->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
                    ->setValue('default_timing', $insert->createNamedParameter('immediate', IQueryBuilder::PARAM_STR))
                    ->setValue('version', $insert->createNamedParameter(1, IQueryBuilder::PARAM_INT))
                    ->setValue('actor_uid', $insert->createNamedParameter('system', IQueryBuilder::PARAM_STR))
                    ->setValue('created_at', $insert->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                    ->setValue('updated_at', $insert->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                    ->executeStatement();
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    private function ruleExists(string $fromStatus, string $toStatus): bool {
        $query = $this->db->getQueryBuilder();
        return $query->select('id')->from('flz_recruitment_status_mail_rules')
            ->where($query->expr()->eq('from_status', $query->createNamedParameter($fromStatus, IQueryBuilder::PARAM_STR)))
            ->andWhere($query->expr()->eq('to_status', $query->createNamedParameter($toStatus, IQueryBuilder::PARAM_STR)))
            ->setMaxResults(1)->executeQuery()->fetchOne() !== false;
    }

    private function templateIdForTarget(string $toStatus): ?int {
        $query = $this->db->getQueryBuilder();
        $templateId = $query->select('template_id')->from('flz_recruitment_status_mail_rules')
            ->where($query->expr()->eq('to_status', $query->createNamedParameter($toStatus, IQueryBuilder::PARAM_STR)))
            ->orderBy('id', 'ASC')->setMaxResults(1)->executeQuery()->fetchOne();
        return $templateId === false ? null : (int)$templateId;
    }
}
