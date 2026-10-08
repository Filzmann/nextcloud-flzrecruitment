<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Migration;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use OCA\FlzRecruitment\Service\ApplicationStatusService;
use OCA\FlzRecruitment\Service\DefaultStatusMailTemplateCatalog;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Ergänzt HTML-Formatsnapshots und deaktivierte Standardvorlagen für noch unbelegte Statuskanten. */
final class Version000011Date202608150003 extends SimpleMigrationStep {
    public function __construct(
        private IDBConnection $db,
        private DefaultStatusMailTemplateCatalog $catalog,
        private ApplicationStatusService $statuses,
    ) {}

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if ($schema->hasTable('flz_recruitment_mail_template_revisions')) {
            $revisions = $schema->getTable('flz_recruitment_mail_template_revisions');
            if (!$revisions->hasColumn('body_format')) {
                $revisions->addColumn('body_format', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'plain']);
            }
        }
        if ($schema->hasTable('flz_recruitment_mail_drafts')) {
            $drafts = $schema->getTable('flz_recruitment_mail_drafts');
            if (!$drafts->hasColumn('body_format')) {
                $drafts->addColumn('body_format', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'plain']);
            }
        }
        return $schema;
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        foreach ($this->catalog->templates($this->statuses->transitions()) as $default) {
            $ruleQuery = $this->db->getQueryBuilder();
            $existingRule = $ruleQuery->select('id')->from('flz_recruitment_status_mail_rules')
                ->where($ruleQuery->expr()->eq('from_status', $ruleQuery->createNamedParameter($default['fromStatus'], IQueryBuilder::PARAM_STR)))
                ->andWhere($ruleQuery->expr()->eq('to_status', $ruleQuery->createNamedParameter($default['toStatus'], IQueryBuilder::PARAM_STR)))
                ->setMaxResults(1)->executeQuery()->fetchOne();
            if ($existingRule !== false) continue;

            $this->db->beginTransaction();
            try {
                $templateId = $this->templateId((string)$default['name']);
                if ($templateId === null) $templateId = $this->createTemplate($default);
                $now = $this->now();
                $insert = $this->db->getQueryBuilder();
                $insert->insert('flz_recruitment_status_mail_rules')
                    ->setValue('from_status', $insert->createNamedParameter($default['fromStatus'], IQueryBuilder::PARAM_STR))
                    ->setValue('to_status', $insert->createNamedParameter($default['toStatus'], IQueryBuilder::PARAM_STR))
                    ->setValue('template_id', $insert->createNamedParameter($templateId, IQueryBuilder::PARAM_INT))
                    ->setValue('enabled', $insert->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
                    ->setValue('default_timing', $insert->createNamedParameter($default['defaultTiming'], IQueryBuilder::PARAM_STR))
                    ->setValue('version', $insert->createNamedParameter(1, IQueryBuilder::PARAM_INT))
                    ->setValue('actor_uid', $insert->createNamedParameter('system', IQueryBuilder::PARAM_STR))
                    ->setValue('created_at', $insert->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                    ->setValue('updated_at', $insert->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                    ->executeStatement();
                $this->db->commit();
            } catch (\Throwable $error) {
                $this->db->rollBack();
                throw $error;
            }
        }
    }

    private function templateId(string $name): ?int {
        $query = $this->db->getQueryBuilder();
        $id = $query->select('id')->from('flz_recruitment_mail_templates')
            ->where($query->expr()->eq('name', $query->createNamedParameter($name, IQueryBuilder::PARAM_STR)))
            ->setMaxResults(1)->executeQuery()->fetchOne();
        return $id === false ? null : (int)$id;
    }

    /** @param array<string,mixed> $default */
    private function createTemplate(array $default): int {
        $now = $this->now();
        $template = $this->db->getQueryBuilder();
        $template->insert('flz_recruitment_mail_templates')
            ->setValue('name', $template->createNamedParameter($default['name'], IQueryBuilder::PARAM_STR))
            ->setValue('active', $template->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
            ->setValue('current_revision', $template->createNamedParameter(1, IQueryBuilder::PARAM_INT))
            ->setValue('version', $template->createNamedParameter(1, IQueryBuilder::PARAM_INT))
            ->setValue('actor_uid', $template->createNamedParameter('system', IQueryBuilder::PARAM_STR))
            ->setValue('created_at', $template->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->setValue('updated_at', $template->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->executeStatement();
        $templateId = $template->getLastInsertId();

        $revision = $this->db->getQueryBuilder();
        $revision->insert('flz_recruitment_mail_template_revisions')
            ->setValue('template_id', $revision->createNamedParameter($templateId, IQueryBuilder::PARAM_INT))
            ->setValue('revision', $revision->createNamedParameter(1, IQueryBuilder::PARAM_INT))
            ->setValue('subject_template', $revision->createNamedParameter($default['subject'], IQueryBuilder::PARAM_STR))
            ->setValue('body_template', $revision->createNamedParameter($default['body'], IQueryBuilder::PARAM_STR))
            ->setValue('body_format', $revision->createNamedParameter('html', IQueryBuilder::PARAM_STR))
            ->setValue('actor_uid', $revision->createNamedParameter('system', IQueryBuilder::PARAM_STR))
            ->setValue('created_at', $revision->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->executeStatement();
        return $templateId;
    }

    private function now(): DateTimeImmutable {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
