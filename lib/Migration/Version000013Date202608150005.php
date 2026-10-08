<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Migration;

use Closure;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Führt alle Rückzugsübergänge auf die älteste bereits verwendete Vorlage zusammen. */
final class Version000013Date202608150005 extends SimpleMigrationStep {
    public function __construct(private IDBConnection $db) {}

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        $query = $this->db->getQueryBuilder();
        $templateId = $query->select('template_id')->from('flz_recruitment_status_mail_rules')
            ->where($query->expr()->eq('to_status', $query->createNamedParameter('withdrawn', IQueryBuilder::PARAM_STR)))
            ->orderBy('id', 'ASC')->setMaxResults(1)->executeQuery()->fetchOne();
        if ($templateId === false) return;
        $update = $this->db->getQueryBuilder();
        $update->update('flz_recruitment_status_mail_rules')
            ->set('template_id', $update->createNamedParameter((int)$templateId, IQueryBuilder::PARAM_INT))
            ->set('version', $update->createFunction('version + 1'))
            ->where($update->expr()->eq('to_status', $update->createNamedParameter('withdrawn', IQueryBuilder::PARAM_STR)))
            ->andWhere($update->expr()->neq('template_id', $update->createNamedParameter((int)$templateId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }
}
