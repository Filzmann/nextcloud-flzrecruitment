<?php

declare(strict_types=1);

namespace OCA\Recruitment\Repository;

use DateTimeImmutable;
use DateTimeZone;
use OCA\Recruitment\Contract\ApplicationStatusStore;
use OCA\Recruitment\Contract\InterviewStore;
use OCA\Recruitment\Contract\RecruitmentStore;
use OCA\Recruitment\Contract\TemplateStore;
use OCA\Recruitment\Exception\ConflictException;
use OCA\Recruitment\Exception\NotFoundException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Einziger QueryBuilder-Zugang für den ersten Recruitment-Durchstich.
 */
final class RecruitmentRepository implements RecruitmentStore, ApplicationStatusStore, TemplateStore, InterviewStore {
    public function __construct(private IDBConnection $db) {
    }

    public function createJob(array $job): int {
        $assignmentKey = (string)$job['assignmentKey'];
        return $this->insert('rec_jobs', [
            'internal_title' => [(string)$job['internalTitle'], IQueryBuilder::PARAM_STR],
            'public_title' => [(string)$job['publicTitle'], IQueryBuilder::PARAM_STR],
            'active' => [(bool)$job['active'], IQueryBuilder::PARAM_BOOL],
            'responsible_users' => [$this->encode($job['responsibleUsers']), IQueryBuilder::PARAM_STR],
            'responsible_groups' => [$this->encode($job['responsibleGroups']), IQueryBuilder::PARAM_STR],
            'assignment_key' => [
                $assignmentKey === '' ? null : $assignmentKey,
                $assignmentKey === '' ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR,
            ],
            'version' => [1, IQueryBuilder::PARAM_INT],
        ]);
    }

    public function createPerson(array $person): int {
        return $this->insert('rec_people', [
            'given_name' => [(string)$person['givenName'], IQueryBuilder::PARAM_STR],
            'family_name' => [(string)$person['familyName'], IQueryBuilder::PARAM_STR],
            'email' => [(string)$person['email'], IQueryBuilder::PARAM_STR],
            'phone' => [(string)$person['phone'], IQueryBuilder::PARAM_STR],
            'version' => [1, IQueryBuilder::PARAM_INT],
        ]);
    }

    public function personExists(int $id): bool {
        return $this->exists('rec_people', $id);
    }

    public function jobExists(int $id): bool {
        return $this->exists('rec_jobs', $id);
    }

    public function applicationExists(int $id): bool {
        return $this->exists('rec_applications', $id);
    }

    public function createApplication(array $application): int {
        return $this->insert('rec_applications', [
            'person_id' => [(int)$application['personId'], IQueryBuilder::PARAM_INT],
            'job_id' => [(int)$application['jobId'], IQueryBuilder::PARAM_INT],
            'source' => [(string)$application['source'], IQueryBuilder::PARAM_STR],
            'received_on' => [
                new DateTimeImmutable((string)$application['receivedOn']),
                IQueryBuilder::PARAM_DATE_IMMUTABLE,
            ],
            'status' => ['received', IQueryBuilder::PARAM_STR],
            'assignee_uid' => [(string)$application['assigneeUid'], IQueryBuilder::PARAM_STR],
            'closure_reason' => ['', IQueryBuilder::PARAM_STR],
            'retention_state' => ['active', IQueryBuilder::PARAM_STR],
            'version' => [1, IQueryBuilder::PARAM_INT],
        ]);
    }

    public function overview(): array {
        return [
            'jobs' => array_map([$this, 'mapJob'], $this->all(
                'rec_jobs',
                ['id', 'internal_title', 'public_title', 'active', 'responsible_users', 'responsible_groups', 'assignment_key', 'version'],
                ['internal_title' => 'ASC'],
            )),
            'people' => array_map([$this, 'mapPerson'], $this->all(
                'rec_people',
                ['id', 'given_name', 'family_name', 'email', 'phone', 'version'],
                ['family_name' => 'ASC', 'given_name' => 'ASC'],
            )),
            'applications' => array_map([$this, 'mapApplication'], $this->all(
                'rec_applications',
                ['id', 'person_id', 'job_id', 'source', 'received_on', 'status', 'assignee_uid', 'closure_reason', 'retention_state', 'version'],
                ['received_on' => 'DESC', 'id' => 'DESC'],
            )),
            'templates' => array_map([$this, 'mapTemplate'], $this->all(
                'rec_templates',
                ['id', 'name', 'type', 'description', 'audience', 'active', 'revision', 'version'],
                ['name' => 'ASC'],
            )),
        ];
    }

