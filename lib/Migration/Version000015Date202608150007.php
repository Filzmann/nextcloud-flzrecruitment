<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Migration;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use OCA\FlzRecruitment\Service\ApplicationMailFieldExtractor;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Ergänzt konservativ fehlende Namensvorschläge in bereits importierten Mails. */
final class Version000015Date202608150007 extends SimpleMigrationStep {
    public function __construct(
        private IDBConnection $db,
        private ApplicationMailFieldExtractor $extractor,
    ) {}

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        $query = $this->db->getQueryBuilder();
        $rows = $query->select('id', 'sender_address', 'body_text', 'field_suggestions_json', 'version')
            ->from('flz_recruitment_messages')->executeQuery()->fetchAllAssociative();
        foreach ($rows as $row) {
            try {
                $suggestions = json_decode((string)$row['field_suggestions_json'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }
            if (!is_array($suggestions) || trim((string)($suggestions['name']['value'] ?? '')) !== '') continue;
            $name = $this->extractor->extract((string)$row['sender_address'], (string)$row['body_text'])['name'] ?? null;
            if ($name === null) continue;
            $suggestions['name'] = $name;
            $update = $this->db->getQueryBuilder();
            $update->update('flz_recruitment_messages')
                ->set('field_suggestions_json', $update->createNamedParameter(json_encode($suggestions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), IQueryBuilder::PARAM_STR))
                ->set('version', $update->createFunction('version + 1'))
                ->set('updated_at', $update->createNamedParameter(new DateTimeImmutable('now', new DateTimeZone('UTC')), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)))
                ->andWhere($update->expr()->eq('version', $update->createNamedParameter((int)$row['version'], IQueryBuilder::PARAM_INT)))
                ->executeStatement();
        }
    }
}