    public function applicationDetail(int $id): array {
        $application = $this->findApplication($id);
        $person = $this->findRow('rec_people', (int)$application['personId']);
        $job = $this->findRow('rec_jobs', (int)$application['jobId']);
        if ($person === null || $job === null) {
            throw new NotFoundException('Die Bewerbung ist unvollständig.');
        }

        $historyQb = $this->db->getQueryBuilder();
        $history = $historyQb
            ->select('id', 'from_status', 'to_status', 'actor_uid', 'changed_at')
            ->from('rec_status_log')
            ->where($historyQb->expr()->eq(
                'application_id',
                $historyQb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
            ))
            ->orderBy('changed_at', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        $interviewQb = $this->db->getQueryBuilder();
        $interviews = $interviewQb
            ->select('id', 'template_id', 'template_revision', 'snapshot_json', 'answers_json', 'status', 'actor_uid', 'version', 'created_at', 'updated_at', 'completed_at')
            ->from('rec_interviews')
            ->where($interviewQb->expr()->eq(
                'application_id',
                $interviewQb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
            ))
            ->orderBy('created_at', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        return [
            'application' => $application,
            'person' => $this->mapPerson($person),
            'job' => $this->mapJob($job),
            'statusHistory' => array_map(static fn (array $row): array => [
                'id' => (int)$row['id'],
                'fromStatus' => (string)$row['from_status'],
                'toStatus' => (string)$row['to_status'],
                'actorUid' => (string)$row['actor_uid'],
                'changedAt' => (string)$row['changed_at'],
            ], $history),
            'interviews' => array_map([$this, 'mapInterview'], $interviews),
        ];
    }

    public function findApplication(int $id): array {
        $row = $this->findRow('rec_applications', $id);
        if ($row === null) {
            throw new NotFoundException('Die Bewerbung wurde nicht gefunden.');
        }
        return $this->mapApplication($row);
    }

    public function transitionStatus(
        int $id,
        string $fromStatus,
        string $toStatus,
        int $expectedVersion,
        string $actorUid,
    ): array {
        $now = $this->now();
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $affected = $qb
                ->update('rec_applications')
                ->set('status', $qb->createNamedParameter($toStatus, IQueryBuilder::PARAM_STR))
                ->set('version', $qb->createFunction('version + 1'))
                ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($fromStatus, IQueryBuilder::PARAM_STR)))
                ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)))
                ->executeStatement();
            if ($affected !== 1) {
                throw new ConflictException('Die Bewerbung wurde zwischenzeitlich geändert.');
            }

            $this->insert('rec_status_log', [
                'application_id' => [$id, IQueryBuilder::PARAM_INT],
                'from_status' => [$fromStatus, IQueryBuilder::PARAM_STR],
                'to_status' => [$toStatus, IQueryBuilder::PARAM_STR],
                'actor_uid' => [$actorUid, IQueryBuilder::PARAM_STR],
                'changed_at' => [$now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            ], false);
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }

        return $this->findApplication($id);
    }

    public function createTemplate(array $template): int {
        return $this->insert('rec_templates', [
            'name' => [(string)$template['name'], IQueryBuilder::PARAM_STR],
            'type' => [(string)$template['type'], IQueryBuilder::PARAM_STR],
            'description' => [(string)$template['description'], IQueryBuilder::PARAM_STR],
            'audience' => [(string)$template['audience'], IQueryBuilder::PARAM_STR],
            'active' => [(bool)$template['active'], IQueryBuilder::PARAM_BOOL],
            'revision' => [1, IQueryBuilder::PARAM_INT],
            'version' => [1, IQueryBuilder::PARAM_INT],
        ]);
    }

    public function createQuestion(array $question): int {
        $this->db->beginTransaction();
        try {
            $id = $this->insert('rec_questions', [
                'template_id' => [(int)$question['templateId'], IQueryBuilder::PARAM_INT],
                'prompt' => [(string)$question['prompt'], IQueryBuilder::PARAM_STR],
                'hint' => [(string)$question['hint'], IQueryBuilder::PARAM_STR],
                'type' => [(string)$question['type'], IQueryBuilder::PARAM_STR],
                'required' => [(bool)$question['required'], IQueryBuilder::PARAM_BOOL],
                'sort_order' => [(int)$question['sortOrder'], IQueryBuilder::PARAM_INT],
                'options_json' => [$this->encode($question['options']), IQueryBuilder::PARAM_STR],
                'visibility' => [(string)$question['visibility'], IQueryBuilder::PARAM_STR],
                'active' => [(bool)$question['active'], IQueryBuilder::PARAM_BOOL],
                'version' => [1, IQueryBuilder::PARAM_INT],
            ]);
            $this->bumpTemplate((int)$question['templateId']);
            $this->db->commit();
            return $id;
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function updateQuestion(int $id, array $question): void {
        $existing = $this->question($id);
        $now = $this->now();
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $affected = $qb
                ->update('rec_questions')
                ->set('prompt', $qb->createNamedParameter((string)$question['prompt'], IQueryBuilder::PARAM_STR))
                ->set('hint', $qb->createNamedParameter((string)$question['hint'], IQueryBuilder::PARAM_STR))
                ->set('type', $qb->createNamedParameter((string)$question['type'], IQueryBuilder::PARAM_STR))
                ->set('required', $qb->createNamedParameter((bool)$question['required'], IQueryBuilder::PARAM_BOOL))
                ->set('sort_order', $qb->createNamedParameter((int)$question['sortOrder'], IQueryBuilder::PARAM_INT))
                ->set('options_json', $qb->createNamedParameter($this->encode($question['options']), IQueryBuilder::PARAM_STR))
                ->set('visibility', $qb->createNamedParameter((string)$question['visibility'], IQueryBuilder::PARAM_STR))
                ->set('active', $qb->createNamedParameter((bool)$question['active'], IQueryBuilder::PARAM_BOOL))
                ->set('version', $qb->createFunction('version + 1'))
                ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
                ->executeStatement();
            if ($affected !== 1) {
                throw new NotFoundException('Die Frage wurde nicht gefunden.');
            }
            $this->bumpTemplate((int)$existing['templateId']);
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function createBubble(array $bubble): int {
        $question = $this->question((int)$bubble['questionId']);
        $this->db->beginTransaction();
        try {
            $id = $this->insert('rec_bubbles', [
                'question_id' => [(int)$bubble['questionId'], IQueryBuilder::PARAM_INT],
                'label' => [(string)$bubble['label'], IQueryBuilder::PARAM_STR],
                'insert_text' => [(string)$bubble['insertText'], IQueryBuilder::PARAM_STR],
                'sort_order' => [(int)$bubble['sortOrder'], IQueryBuilder::PARAM_INT],
                'active' => [(bool)$bubble['active'], IQueryBuilder::PARAM_BOOL],
                'version' => [1, IQueryBuilder::PARAM_INT],
            ]);
            $this->bumpTemplate((int)$question['templateId']);
            $this->db->commit();
            return $id;
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function question(int $id): array {
        $row = $this->findRow('rec_questions', $id);
        if ($row === null) {
            throw new NotFoundException('Die Frage wurde nicht gefunden.');
        }
        return $this->mapQuestion($row);
    }

    public function templateSnapshot(int $id): array {
        $row = $this->findRow('rec_templates', $id);
        if ($row === null) {
            throw new NotFoundException('Die Interviewvorlage wurde nicht gefunden.');
        }
        $template = $this->mapTemplate($row);

        $qb = $this->db->getQueryBuilder();
        $questionRows = $qb
            ->select('id', 'template_id', 'prompt', 'hint', 'type', 'required', 'sort_order', 'options_json', 'visibility', 'active', 'version')
            ->from('rec_questions')
            ->where($qb->expr()->eq('template_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->orderBy('sort_order', 'ASC')
            ->addOrderBy('id', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        $questions = [];
        foreach ($questionRows as $questionRow) {
            $question = $this->mapQuestion($questionRow);
            $bubbleQb = $this->db->getQueryBuilder();
            $bubbleRows = $bubbleQb
                ->select('id', 'question_id', 'label', 'insert_text', 'sort_order', 'active', 'version')
                ->from('rec_bubbles')
                ->where($bubbleQb->expr()->eq(
                    'question_id',
                    $bubbleQb->createNamedParameter((int)$question['id'], IQueryBuilder::PARAM_INT),
                ))
                ->orderBy('sort_order', 'ASC')
                ->addOrderBy('id', 'ASC')
                ->executeQuery()
                ->fetchAllAssociative();
            $question['bubbles'] = array_map([$this, 'mapBubble'], $bubbleRows);
            $questions[] = $question;
        }
        $template['questions'] = $questions;
        return $template;
    }

    public function createInterview(array $interview): int {
        return $this->insert('rec_interviews', [
            'application_id' => [(int)$interview['applicationId'], IQueryBuilder::PARAM_INT],
            'template_id' => [(int)$interview['templateId'], IQueryBuilder::PARAM_INT],
            'template_revision' => [(int)$interview['templateRevision'], IQueryBuilder::PARAM_INT],
            'snapshot_json' => [$this->encode($interview['snapshot']), IQueryBuilder::PARAM_STR],
            'answers_json' => ['{}', IQueryBuilder::PARAM_STR],
            'status' => ['not_started', IQueryBuilder::PARAM_STR],
            'actor_uid' => [(string)$interview['actorUid'], IQueryBuilder::PARAM_STR],
            'version' => [1, IQueryBuilder::PARAM_INT],
            'completed_at' => [null, IQueryBuilder::PARAM_NULL],
        ]);
    }

    public function interview(int $id): array {
        $row = $this->findRow('rec_interviews', $id);
        if ($row === null) {
            throw new NotFoundException('Das Interview wurde nicht gefunden.');
        }
        return $this->mapInterview($row);
    }

    public function saveInterviewDraft(int $id, array $answers, string $status, int $expectedVersion): array {
        return $this->updateInterview($id, $answers, $status, $expectedVersion, null);
    }

    public function completeInterview(int $id, array $answers, int $expectedVersion): array {
        return $this->updateInterview($id, $answers, 'completed', $expectedVersion, $this->now());
    }

    private function updateInterview(
        int $id,
        array $answers,
        string $status,
        int $expectedVersion,
        ?DateTimeImmutable $completedAt,
    ): array {
        $qb = $this->db->getQueryBuilder();
        $qb
            ->update('rec_interviews')
            ->set('answers_json', $qb->createNamedParameter($this->encode($answers), IQueryBuilder::PARAM_STR))
            ->set('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_STR))
            ->set('version', $qb->createFunction('version + 1'))
            ->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->neq('status', $qb->createNamedParameter('completed', IQueryBuilder::PARAM_STR)));
        if ($completedAt !== null) {
            $qb->set('completed_at', $qb->createNamedParameter($completedAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE));
        }
        if ($qb->executeStatement() !== 1) {
            throw new ConflictException('Das Interview wurde zwischenzeitlich geändert oder bereits abgeschlossen.');
        }
        return $this->interview($id);
    }

    /**
     * @param array<string,array{0:mixed,1:mixed}> $values
     */
    private function insert(string $table, array $values, bool $timestamps = true): int {
        if ($timestamps) {
            $now = $this->now();
            $values['created_at'] = [$now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE];
            $values['updated_at'] = [$now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE];
        }
        $qb = $this->db->getQueryBuilder();
        $qb->insert($table);
        foreach ($values as $field => [$value, $type]) {
            $qb->setValue($field, $qb->createNamedParameter($value, $type));
        }
        $qb->executeStatement();
        return $qb->getLastInsertId();
    }

    private function exists(string $table, int $id): bool {
        $qb = $this->db->getQueryBuilder();
        return $qb
            ->select('id')
            ->from($table)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne() !== false;
    }

    /** @return array<string,mixed>|null */
    private function findRow(string $table, int $id): ?array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb
            ->select('*')
            ->from($table)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : $row;
    }

    /**
     * @param list<string> $fields
     * @param array<string,'ASC'|'DESC'> $order
     * @return list<array<string,mixed>>
     */
    private function all(string $table, array $fields, array $order): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select(...$fields)->from($table);
        foreach ($order as $field => $direction) {
            $qb->addOrderBy($field, $direction);
        }
        return $qb->executeQuery()->fetchAllAssociative();
    }

    private function bumpTemplate(int $id): void {
        $qb = $this->db->getQueryBuilder();
        $qb
            ->update('rec_templates')
            ->set('revision', $qb->createFunction('revision + 1'))
            ->set('version', $qb->createFunction('version + 1'))
            ->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
    }

    /** @return array<string,mixed> */
    private function mapJob(array $row): array {
        return [
            'id' => (int)$row['id'],
            'internalTitle' => (string)$row['internal_title'],
            'publicTitle' => (string)$row['public_title'],
            'active' => (bool)$row['active'],
            'responsibleUsers' => $this->decode((string)$row['responsible_users']),
            'responsibleGroups' => $this->decode((string)$row['responsible_groups']),
            'assignmentKey' => (string)($row['assignment_key'] ?? ''),
            'version' => (int)$row['version'],
        ];
    }

    /** @return array<string,mixed> */
    private function mapPerson(array $row): array {
        return [
            'id' => (int)$row['id'],
            'givenName' => (string)$row['given_name'],
            'familyName' => (string)$row['family_name'],
            'email' => (string)$row['email'],
            'phone' => (string)$row['phone'],
            'version' => (int)$row['version'],
        ];
    }

    /** @return array<string,mixed> */
    private function mapApplication(array $row): array {
        return [
            'id' => (int)$row['id'],
            'personId' => (int)$row['person_id'],
            'jobId' => (int)$row['job_id'],
            'source' => (string)$row['source'],
            'receivedOn' => (string)$row['received_on'],
            'status' => (string)$row['status'],
            'assigneeUid' => (string)$row['assignee_uid'],
            'closureReason' => (string)$row['closure_reason'],
            'retentionState' => (string)$row['retention_state'],
            'version' => (int)$row['version'],
        ];
    }

    /** @return array<string,mixed> */
    private function mapTemplate(array $row): array {
        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'type' => (string)$row['type'],
            'description' => (string)$row['description'],
            'audience' => (string)$row['audience'],
            'active' => (bool)$row['active'],
            'revision' => (int)$row['revision'],
            'version' => (int)$row['version'],
        ];
    }

    /** @return array<string,mixed> */
    private function mapQuestion(array $row): array {
        return [
            'id' => (int)$row['id'],
            'templateId' => (int)$row['template_id'],
            'prompt' => (string)$row['prompt'],
            'hint' => (string)$row['hint'],
            'type' => (string)$row['type'],
            'required' => (bool)$row['required'],
            'sortOrder' => (int)$row['sort_order'],
            'options' => $this->decode((string)$row['options_json']),
            'visibility' => (string)$row['visibility'],
            'active' => (bool)$row['active'],
            'version' => (int)$row['version'],
        ];
    }

    /** @return array<string,mixed> */
    private function mapBubble(array $row): array {
        return [
            'id' => (int)$row['id'],
            'questionId' => (int)$row['question_id'],
            'label' => (string)$row['label'],
            'insertText' => (string)$row['insert_text'],
            'sortOrder' => (int)$row['sort_order'],
            'active' => (bool)$row['active'],
            'version' => (int)$row['version'],
        ];
    }

    /** @return array<string,mixed> */
    private function mapInterview(array $row): array {
        return [
            'id' => (int)$row['id'],
            'applicationId' => (int)($row['application_id'] ?? 0),
            'templateId' => (int)$row['template_id'],
            'templateRevision' => (int)$row['template_revision'],
            'snapshot' => $this->decode((string)$row['snapshot_json']),
            'answers' => $this->decode((string)$row['answers_json']),
            'status' => (string)$row['status'],
            'actorUid' => (string)$row['actor_uid'],
            'version' => (int)$row['version'],
            'createdAt' => (string)$row['created_at'],
            'updatedAt' => (string)$row['updated_at'],
            'completedAt' => $row['completed_at'] === null ? null : (string)$row['completed_at'],
        ];
    }

    private function now(): DateTimeImmutable {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function encode(mixed $value): string {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<mixed> */
    private function decode(string $value): array {
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }
}
