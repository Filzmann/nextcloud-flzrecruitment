<?php

declare(strict_types=1);

namespace OCA\Recruitment\Repository;

use DateTimeImmutable;
use DateTimeZone;
use OCA\Recruitment\Contract\ApplicationStatusStore;
use OCA\Recruitment\Contract\BasisQualificationStore;
use OCA\Recruitment\Contract\CandidatePoolStore;
use OCA\Recruitment\Contract\InterviewStore;
use OCA\Recruitment\Contract\HiringDataStore;
use OCA\Recruitment\Contract\MailInboxStore;
use OCA\Recruitment\Contract\DocumentFieldLinkStore;
use OCA\Recruitment\Contract\DocumentReviewStore;
use OCA\Recruitment\Contract\StatusMailOutboxStore;
use OCA\Recruitment\Contract\RecruitmentStore;
use OCA\Recruitment\Contract\TemplateStore;
use OCA\Recruitment\Exception\ConflictException;
use OCA\Recruitment\Exception\NotFoundException;
use OCA\Recruitment\Exception\ValidationException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Einziger QueryBuilder-Zugang für den ersten Recruitment-Durchstich.
 */
final class RecruitmentRepository implements RecruitmentStore, ApplicationStatusStore, TemplateStore, InterviewStore, HiringDataStore, BasisQualificationStore, MailInboxStore, DocumentReviewStore, DocumentFieldLinkStore, StatusMailOutboxStore, CandidatePoolStore {
    public function __construct(private IDBConnection $db) {
    }

    /** @return list<array<string,mixed>> */
    public function personalDataForNextcloudUid(string $uid,int $limit):array {
        $result=[];
        foreach([
            ['rec_applications',['id','status','updated_at'],['assignee_uid'],'application_assignment','updated_at'],
            ['rec_status_log',['id','application_id','from_status','to_status','changed_at'],['actor_uid'],'status_change','changed_at'],
            ['rec_interviews',['id','application_id','status','updated_at'],['actor_uid'],'interview','updated_at'],
            ['rec_permission_audit',['id','application_id','action','actor_uid','subject_uid','created_at'],['actor_uid','subject_uid'],'permission_audit','created_at'],
            ['rec_bq_runs',['id','label','updated_at'],['actor_uid'],'bq_run','updated_at'],
            ['rec_bq_assignments',['id','application_id','result','updated_at'],['actor_uid'],'bq_assignment','updated_at'],
            ['rec_message_audit',['id','from_state','to_state','changed_at'],['actor_uid'],'message_audit','changed_at'],
            ['rec_document_comments',['id','created_at'],['actor_uid'],'document_comment','created_at'],
            ['rec_document_field_links',['id','application_id','created_at'],['actor_uid'],'document_field_link','created_at'],
            ['rec_mail_templates',['id','name','updated_at'],['actor_uid'],'mail_template','updated_at'],
            ['rec_mail_template_revisions',['id','created_at'],['actor_uid'],'mail_template_revision','created_at'],
            ['rec_mail_text_blocks',['id','label','updated_at'],['actor_uid'],'mail_text_block','updated_at'],
            ['rec_status_mail_rules',['id','from_status','to_status','updated_at'],['actor_uid'],'status_mail_rule','updated_at'],
            ['rec_mail_drafts',['id','application_id','state','created_at'],['actor_uid','approved_by'],'mail_draft','created_at'],
            ['rec_pool_entries',['id','source_application_id','status','updated_at'],['actor_uid'],'candidate_pool_entry','updated_at'],
            ['rec_pool_consents',['id','entry_id','action','occurred_at'],['actor_uid'],'candidate_pool_consent','occurred_at'],
            ['rec_pool_matches',['id','entry_id','state','updated_at'],['reviewed_by_uid'],'candidate_pool_match','updated_at'],
        ] as [$table,$columns,$uidColumns,$kind,$dateColumn]){
            $qb=$this->db->getQueryBuilder();$conditions=[];foreach($uidColumns as $column)$conditions[]=$qb->expr()->eq($column,$qb->createNamedParameter($uid,IQueryBuilder::PARAM_STR));
            $rows=$qb->select(...$columns)->from($table)->where($qb->expr()->orX(...$conditions))->orderBy($dateColumn,'ASC')->setMaxResults($limit)->executeQuery()->fetchAllAssociative();
            foreach($rows as $row){$item=['kind'=>$kind,'id'=>(int)$row['id'],'occurred_at'=>$row[$dateColumn]];if(isset($row['application_id'])&&$row['application_id']!==null)$item['application_id']=(int)$row['application_id'];if(isset($row['label']))$item['label']=(string)$row['label'];if(isset($row['name']))$item['label']=(string)$row['name'];if(isset($row['status']))$item['status']=(string)$row['status'];if(isset($row['state']))$item['status']=(string)$row['state'];if(isset($row['result']))$item['action']=(string)$row['result'];if(isset($row['action']))$item['action']=(string)$row['action'];if(isset($row['from_status']))$item['action']=(string)$row['from_status'].' → '.(string)$row['to_status'];if(isset($row['from_state']))$item['action']=(string)$row['from_state'].' → '.(string)$row['to_state'];if($kind==='permission_audit')$item['role']=(string)$row['subject_uid']===$uid?'subject':'actor';$result[]=$item;}
        }
        $jobs=$this->all('rec_jobs',['id','internal_title','responsible_users','created_at'],['id'=>'ASC']);foreach($jobs as $job)if(in_array($uid,$this->decode((string)$job['responsible_users']),true))$result[]=['kind'=>'job_responsibility','id'=>(int)$job['id'],'label'=>(string)$job['internal_title'],'occurred_at'=>$job['created_at']];
        usort($result,static fn(array $a,array $b):int=>strcmp((string)$a['occurred_at'],(string)$b['occurred_at']));return array_slice($result,0,$limit);
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
            'basis_qualification_required' => [(bool)($job['basisQualificationRequired'] ?? false), IQueryBuilder::PARAM_BOOL],
            'profession_category' => [(string)($job['professionCategory'] ?? 'other'), IQueryBuilder::PARAM_STR],
            'contract_term' => [$job['contractTerm'] === '' ? null : (string)$job['contractTerm'], $job['contractTerm'] === '' ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR],
            'pay_grade' => [$job['payGrade'] === '' ? null : (string)$job['payGrade'], $job['payGrade'] === '' ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR],
            'advertised_weekly_hours' => [$job['advertisedWeeklyHours'], $job['advertisedWeeklyHours'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR],
            'full_time_weekly_hours' => [$job['fullTimeWeeklyHours'], $job['fullTimeWeeklyHours'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR],
            'vacation_days' => [$job['vacationDays'], $job['vacationDays'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR],
            'work_location' => [(string)$job['workLocation'], IQueryBuilder::PARAM_STR],
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

    /** @param array<string,mixed> $mailbox */
    public function ensureMailbox(array $mailbox): int {
        $qb = $this->db->getQueryBuilder();
        $id = $qb->select('id')->from('rec_mailboxes')
            ->where($qb->expr()->eq('technical_key', $qb->createNamedParameter((string)$mailbox['technicalKey'], IQueryBuilder::PARAM_STR)))
            ->executeQuery()->fetchOne();
        if ($id !== false) return (int)$id;
        return $this->insert('rec_mailboxes', [
            'technical_key' => [(string)$mailbox['technicalKey'], IQueryBuilder::PARAM_STR],
            'label' => [(string)$mailbox['label'], IQueryBuilder::PARAM_STR],
            'address' => [(string)$mailbox['address'], IQueryBuilder::PARAM_STR],
            'active' => [true, IQueryBuilder::PARAM_BOOL],
            'version' => [1, IQueryBuilder::PARAM_INT],
        ]);
    }

    public function findMessageByIdentity(int $mailboxId, ?string $externalMessageId, string $contentHash): ?array {
        $id = $this->findInboxMessageId($mailboxId, 'content_hash', $contentHash);
        if ($id === null && $externalMessageId !== null) {
            $id = $this->findInboxMessageId($mailboxId, 'external_message_id', $externalMessageId);
        }
        return $id === null ? null : $this->inboxMessage($id);
    }

    /** @param array<string,mixed> $message */
    public function createInboxMessage(array $message): int {
        $id = $this->insert('rec_messages', [
            'mailbox_id' => [(int)$message['mailboxId'], IQueryBuilder::PARAM_INT],
            'external_message_id' => [$message['externalMessageId'], $message['externalMessageId'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR],
            'content_hash' => [(string)$message['contentHash'], IQueryBuilder::PARAM_STR],
            'state' => [(string)$message['state'], IQueryBuilder::PARAM_STR],
            'sender_address' => [(string)$message['senderAddress'], IQueryBuilder::PARAM_STR],
            'recipients_json' => [$this->encode($message['recipients']), IQueryBuilder::PARAM_STR],
            'subject' => [(string)$message['subject'], IQueryBuilder::PARAM_STR],
            'received_at' => [$message['receivedAt'], IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'body_text' => [(string)$message['bodyText'], IQueryBuilder::PARAM_STR],
            'field_suggestions_json' => [$this->encode($message['fieldSuggestions']), IQueryBuilder::PARAM_STR],
            'application_id' => [null, IQueryBuilder::PARAM_NULL],
            'imported_at' => [$this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'version' => [1, IQueryBuilder::PARAM_INT],
        ]);
        $this->insertInboxAudit($id, '', (string)$message['state'], (string)$message['actorUid'], ['action' => 'imported']);
        return $id;
    }

    /** @param array<string,mixed> $attachment */
    public function addInboxAttachment(int $messageId, array $attachment): int {
        return $this->insert('rec_attachments', [
            'message_id' => [$messageId, IQueryBuilder::PARAM_INT],
            'original_name' => [(string)$attachment['originalName'], IQueryBuilder::PARAM_STR],
            'stored_name' => [(string)$attachment['storedName'], IQueryBuilder::PARAM_STR],
            'mime_type' => [(string)$attachment['mimeType'], IQueryBuilder::PARAM_STR],
            'size_bytes' => [(int)$attachment['sizeBytes'], IQueryBuilder::PARAM_INT],
            'content_hash' => [(string)$attachment['contentHash'], IQueryBuilder::PARAM_STR],
            'storage_path' => [(string)$attachment['storagePath'], IQueryBuilder::PARAM_STR],
            'created_at' => [$this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
        ], false);
    }

    public function attachmentContext(int $id): array {
        $attachment = $this->findRow('rec_attachments', $id);
        if ($attachment === null) throw new NotFoundException('Das Dokument wurde nicht gefunden.');
        $message = $this->findRow('rec_messages', (int)$attachment['message_id']);
        if ($message === null) throw new NotFoundException('Die Eingangsnachricht des Dokuments wurde nicht gefunden.');
        return [
            'id' => (int)$attachment['id'],
            'messageId' => (int)$attachment['message_id'],
            'applicationId' => $message['application_id'] === null ? null : (int)$message['application_id'],
            'originalName' => (string)$attachment['original_name'],
            'mimeType' => (string)$attachment['mime_type'],
            'contentHash' => (string)$attachment['content_hash'],
            'storagePath' => (string)$attachment['storage_path'],
        ];
    }

    public function documentComments(int $attachmentId): array {
        $qb = $this->db->getQueryBuilder();
        $rows = $qb->select('id', 'attachment_id', 'kind', 'body', 'page_number', 'anchor_column', 'actor_uid', 'client_key', 'version', 'created_at')
            ->from('rec_document_comments')
            ->where($qb->expr()->eq('attachment_id', $qb->createNamedParameter($attachmentId, IQueryBuilder::PARAM_INT)))
            ->orderBy('created_at', 'ASC')->addOrderBy('id', 'ASC')->executeQuery()->fetchAllAssociative();
        return array_map([$this, 'mapDocumentComment'], $rows);
    }

    public function findDocumentCommentByClientKey(int $attachmentId, string $clientKey): ?array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb->select('id', 'attachment_id', 'kind', 'body', 'page_number', 'anchor_column', 'actor_uid', 'client_key', 'version', 'created_at')
            ->from('rec_document_comments')
            ->where($qb->expr()->eq('attachment_id', $qb->createNamedParameter($attachmentId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('client_key', $qb->createNamedParameter($clientKey, IQueryBuilder::PARAM_STR)))
            ->executeQuery()->fetchAssociative();
        return $row === false ? null : $this->mapDocumentComment($row);
    }

    public function createDocumentComment(array $comment): int {
        return $this->insert('rec_document_comments', [
            'attachment_id' => [(int)$comment['attachmentId'], IQueryBuilder::PARAM_INT],
            'kind' => [(string)$comment['kind'], IQueryBuilder::PARAM_STR],
            'body' => [(string)$comment['body'], IQueryBuilder::PARAM_STR],
            'page_number' => [$comment['pageNumber'], $comment['pageNumber'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT],
            'anchor_column' => [$comment['anchorColumn'] === '' ? null : (string)$comment['anchorColumn'], $comment['anchorColumn'] === '' ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR],
            'actor_uid' => [(string)$comment['actorUid'], IQueryBuilder::PARAM_STR],
            'client_key' => [(string)$comment['clientKey'], IQueryBuilder::PARAM_STR],
            'version' => [1, IQueryBuilder::PARAM_INT],
            'created_at' => [$this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
        ], false);
    }

    public function documentComment(int $id): array {
        $row = $this->findRow('rec_document_comments', $id);
        if ($row === null) throw new NotFoundException('Der Dokumentkommentar wurde nicht gefunden.');
        return $this->mapDocumentComment($row);
    }

    /** @return array<string,mixed> */
    public function documentFieldState(int $applicationId): array {
        $row = $this->findRow('rec_applications', $applicationId);
        if ($row === null) throw new NotFoundException('Die Bewerbung wurde nicht gefunden.');
        $hiring = $this->hiringData($applicationId);
        $hiringData = $hiring['data'];
        $applicationVersion = (int)$row['version'];
        $hiringVersion = (int)$hiring['version'];
        return [
            'applicationId' => $applicationId,
            'fields' => [
                'previousExperience' => ['value' => (string)($row['previous_experience'] ?? ''), 'version' => $applicationVersion],
                'germanLanguageLevel' => ['value' => (string)($row['german_language_level'] ?? ''), 'version' => $applicationVersion],
                'freeComment' => ['value' => (string)($row['free_comment'] ?? ''), 'version' => $applicationVersion],
                'birthDate' => ['value' => (string)($hiringData['birthDate'] ?? ''), 'version' => $hiringVersion],
                'birthPlace' => ['value' => (string)($hiringData['birthPlace'] ?? ''), 'version' => $hiringVersion],
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function documentFieldLinks(int $attachmentId): array {
        $qb = $this->db->getQueryBuilder();
        $rows = $qb->select(
            'id', 'attachment_id', 'application_id', 'target_field', 'selected_text',
            'applied_value', 'result_value', 'page_number', 'rectangles_json',
            'replaced_existing', 'actor_uid', 'client_key', 'created_at',
        )->from('rec_document_field_links')
            ->where($qb->expr()->eq('attachment_id', $qb->createNamedParameter($attachmentId, IQueryBuilder::PARAM_INT)))
            ->orderBy('created_at', 'ASC')->addOrderBy('id', 'ASC')->executeQuery()->fetchAllAssociative();
        return array_map([$this, 'mapDocumentFieldLink'], $rows);
    }

    /** @return array<string,mixed>|null */
    public function findDocumentFieldLinkByClientKey(int $attachmentId, string $clientKey): ?array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb->select(
            'id', 'attachment_id', 'application_id', 'target_field', 'selected_text',
            'applied_value', 'result_value', 'page_number', 'rectangles_json',
            'replaced_existing', 'actor_uid', 'client_key', 'created_at',
        )->from('rec_document_field_links')
            ->where($qb->expr()->eq('attachment_id', $qb->createNamedParameter($attachmentId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('client_key', $qb->createNamedParameter($clientKey, IQueryBuilder::PARAM_STR)))
            ->executeQuery()->fetchAssociative();
        return $row === false ? null : $this->mapDocumentFieldLink($row);
    }

    /** @param array<string,mixed> $link */
    public function createDocumentFieldLinkAndApply(array $link, string $resultValue, int $expectedVersion): int {
        $applicationId = (int)$link['applicationId'];
        $attachmentId = (int)$link['attachmentId'];
        $targetField = (string)$link['targetField'];
        $this->db->beginTransaction();
        try {
            $attachment = $this->attachmentContext($attachmentId);
            if (($attachment['applicationId'] ?? null) !== $applicationId) {
                throw new ConflictException('Die Dokumentzuordnung wurde zwischenzeitlich geändert.');
            }
            $applicationColumns = [
                'previousExperience' => 'previous_experience',
                'germanLanguageLevel' => 'german_language_level',
                'freeComment' => 'free_comment',
            ];
            if (isset($applicationColumns[$targetField])) {
                $qb = $this->db->getQueryBuilder();
                $affected = $qb->update('rec_applications')
                    ->set($applicationColumns[$targetField], $qb->createNamedParameter($resultValue, IQueryBuilder::PARAM_STR))
                    ->set('version', $qb->createFunction('version + 1'))
                    ->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                    ->where($qb->expr()->eq('id', $qb->createNamedParameter($applicationId, IQueryBuilder::PARAM_INT)))
                    ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)))
                    ->executeStatement();
                if ($affected !== 1) throw new ConflictException('Das Zielfeld wurde zwischenzeitlich geändert.');
            } else {
                $hiring = $this->hiringData($applicationId);
                if ((int)$hiring['version'] !== $expectedVersion) {
                    throw new ConflictException('Das Zielfeld wurde zwischenzeitlich geändert.');
                }
                $data = $hiring['data'];
                $data[$targetField] = $resultValue;
                if ($expectedVersion === 0) {
                    $this->insert('rec_hiring_data', [
                        'application_id' => [$applicationId, IQueryBuilder::PARAM_INT],
                        'data_json' => [$this->encode($data), IQueryBuilder::PARAM_STR],
                        'version' => [1, IQueryBuilder::PARAM_INT],
                    ]);
                } else {
                    $qb = $this->db->getQueryBuilder();
                    $affected = $qb->update('rec_hiring_data')
                        ->set('data_json', $qb->createNamedParameter($this->encode($data), IQueryBuilder::PARAM_STR))
                        ->set('version', $qb->createFunction('version + 1'))
                        ->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                        ->where($qb->expr()->eq('application_id', $qb->createNamedParameter($applicationId, IQueryBuilder::PARAM_INT)))
                        ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)))
                        ->executeStatement();
                    if ($affected !== 1) throw new ConflictException('Das Zielfeld wurde zwischenzeitlich geändert.');
                }
            }
            $id = $this->insert('rec_document_field_links', [
                'attachment_id' => [$attachmentId, IQueryBuilder::PARAM_INT],
                'application_id' => [$applicationId, IQueryBuilder::PARAM_INT],
                'target_field' => [$targetField, IQueryBuilder::PARAM_STR],
                'selected_text' => [(string)$link['selectedText'], IQueryBuilder::PARAM_STR],
                'applied_value' => [(string)$link['appliedValue'], IQueryBuilder::PARAM_STR],
                'result_value' => [$resultValue, IQueryBuilder::PARAM_STR],
                'page_number' => [(int)$link['pageNumber'], IQueryBuilder::PARAM_INT],
                'rectangles_json' => [$this->encode($link['rectangles']), IQueryBuilder::PARAM_STR],
                'replaced_existing' => [(bool)$link['replaceExisting'], IQueryBuilder::PARAM_BOOL],
                'actor_uid' => [(string)$link['actorUid'], IQueryBuilder::PARAM_STR],
                'client_key' => [(string)$link['clientKey'], IQueryBuilder::PARAM_STR],
                'created_at' => [$this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            ], false);
            $this->db->commit();
            return $id;
        } catch (\Throwable $error) {
            $this->db->rollBack();
            $existing = $this->findDocumentFieldLinkByClientKey($attachmentId, (string)$link['clientKey']);
            if ($existing !== null) return (int)$existing['id'];
            if ($expectedVersion === 0 && in_array($targetField, ['birthDate', 'birthPlace'], true)
                && $this->hiringData($applicationId)['version'] > 0) {
                throw new ConflictException('Das Zielfeld wurde zwischenzeitlich geändert.');
            }
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    public function documentFieldLink(int $id): array {
        $row = $this->findRow('rec_document_field_links', $id);
        if ($row === null) throw new NotFoundException('Die PDF-Feldverknüpfung wurde nicht gefunden.');
        return $this->mapDocumentFieldLink($row);
    }

    public function markInboxImportError(int $messageId, string $actorUid): void {
        $message = $this->inboxMessage($messageId);
        $qb = $this->db->getQueryBuilder();
        $qb->update('rec_messages')
            ->set('state', $qb->createNamedParameter('error', IQueryBuilder::PARAM_STR))
            ->set('version', $qb->createFunction('version + 1'))
            ->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($messageId, IQueryBuilder::PARAM_INT)))
            ->executeStatement();
        $this->insertInboxAudit($messageId, (string)$message['state'], 'error', $actorUid, ['action' => 'storage_failed']);
    }

    public function inboxApplicationExists(int $applicationId): bool { return $this->applicationExists($applicationId); }

    /** @return list<array<string,mixed>> */
    public function inboxMessages(): array {
        $qb = $this->db->getQueryBuilder();
        $ids = $qb->select('id')->from('rec_messages')
            ->where($qb->expr()->isNull('application_id'))
            ->orderBy('received_at', 'DESC')->addOrderBy('id', 'DESC')
            ->executeQuery()->fetchFirstColumn();
        return array_map(fn(mixed $id): array => $this->inboxMessage((int)$id), $ids);
    }

    public function inboxMessagesForApplication(int $applicationId): array {
        $qb = $this->db->getQueryBuilder();
        $ids = $qb->select('id')->from('rec_messages')
            ->where($qb->expr()->eq('application_id', $qb->createNamedParameter($applicationId, IQueryBuilder::PARAM_INT)))
            ->orderBy('received_at', 'DESC')->addOrderBy('id', 'DESC')->executeQuery()->fetchFirstColumn();
        return array_map(fn(mixed $id): array => $this->inboxMessage((int)$id), $ids);
    }

    /** @return array<string,mixed> */
    public function inboxMessage(int $messageId): array {
        $row = $this->findRow('rec_messages', $messageId);
        if ($row === null) throw new NotFoundException('Die Eingangsnachricht wurde nicht gefunden.');
        $mailbox = $this->findRow('rec_mailboxes', (int)$row['mailbox_id']);
        if ($mailbox === null) throw new NotFoundException('Das zugehörige Postfach wurde nicht gefunden.');
        $attachmentQb = $this->db->getQueryBuilder();
        $attachmentRows = $attachmentQb->select('id', 'original_name', 'stored_name', 'mime_type', 'size_bytes', 'content_hash')
            ->from('rec_attachments')
            ->where($attachmentQb->expr()->eq('message_id', $attachmentQb->createNamedParameter($messageId, IQueryBuilder::PARAM_INT)))
            ->orderBy('id', 'ASC')->executeQuery()->fetchAllAssociative();
        $auditQb = $this->db->getQueryBuilder();
        $auditRows = $auditQb->select('id', 'from_state', 'to_state', 'actor_uid', 'details_json', 'changed_at')
            ->from('rec_message_audit')
            ->where($auditQb->expr()->eq('message_id', $auditQb->createNamedParameter($messageId, IQueryBuilder::PARAM_INT)))
            ->orderBy('changed_at', 'DESC')->addOrderBy('id', 'DESC')->executeQuery()->fetchAllAssociative();
        return [
            'id' => (int)$row['id'],
            'mailbox' => ['id' => (int)$mailbox['id'], 'technicalKey' => (string)$mailbox['technical_key'], 'label' => (string)$mailbox['label'], 'address' => (string)$mailbox['address']],
            'externalMessageId' => $row['external_message_id'] === null ? null : (string)$row['external_message_id'],
            'contentHash' => (string)$row['content_hash'],
            'state' => (string)$row['state'],
            'senderAddress' => (string)$row['sender_address'],
            'recipients' => $this->decode((string)$row['recipients_json']),
            'subject' => (string)$row['subject'],
            'receivedAt' => (string)$row['received_at'],
            'bodyText' => (string)$row['body_text'],
            'fieldSuggestions' => $this->decode((string)$row['field_suggestions_json']),
            'applicationId' => $row['application_id'] === null ? null : (int)$row['application_id'],
            'attachments' => array_map(fn(array $attachment): array => [
                'id' => (int)$attachment['id'], 'originalName' => (string)$attachment['original_name'],
                'storedName' => (string)$attachment['stored_name'], 'mimeType' => (string)$attachment['mime_type'],
                'sizeBytes' => (int)$attachment['size_bytes'], 'contentHash' => (string)$attachment['content_hash'],
                'comments' => $this->documentComments((int)$attachment['id']),
                'fieldLinks' => $this->documentFieldLinks((int)$attachment['id']),
            ], $attachmentRows),
            'audit' => array_map(fn(array $audit): array => [
                'id' => (int)$audit['id'], 'fromState' => (string)$audit['from_state'], 'toState' => (string)$audit['to_state'],
                'actorUid' => (string)$audit['actor_uid'], 'details' => $this->decode((string)$audit['details_json']), 'changedAt' => (string)$audit['changed_at'],
            ], $auditRows),
            'version' => (int)$row['version'],
        ];
    }

    public function assignInboxMessage(int $messageId, int $applicationId, int $expectedVersion, string $actorUid, array $hiringDefaults = [], array $applicationDefaults = []): array {
        return $this->transitionInboxMessage($messageId, 'assigned', $expectedVersion, $actorUid, $applicationId, ['new', 'unclear', 'assigned'], $hiringDefaults, $applicationDefaults);
    }

    public function inboxJobExists(int $jobId): bool {
        $qb = $this->db->getQueryBuilder();
        return $qb
            ->select('id')
            ->from('rec_jobs')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($jobId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('active', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne() !== false;
    }

    public function createAndAssignInboxApplication(
        int $messageId,
        int $expectedVersion,
        string $actorUid,
        array $person,
        array $application,
        array $hiringDefaults = [],
        array $applicationDefaults = [],
    ): array {
        $this->db->beginTransaction();
        try {
            if (!$this->inboxJobExists((int)$application['jobId'])) {
                throw new ValidationException('Die ausgewählte Stelle ist nicht verfügbar.');
            }
            $personId = $this->createPerson($person);
            $applicationId = $this->createApplication(['personId' => $personId] + $application);
            $createdApplicationFields = [];
            if (($application['desiredWeeklyHours'] ?? null) !== null) $createdApplicationFields[] = 'desiredWeeklyHours';
            if (($application['desiredWeeklyHoursMax'] ?? null) !== null) $createdApplicationFields[] = 'desiredWeeklyHoursMax';
            unset($applicationDefaults['desiredWeeklyHours'], $applicationDefaults['desiredWeeklyHoursMax']);
            $message = $this->transitionInboxMessage(
                $messageId,
                'assigned',
                $expectedVersion,
                $actorUid,
                $applicationId,
                ['new', 'unclear'],
                $hiringDefaults,
                $applicationDefaults,
                false,
                ['personId' => $personId, 'createdApplicationFields' => $createdApplicationFields],
            );
            $this->db->commit();
            return ['personId' => $personId, 'applicationId' => $applicationId, 'message' => $message];
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function ignoreInboxMessage(int $messageId, int $expectedVersion, string $actorUid): array {
        return $this->transitionInboxMessage($messageId, 'ignored', $expectedVersion, $actorUid, null, ['new', 'unclear', 'error']);
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
            'desired_weekly_hours' => [
                $application['desiredWeeklyHours'],
                $application['desiredWeeklyHours'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR,
            ],
            'desired_weekly_hours_max' => [
                $application['desiredWeeklyHoursMax'],
                $application['desiredWeeklyHoursMax'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR,
            ],
            'closure_reason' => ['', IQueryBuilder::PARAM_STR],
            'retention_state' => ['active', IQueryBuilder::PARAM_STR],
            'area_key' => ['', IQueryBuilder::PARAM_STR],
            'first_guide_access' => [false, IQueryBuilder::PARAM_BOOL],
            'version' => [1, IQueryBuilder::PARAM_INT],
        ]);
    }

    public function overview(): array {
        return [
            'jobs' => array_map([$this, 'mapJob'], $this->all(
                'rec_jobs',
                ['id', 'internal_title', 'public_title', 'active', 'responsible_users', 'responsible_groups', 'assignment_key', 'basis_qualification_required', 'profession_category', 'contract_term', 'pay_grade', 'advertised_weekly_hours', 'full_time_weekly_hours', 'vacation_days', 'work_location', 'version'],
                ['internal_title' => 'ASC'],
            )),
            'people' => array_map([$this, 'mapPerson'], $this->all(
                'rec_people',
                ['id', 'given_name', 'family_name', 'email', 'phone', 'version'],
                ['family_name' => 'ASC', 'given_name' => 'ASC'],
            )),
            'applications' => array_map([$this, 'mapApplicationSummary'], $this->all(
                'rec_applications',
                ['id', 'person_id', 'job_id', 'source', 'received_on', 'status', 'assignee_uid', 'desired_weekly_hours', 'desired_weekly_hours_max', 'closure_reason', 'retention_state', 'area_key', 'first_guide_access', 'version'],
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
            'application' => $this->publicApplication($application),
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

    /** @return array{application: array<string,mixed>, person: array<string,mixed>, job: array<string,mixed>} */
    public function hiringContext(int $id): array {
        $application = $this->findApplication($id);
        $person = $this->findRow('rec_people', (int)$application['personId']);
        $job = $this->findRow('rec_jobs', (int)$application['jobId']);
        if ($person === null || $job === null) {
            throw new NotFoundException('Die Bewerbung ist unvollständig.');
        }
        return [
            'application' => $application,
            'person' => $this->mapPerson($person),
            'job' => $this->mapJob($job),
        ];
    }

    public function findApplication(int $id): array {
        $row = $this->findRow('rec_applications', $id);
        if ($row === null) {
            throw new NotFoundException('Die Bewerbung wurde nicht gefunden.');
        }
        return $this->mapApplicationWithBasisQualification($row);
    }

    public function statusMailPreparation(int $id, string $fromStatus, string $toStatus): ?array {
        $ruleQb = $this->db->getQueryBuilder();
        $rule = $ruleQb->select('template_id', 'default_timing')->from('rec_status_mail_rules')
            ->where($ruleQb->expr()->eq('from_status', $ruleQb->createNamedParameter($fromStatus, IQueryBuilder::PARAM_STR)))
            ->andWhere($ruleQb->expr()->eq('to_status', $ruleQb->createNamedParameter($toStatus, IQueryBuilder::PARAM_STR)))
            ->andWhere($ruleQb->expr()->eq('enabled', $ruleQb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
            ->executeQuery()->fetchAssociative();
        if ($rule === false) return null;

        $template = $this->findRow('rec_mail_templates', (int)$rule['template_id']);
        if ($template === null || !(bool)$template['active']) return null;
        $revisionQb = $this->db->getQueryBuilder();
        $revision = $revisionQb->select('subject_template', 'body_template', 'body_format')->from('rec_mail_template_revisions')
            ->where($revisionQb->expr()->eq('template_id', $revisionQb->createNamedParameter((int)$template['id'], IQueryBuilder::PARAM_INT)))
            ->andWhere($revisionQb->expr()->eq('revision', $revisionQb->createNamedParameter((int)$template['current_revision'], IQueryBuilder::PARAM_INT)))
            ->executeQuery()->fetchAssociative();
        $application = $this->findApplication($id);
        $person = $this->findRow('rec_people', (int)$application['personId']);
        $job = $this->findRow('rec_jobs', (int)$application['jobId']);
        if ($revision === false || $person === null || $job === null) {
            throw new NotFoundException('Die konfigurierte Mailvorlage oder ihr Bewerbungskontext fehlt.');
        }
        $publicJobTitle = trim((string)$job['public_title']);
        return [
            'template' => [
                'id' => (int)$template['id'], 'revision' => (int)$template['current_revision'],
                'subject' => (string)$revision['subject_template'], 'body' => (string)$revision['body_template'],
                'bodyFormat' => (string)($revision['body_format'] ?? 'plain'),
            ],
            'context' => [
                'given_name' => (string)$person['given_name'], 'family_name' => (string)$person['family_name'],
                'job_title' => $publicJobTitle !== '' ? $publicJobTitle : (string)$job['internal_title'],
            ],
            'recipient' => (string)$person['email'],
            'defaultTiming' => (string)$rule['default_timing'],
        ];
    }

    public function applicationForInterview(int $id): array {
        $interview = $this->interview($id);
        return $this->findApplication((int)$interview['applicationId']);
    }

    /** @return array{data: array<string,mixed>, version: int} */
    public function hiringData(int $applicationId): array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb
            ->select('data_json', 'version')
            ->from('rec_hiring_data')
            ->where($qb->expr()->eq(
                'application_id',
                $qb->createNamedParameter($applicationId, IQueryBuilder::PARAM_INT),
            ))
            ->executeQuery()
            ->fetchAssociative();
        if ($row === false) return ['data' => [], 'version' => 0];
        return ['data' => $this->decode((string)$row['data_json']), 'version' => (int)$row['version']];
    }

    /** @param array<string,mixed> $data
     *  @return array{data: array<string,mixed>, version: int}
     */
    public function saveHiringData(int $applicationId, array $data, int $expectedVersion, string $actorUid): array {
        $this->db->beginTransaction();
        try {
            $existing = $this->hiringData($applicationId);
            if ($existing['version'] === 0) {
                if ($expectedVersion !== 0) throw new ConflictException('Die Einstellungsdaten wurden zwischenzeitlich geändert.');
                $this->insert('rec_hiring_data', [
                    'application_id' => [$applicationId, IQueryBuilder::PARAM_INT],
                    'data_json' => [$this->encode($data), IQueryBuilder::PARAM_STR],
                    'version' => [1, IQueryBuilder::PARAM_INT],
                ]);
            } else {
                $qb = $this->db->getQueryBuilder();
                $affected = $qb
                    ->update('rec_hiring_data')
                    ->set('data_json', $qb->createNamedParameter($this->encode($data), IQueryBuilder::PARAM_STR))
                    ->set('version', $qb->createFunction('version + 1'))
                    ->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                    ->where($qb->expr()->eq('application_id', $qb->createNamedParameter($applicationId, IQueryBuilder::PARAM_INT)))
                    ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)))
                    ->executeStatement();
                if ($affected !== 1) throw new ConflictException('Die Einstellungsdaten wurden zwischenzeitlich geändert.');
            }
            $this->insertAudit($actorUid, 'hiring_data_saved', '', $applicationId, ['fields' => array_keys($data)]);
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            if ($expectedVersion === 0 && $this->hiringData($applicationId)['version'] > 0) {
                throw new ConflictException('Die Einstellungsdaten wurden zwischenzeitlich geändert.');
            }
            throw $error;
        }
        return $this->hiringData($applicationId);
    }

    /** @return list<array<string,mixed>> */
    public function releasedHiringApplications(): array {
        $qb = $this->db->getQueryBuilder();
        $rows = $qb
            ->select('id', 'person_id', 'job_id', 'source', 'received_on', 'status', 'assignee_uid', 'closure_reason', 'retention_state', 'area_key', 'first_guide_access', 'version')
            ->from('rec_applications')
            ->where($qb->expr()->in(
                'status',
                $qb->createNamedParameter(['approved_for_hire', 'hired'], IQueryBuilder::PARAM_STR_ARRAY),
            ))
            ->orderBy('received_on', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
        return array_map([$this, 'mapApplicationWithBasisQualification'], $rows);
    }

    /** @return array<string,mixed> */
    public function setFirstGuideAccess(int $applicationId, bool $enabled, int $expectedVersion, string $actorUid): array {
        $now = $this->now();
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $affected = $qb
                ->update('rec_applications')
                ->set('first_guide_access', $qb->createNamedParameter($enabled, IQueryBuilder::PARAM_BOOL))
                ->set('version', $qb->createFunction('version + 1'))
                ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($applicationId, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->in(
                    'status',
                    $qb->createNamedParameter(['approved_for_hire', 'hired'], IQueryBuilder::PARAM_STR_ARRAY),
                ))
                ->executeStatement();
            if ($affected !== 1) throw new ConflictException('Die Erstbegleitungsfreigabe wurde zwischenzeitlich geändert oder ist nicht zulässig.');
            $this->insertAudit($actorUid, $enabled ? 'first_guide_enabled' : 'first_guide_disabled', '', $applicationId, []);
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
        return $this->findApplication($applicationId);
    }

    /** @param array<string,mixed> $details */
    public function recordPermissionAudit(string $actorUid, string $action, string $subjectUid, ?int $applicationId, array $details): void {
        $this->insertAudit($actorUid, $action, $subjectUid, $applicationId, $details);
    }

    /** @return array{application: array<string,mixed>, job: array<string,mixed>} */
    public function basisQualificationContext(int $applicationId): array {
        $application = $this->findApplication($applicationId);
        $job = $this->findRow('rec_jobs', (int)$application['jobId']);
        if ($job === null) throw new NotFoundException('Die Stelle wurde nicht gefunden.');
        return ['application' => $application, 'job' => $this->mapJob($job)];
    }

    /** @return array<string,mixed> */
    public function basisQualificationJob(int $jobId): array {
        $row = $this->findRow('rec_jobs', $jobId);
        if ($row === null) throw new NotFoundException('Die Stelle wurde nicht gefunden.');
        return $this->mapJob($row);
    }

    /** @return array<string,mixed> */
    public function setJobBasisQualificationRequired(
        int $jobId,
        bool $required,
        int $expectedVersion,
        string $actorUid,
    ): array {
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $affected = $qb
                ->update('rec_jobs')
                ->set('basis_qualification_required', $qb->createNamedParameter($required, IQueryBuilder::PARAM_BOOL))
                ->set('version', $qb->createFunction('version + 1'))
                ->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($jobId, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)))
                ->executeStatement();
            if ($affected !== 1) throw new ConflictException('Die Stelle wurde zwischenzeitlich geändert.');
            $this->insertAudit($actorUid, 'basis_qualification_job_requirement_saved', '', null, ['jobId' => $jobId]);
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
        return $this->basisQualificationJob($jobId);
    }

    /** @return array<string,mixed> */
    public function basisQualificationRun(int $runId): array {
        $row = $this->findRow('rec_bq_runs', $runId);
        if ($row === null) throw new NotFoundException('Der BQ-Durchlauf wurde nicht gefunden.');
        return $this->mapBasisQualificationRun($row);
    }

    /** @return list<array<string,mixed>> */
    public function basisQualificationRuns(): array {
        return array_map([$this, 'mapBasisQualificationRun'], $this->all(
            'rec_bq_runs',
            ['id', 'label', 'starts_on', 'ends_on', 'actor_uid', 'version', 'created_at', 'updated_at'],
            ['starts_on' => 'DESC', 'id' => 'DESC'],
        ));
    }

    /** @return array<string,mixed>|null */
    public function activeBasisQualificationAssignment(int $applicationId): ?array {
        $assignment = $this->latestBasisQualificationAssignment($applicationId);
        if ($assignment === null || !in_array((string)$assignment['result'], ['pending', 'suitable'], true)) return null;
        return $assignment;
    }

    /** @param array<string,mixed> $run */
    public function createBasisQualificationRun(array $run): int {
        $this->db->beginTransaction();
        try {
            $runId = $this->insert('rec_bq_runs', [
                'label' => [(string)$run['label'], IQueryBuilder::PARAM_STR],
                'starts_on' => [new DateTimeImmutable((string)$run['startsOn']), IQueryBuilder::PARAM_DATE_IMMUTABLE],
                'ends_on' => [new DateTimeImmutable((string)$run['endsOn']), IQueryBuilder::PARAM_DATE_IMMUTABLE],
                'actor_uid' => [(string)$run['actorUid'], IQueryBuilder::PARAM_STR],
                'version' => [1, IQueryBuilder::PARAM_INT],
            ]);
            $this->insertAudit((string)$run['actorUid'], 'basis_qualification_run_created', '', null, ['runId' => $runId]);
            $this->db->commit();
            return $runId;
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    public function assignBasisQualification(
        int $applicationId,
        int $runId,
        int $expectedApplicationVersion,
        string $actorUid,
    ): array {
        $now = $this->now();
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $affected = $qb
                ->update('rec_applications')
                ->set('status', $qb->createNamedParameter('basis_qualification', IQueryBuilder::PARAM_STR))
                ->set('version', $qb->createFunction('version + 1'))
                ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($applicationId, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('decision_pending', IQueryBuilder::PARAM_STR)))
                ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedApplicationVersion, IQueryBuilder::PARAM_INT)))
                ->executeStatement();
            if ($affected !== 1) throw new ConflictException('Die Bewerbung wurde zwischenzeitlich geändert oder bereits einer BQ zugeordnet.');

            $assignmentId = $this->insert('rec_bq_assignments', [
                'application_id' => [$applicationId, IQueryBuilder::PARAM_INT],
                'run_id' => [$runId, IQueryBuilder::PARAM_INT],
                'result' => ['pending', IQueryBuilder::PARAM_STR],
                'evaluation_note' => ['', IQueryBuilder::PARAM_STR],
                'actor_uid' => [$actorUid, IQueryBuilder::PARAM_STR],
                'version' => [1, IQueryBuilder::PARAM_INT],
                'evaluated_at' => [null, IQueryBuilder::PARAM_NULL],
            ]);
            $this->insert('rec_status_log', [
                'application_id' => [$applicationId, IQueryBuilder::PARAM_INT],
                'from_status' => ['decision_pending', IQueryBuilder::PARAM_STR],
                'to_status' => ['basis_qualification', IQueryBuilder::PARAM_STR],
                'actor_uid' => [$actorUid, IQueryBuilder::PARAM_STR],
                'changed_at' => [$now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            ], false);
            $this->insertAudit($actorUid, 'basis_qualification_assigned', '', $applicationId, ['runId' => $runId]);
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
        return $this->basisQualificationAssignment($assignmentId);
    }

    /** @return array<string,mixed> */
    public function basisQualificationAssignment(int $assignmentId): array {
        $row = $this->findRow('rec_bq_assignments', $assignmentId);
        if ($row === null) throw new NotFoundException('Die BQ-Zuordnung wurde nicht gefunden.');
        return $this->mapBasisQualificationAssignment($row);
    }

    /** @return list<array<string,mixed>> */
    public function basisQualificationAssignments(int $applicationId): array {
        $qb = $this->db->getQueryBuilder();
        $rows = $qb
            ->select('id', 'application_id', 'run_id', 'result', 'evaluation_note', 'actor_uid', 'version', 'evaluated_at', 'created_at', 'updated_at')
            ->from('rec_bq_assignments')
            ->where($qb->expr()->eq('application_id', $qb->createNamedParameter($applicationId, IQueryBuilder::PARAM_INT)))
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
        return array_map([$this, 'mapBasisQualificationAssignment'], $rows);
    }

    /** @return array<string,mixed> */
    public function recordBasisQualificationResult(
        int $assignmentId,
        string $result,
        string $note,
        int $expectedVersion,
        string $actorUid,
    ): array {
        $assignment = $this->basisQualificationAssignment($assignmentId);
        $now = $this->now();
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $affected = $qb
                ->update('rec_bq_assignments')
                ->set('result', $qb->createNamedParameter($result, IQueryBuilder::PARAM_STR))
                ->set('evaluation_note', $qb->createNamedParameter($note, IQueryBuilder::PARAM_STR))
                ->set('actor_uid', $qb->createNamedParameter($actorUid, IQueryBuilder::PARAM_STR))
                ->set('evaluated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->set('version', $qb->createFunction('version + 1'))
                ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($assignmentId, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)))
                ->executeStatement();
            if ($affected !== 1) throw new ConflictException('Die BQ-Bewertung wurde zwischenzeitlich geändert.');
            $this->insertAudit($actorUid, 'basis_qualification_result_recorded', '', (int)$assignment['applicationId'], [
                'assignmentId' => $assignmentId,
            ]);
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
        return $this->basisQualificationAssignment($assignmentId);
    }

    /** @return array<string,mixed> */
    public function mailConfiguration(): array {
        $templateRows = $this->all('rec_mail_templates', ['*'], ['name' => 'ASC']);
        return [
            'templates' => array_map(fn(array $row): array => $this->mailTemplate((int)$row['id']), $templateRows),
            'rules' => array_map([$this, 'mapStatusMailRule'], $this->all('rec_status_mail_rules', ['*'], ['from_status' => 'ASC', 'to_status' => 'ASC'])),
            'textBlocks' => array_map([$this, 'mapMailTextBlock'], $this->all('rec_mail_text_blocks', ['*'], ['label' => 'ASC'])),
        ];
    }

    public function createMailTemplate(string $name, string $subject, string $body, string $bodyFormat, string $actorUid): array {
        $this->db->beginTransaction();
        try {
            $id = $this->insert('rec_mail_templates', [
                'name' => [$name, IQueryBuilder::PARAM_STR], 'active' => [true, IQueryBuilder::PARAM_BOOL],
                'current_revision' => [1, IQueryBuilder::PARAM_INT], 'version' => [1, IQueryBuilder::PARAM_INT],
                'actor_uid' => [$actorUid, IQueryBuilder::PARAM_STR],
            ]);
            $this->insert('rec_mail_template_revisions', [
                'template_id' => [$id, IQueryBuilder::PARAM_INT], 'revision' => [1, IQueryBuilder::PARAM_INT],
                'subject_template' => [$subject, IQueryBuilder::PARAM_STR], 'body_template' => [$body, IQueryBuilder::PARAM_STR],
                'body_format' => [$bodyFormat, IQueryBuilder::PARAM_STR],
                'actor_uid' => [$actorUid, IQueryBuilder::PARAM_STR], 'created_at' => [$this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            ], false);
            $this->db->commit();
        } catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
        return $this->mailTemplate($id);
    }

    public function reviseMailTemplate(int $id, string $name, string $subject, string $body, string $bodyFormat, bool $active, int $expectedVersion, string $actorUid): array {
        $template = $this->mailTemplate($id); $revision = (int)$template['revision'] + 1; $now = $this->now();
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $affected = $qb->update('rec_mail_templates')->set('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR))
                ->set('active', $qb->createNamedParameter($active, IQueryBuilder::PARAM_BOOL))
                ->set('current_revision', $qb->createNamedParameter($revision, IQueryBuilder::PARAM_INT))
                ->set('version', $qb->createFunction('version + 1'))->set('actor_uid', $qb->createNamedParameter($actorUid, IQueryBuilder::PARAM_STR))
                ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)))->executeStatement();
            if ($affected !== 1) throw new ConflictException('Die Mailvorlage wurde zwischenzeitlich geändert.');
            $this->insert('rec_mail_template_revisions', [
                'template_id' => [$id, IQueryBuilder::PARAM_INT], 'revision' => [$revision, IQueryBuilder::PARAM_INT],
                'subject_template' => [$subject, IQueryBuilder::PARAM_STR], 'body_template' => [$body, IQueryBuilder::PARAM_STR],
                'body_format' => [$bodyFormat, IQueryBuilder::PARAM_STR],
                'actor_uid' => [$actorUid, IQueryBuilder::PARAM_STR], 'created_at' => [$now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            ], false);
            $this->db->commit();
        } catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
        return $this->mailTemplate($id);
    }

    public function mailTemplate(int $id): array {
        $row = $this->findRow('rec_mail_templates', $id); if ($row === null) throw new NotFoundException('Die Mailvorlage wurde nicht gefunden.');
        $qb = $this->db->getQueryBuilder();
        $revision = $qb->select('subject_template', 'body_template', 'body_format')->from('rec_mail_template_revisions')
            ->where($qb->expr()->eq('template_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('revision', $qb->createNamedParameter((int)$row['current_revision'], IQueryBuilder::PARAM_INT)))
            ->executeQuery()->fetchAssociative();
        if ($revision === false) throw new NotFoundException('Die aktuelle Mailvorlagenrevision wurde nicht gefunden.');
        return $this->mapMailTemplate($row) + [
            'subject' => (string)$revision['subject_template'], 'body' => (string)$revision['body_template'],
            'bodyFormat' => (string)($revision['body_format'] ?? 'plain'),
        ];
    }

    public function saveStatusMailRule(string $fromStatus, string $toStatus, int $templateId, bool $enabled, string $timing, int $expectedVersion, string $actorUid): array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb->select('id', 'version')->from('rec_status_mail_rules')
            ->where($qb->expr()->eq('from_status', $qb->createNamedParameter($fromStatus, IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->eq('to_status', $qb->createNamedParameter($toStatus, IQueryBuilder::PARAM_STR)))->executeQuery()->fetchAssociative();
        if ($row === false) {
            if ($expectedVersion !== 0) throw new ConflictException('Die Statusmail-Regel wurde zwischenzeitlich geändert.');
            $id = $this->insert('rec_status_mail_rules', [
                'from_status' => [$fromStatus, IQueryBuilder::PARAM_STR], 'to_status' => [$toStatus, IQueryBuilder::PARAM_STR],
                'template_id' => [$templateId, IQueryBuilder::PARAM_INT], 'enabled' => [$enabled, IQueryBuilder::PARAM_BOOL],
                'default_timing' => [$timing, IQueryBuilder::PARAM_STR], 'version' => [1, IQueryBuilder::PARAM_INT], 'actor_uid' => [$actorUid, IQueryBuilder::PARAM_STR],
            ]);
        } else {
            $id = (int)$row['id']; $update = $this->db->getQueryBuilder();
            $update->update('rec_status_mail_rules')->set('template_id', $update->createNamedParameter($templateId, IQueryBuilder::PARAM_INT))
                ->set('enabled', $update->createNamedParameter($enabled, IQueryBuilder::PARAM_BOOL))->set('default_timing', $update->createNamedParameter($timing, IQueryBuilder::PARAM_STR))
                ->set('version', $update->createFunction('version + 1'))->set('actor_uid', $update->createNamedParameter($actorUid, IQueryBuilder::PARAM_STR))
                ->set('updated_at', $update->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($update->expr()->eq('id', $update->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
                ->andWhere($update->expr()->eq('version', $update->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)));
            if ($update->executeStatement() !== 1) throw new ConflictException('Die Statusmail-Regel wurde zwischenzeitlich geändert.');
        }
        return $this->mapStatusMailRule($this->findRow('rec_status_mail_rules', $id) ?? []);
    }

    public function createMailTextBlock(string $label, string $text, string $actorUid): array {
        $id = $this->insert('rec_mail_text_blocks', [
            'label' => [$label, IQueryBuilder::PARAM_STR], 'insert_text' => [$text, IQueryBuilder::PARAM_STR],
            'active' => [true, IQueryBuilder::PARAM_BOOL], 'version' => [1, IQueryBuilder::PARAM_INT], 'actor_uid' => [$actorUid, IQueryBuilder::PARAM_STR],
        ]);
        return $this->mapMailTextBlock($this->findRow('rec_mail_text_blocks', $id) ?? []);
    }

    /** @return list<array<string,mixed>> */
    public function mailDrafts(int $applicationId): array {
        $qb = $this->db->getQueryBuilder();
        $rows = $qb->select('*')->from('rec_mail_drafts')->where($qb->expr()->eq('application_id', $qb->createNamedParameter($applicationId, IQueryBuilder::PARAM_INT)))
            ->orderBy('created_at', 'DESC')->executeQuery()->fetchAllAssociative();
        return array_map([$this, 'mapMailDraft'], $rows);
    }

    public function mailDraft(int $id): array { $row = $this->findRow('rec_mail_drafts', $id); if ($row === null) throw new NotFoundException('Der Mailentwurf wurde nicht gefunden.'); return $this->mapMailDraft($row); }

    public function saveMailDraft(int $id, string $subject, string $body, string $bodyFormat, int $expectedVersion): array {
        $qb = $this->db->getQueryBuilder();
        $affected = $qb->update('rec_mail_drafts')->set('subject', $qb->createNamedParameter($subject, IQueryBuilder::PARAM_STR))
            ->set('body', $qb->createNamedParameter($body, IQueryBuilder::PARAM_STR))
            ->set('body_format', $qb->createNamedParameter($bodyFormat, IQueryBuilder::PARAM_STR))->set('version', $qb->createFunction('version + 1'))
            ->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('state', $qb->createNamedParameter('draft', IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)))->executeStatement();
        if ($affected !== 1) throw new ConflictException('Der Mailentwurf wurde zwischenzeitlich geändert oder bereits freigegeben.');
        return $this->mailDraft($id);
    }

    public function approveMailDraft(int $id, array $approved, int $expectedVersion, string $actorUid, string $jobKey): array {
        $now = $this->now(); $scheduledAt = new DateTimeImmutable((string)$approved['scheduledAt']);
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $affected = $qb->update('rec_mail_drafts')->set('subject', $qb->createNamedParameter((string)$approved['subject'], IQueryBuilder::PARAM_STR))
                ->set('body', $qb->createNamedParameter((string)$approved['body'], IQueryBuilder::PARAM_STR))
                ->set('body_format', $qb->createNamedParameter((string)$approved['bodyFormat'], IQueryBuilder::PARAM_STR))
                ->set('intended_recipient', $qb->createNamedParameter((string)$approved['intendedRecipient'], IQueryBuilder::PARAM_STR))
                ->set('delivery_recipient', $qb->createNamedParameter((string)$approved['deliveryRecipient'], IQueryBuilder::PARAM_STR))
                ->set('test_mode', $qb->createNamedParameter((bool)$approved['testMode'], IQueryBuilder::PARAM_BOOL))
                ->set('scheduled_at', $qb->createNamedParameter($scheduledAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->set('state', $qb->createNamedParameter('approved', IQueryBuilder::PARAM_STR))->set('approved_by', $qb->createNamedParameter($actorUid, IQueryBuilder::PARAM_STR))
                ->set('approved_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))->set('version', $qb->createFunction('version + 1'))
                ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->eq('state', $qb->createNamedParameter('draft', IQueryBuilder::PARAM_STR)))
                ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)))->executeStatement();
            if ($affected !== 1) throw new ConflictException('Der Mailentwurf wurde zwischenzeitlich geändert oder bereits freigegeben.');
            $this->insert('rec_mail_outbox', [
                'draft_id' => [$id, IQueryBuilder::PARAM_INT], 'job_key' => [$jobKey, IQueryBuilder::PARAM_STR], 'state' => ['pending', IQueryBuilder::PARAM_STR],
                'attempts' => [0, IQueryBuilder::PARAM_INT], 'scheduled_at' => [$scheduledAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
                'last_error_code' => [null, IQueryBuilder::PARAM_NULL], 'sent_at' => [null, IQueryBuilder::PARAM_NULL],
            ]);
            $this->db->commit();
        } catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
        return $this->mailDraft($id);
    }

    public function cancelMailDraft(int $id, int $expectedVersion): array {
        $qb = $this->db->getQueryBuilder();
        $affected = $qb->update('rec_mail_drafts')->set('state', $qb->createNamedParameter('cancelled', IQueryBuilder::PARAM_STR))
            ->set('version', $qb->createFunction('version + 1'))->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('state', $qb->createNamedParameter('draft', IQueryBuilder::PARAM_STR)))
            ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)))->executeStatement();
        if ($affected !== 1) throw new ConflictException('Der Mailentwurf wurde zwischenzeitlich geändert oder bereits freigegeben.');
        return $this->mailDraft($id);
    }

    public function claimDueMailJob(DateTimeImmutable $now): ?array {
        $staleBefore = $now->modify('-15 minutes');
        $stale = $this->db->getQueryBuilder();
        $interrupted = $stale->select('id', 'draft_id', 'attempts')->from('rec_mail_outbox')
            ->where($stale->expr()->eq('state', $stale->createNamedParameter('sending', IQueryBuilder::PARAM_STR)))
            ->andWhere($stale->expr()->lte('updated_at', $stale->createNamedParameter($staleBefore, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
            ->executeQuery()->fetchAllAssociative();
        foreach ($interrupted as $row) {
            $recover = $this->db->getQueryBuilder();
            $affected = $recover->update('rec_mail_outbox')
                ->set('state', $recover->createNamedParameter('failed', IQueryBuilder::PARAM_STR))
                ->set('last_error_code', $recover->createNamedParameter('worker_interrupted', IQueryBuilder::PARAM_STR))
                ->set('scheduled_at', $recover->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->set('updated_at', $recover->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($recover->expr()->eq('id', $recover->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)))
                ->andWhere($recover->expr()->eq('state', $recover->createNamedParameter('sending', IQueryBuilder::PARAM_STR)))
                ->andWhere($recover->expr()->lte('updated_at', $recover->createNamedParameter($staleBefore, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
                ->executeStatement();
            if ($affected === 1 && (int)$row['attempts'] >= 5) {
                $draft = $this->db->getQueryBuilder();
                $draft->update('rec_mail_drafts')->set('state', $draft->createNamedParameter('failed', IQueryBuilder::PARAM_STR))
                    ->set('version', $draft->createFunction('version + 1'))
                    ->set('updated_at', $draft->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                    ->where($draft->expr()->eq('id', $draft->createNamedParameter((int)$row['draft_id'], IQueryBuilder::PARAM_INT)))
                    ->executeStatement();
            }
        }
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $select = $this->db->getQueryBuilder();
            $row = $select->select('id', 'draft_id', 'attempts')->from('rec_mail_outbox')
                ->where($select->expr()->in('state', $select->createNamedParameter(['pending', 'failed'], IQueryBuilder::PARAM_STR_ARRAY)))
                ->andWhere($select->expr()->lte('scheduled_at', $select->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE)))
                ->andWhere($select->expr()->lt('attempts', $select->createNamedParameter(5, IQueryBuilder::PARAM_INT)))
                ->orderBy('scheduled_at', 'ASC')->addOrderBy('id', 'ASC')->setMaxResults(1)->executeQuery()->fetchAssociative();
            if ($row === false) return null;
            $update = $this->db->getQueryBuilder();
            $affected = $update->update('rec_mail_outbox')->set('state', $update->createNamedParameter('sending', IQueryBuilder::PARAM_STR))
                ->set('attempts', $update->createFunction('attempts + 1'))->set('updated_at', $update->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($update->expr()->eq('id', $update->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)))
                ->andWhere($update->expr()->in('state', $update->createNamedParameter(['pending', 'failed'], IQueryBuilder::PARAM_STR_ARRAY)))
                ->andWhere($update->expr()->eq('attempts', $update->createNamedParameter((int)$row['attempts'], IQueryBuilder::PARAM_INT)))->executeStatement();
            if ($affected !== 1) continue;
            $draft = $this->mailDraft((int)$row['draft_id']);
            return [
                'id' => (int)$row['id'], 'draftId' => (int)$row['draft_id'], 'attempts' => (int)$row['attempts'] + 1,
                'recipient' => (string)$draft['deliveryRecipient'], 'subject' => (string)$draft['subject'], 'body' => (string)$draft['body'],
                'bodyFormat' => (string)$draft['bodyFormat'],
            ];
        }
        return null;
    }

    public function markMailJobSent(int $jobId, int $draftId, DateTimeImmutable $sentAt): void {
        $this->db->beginTransaction();
        try {
            $job = $this->db->getQueryBuilder();
            $affected = $job->update('rec_mail_outbox')->set('state', $job->createNamedParameter('sent', IQueryBuilder::PARAM_STR))
                ->set('sent_at', $job->createNamedParameter($sentAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->set('updated_at', $job->createNamedParameter($sentAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($job->expr()->eq('id', $job->createNamedParameter($jobId, IQueryBuilder::PARAM_INT)))
                ->andWhere($job->expr()->eq('state', $job->createNamedParameter('sending', IQueryBuilder::PARAM_STR)))->executeStatement();
            if ($affected !== 1) throw new ConflictException('Der Versandauftrag ist nicht mehr im erwarteten Zustand.');
            $draft = $this->db->getQueryBuilder();
            $draft->update('rec_mail_drafts')->set('state', $draft->createNamedParameter('sent', IQueryBuilder::PARAM_STR))
                ->set('version', $draft->createFunction('version + 1'))->set('updated_at', $draft->createNamedParameter($sentAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($draft->expr()->eq('id', $draft->createNamedParameter($draftId, IQueryBuilder::PARAM_INT)))->executeStatement();
            $this->db->commit();
        } catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
    }

    public function markMailJobFailed(int $jobId, int $draftId, string $errorCode, DateTimeImmutable $retryAt): void {
        $this->db->beginTransaction();
        try {
            $job = $this->db->getQueryBuilder();
            $affected = $job->update('rec_mail_outbox')->set('state', $job->createNamedParameter('failed', IQueryBuilder::PARAM_STR))
                ->set('last_error_code', $job->createNamedParameter(substr($errorCode, 0, 64), IQueryBuilder::PARAM_STR))
                ->set('scheduled_at', $job->createNamedParameter($retryAt, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->set('updated_at', $job->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($job->expr()->eq('id', $job->createNamedParameter($jobId, IQueryBuilder::PARAM_INT)))
                ->andWhere($job->expr()->eq('state', $job->createNamedParameter('sending', IQueryBuilder::PARAM_STR)))->executeStatement();
            if ($affected !== 1) throw new ConflictException('Der Versandauftrag ist nicht mehr im erwarteten Zustand.');
            $draft = $this->db->getQueryBuilder();
            $draft->update('rec_mail_drafts')->set('state', $draft->createNamedParameter('failed', IQueryBuilder::PARAM_STR))
                ->set('version', $draft->createFunction('version + 1'))->set('updated_at', $draft->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($draft->expr()->eq('id', $draft->createNamedParameter($draftId, IQueryBuilder::PARAM_INT)))->executeStatement();
            $this->db->commit();
        } catch (\Throwable $error) { $this->db->rollBack(); throw $error; }
    }

    public function transitionStatus(
        int $id,
        string $fromStatus,
        string $toStatus,
        int $expectedVersion,
        string $actorUid,
        ?string $areaKey,
        bool $enableFirstGuideAccess,
        ?array $mailDraft = null,
        bool $override = false,
    ): array {
        $now = $this->now();
        $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $qb
                ->update('rec_applications')
                ->set('status', $qb->createNamedParameter($toStatus, IQueryBuilder::PARAM_STR))
                ->set('version', $qb->createFunction('version + 1'))
                ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($fromStatus, IQueryBuilder::PARAM_STR)))
                ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)));
            if ($areaKey !== null) {
                $qb
                    ->set('area_key', $qb->createNamedParameter($areaKey, IQueryBuilder::PARAM_STR))
                    ->set('first_guide_access', $qb->createNamedParameter($enableFirstGuideAccess, IQueryBuilder::PARAM_BOOL));
            }
            $affected = $qb->executeStatement();
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
            if ($override) {
                $this->insertAudit($actorUid, 'status_transition_overridden', '', $id, [
                    'fromStatus' => $fromStatus,
                    'toStatus' => $toStatus,
                ]);
            }
            if ($areaKey !== null && $enableFirstGuideAccess) {
                $this->insertAudit($actorUid, 'first_guide_enabled', '', $id, [
                    'areaKey' => $areaKey,
                    'source' => 'hire_approval',
                ]);
            }
            $mailDraftId = null;
            if ($mailDraft !== null) {
                $mailDraftId = $this->insert('rec_mail_drafts', [
                    'application_id' => [$id, IQueryBuilder::PARAM_INT],
                    'from_status' => [(string)$mailDraft['fromStatus'], IQueryBuilder::PARAM_STR],
                    'to_status' => [(string)$mailDraft['toStatus'], IQueryBuilder::PARAM_STR],
                    'template_id' => [(int)$mailDraft['templateId'], IQueryBuilder::PARAM_INT],
                    'template_revision' => [(int)$mailDraft['templateRevision'], IQueryBuilder::PARAM_INT],
                    'original_recipient' => [(string)$mailDraft['originalRecipient'], IQueryBuilder::PARAM_STR],
                    'intended_recipient' => [null, IQueryBuilder::PARAM_NULL],
                    'delivery_recipient' => [null, IQueryBuilder::PARAM_NULL],
                    'subject' => [(string)$mailDraft['subject'], IQueryBuilder::PARAM_STR],
                    'body' => [(string)$mailDraft['body'], IQueryBuilder::PARAM_STR],
                    'body_format' => [(string)$mailDraft['bodyFormat'], IQueryBuilder::PARAM_STR],
                    'state' => ['draft', IQueryBuilder::PARAM_STR],
                    'default_timing' => [(string)$mailDraft['defaultTiming'], IQueryBuilder::PARAM_STR],
                    'test_mode' => [false, IQueryBuilder::PARAM_BOOL],
                    'scheduled_at' => [null, IQueryBuilder::PARAM_NULL],
                    'actor_uid' => [(string)$mailDraft['actorUid'], IQueryBuilder::PARAM_STR],
                    'approved_by' => [null, IQueryBuilder::PARAM_NULL],
                    'client_key' => [(string)$mailDraft['clientKey'], IQueryBuilder::PARAM_STR],
                    'version' => [1, IQueryBuilder::PARAM_INT],
                    'approved_at' => [null, IQueryBuilder::PARAM_NULL],
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }

        $result = $this->findApplication($id);
        if (($mailDraftId ?? null) !== null) $result['mailDraft'] = $this->mailDraft((int)$mailDraftId);
        return $result;
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

    private function findInboxMessageId(int $mailboxId, string $field, string $value): ?int {
        if (!in_array($field, ['content_hash', 'external_message_id'], true)) {
            throw new \LogicException('Ungültiges Identitätsfeld.');
        }
        $qb = $this->db->getQueryBuilder();
        $id = $qb->select('id')->from('rec_messages')
            ->where($qb->expr()->eq('mailbox_id', $qb->createNamedParameter($mailboxId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq($field, $qb->createNamedParameter($value, IQueryBuilder::PARAM_STR)))
            ->setMaxResults(1)->executeQuery()->fetchOne();
        return $id === false ? null : (int)$id;
    }

    /** @param list<string> $allowedFrom
     *  @return array<string,mixed>
     */
    private function transitionInboxMessage(
        int $messageId,
        string $toState,
        int $expectedVersion,
        string $actorUid,
        ?int $applicationId,
        array $allowedFrom,
        array $hiringDefaults = [],
        array $applicationDefaults = [],
        bool $manageTransaction = true,
        array $additionalAuditDetails = [],
    ): array {
        $message = $this->inboxMessage($messageId);
        if ($manageTransaction) $this->db->beginTransaction();
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->update('rec_messages')
                ->set('state', $qb->createNamedParameter($toState, IQueryBuilder::PARAM_STR))
                ->set('application_id', $qb->createNamedParameter($applicationId, $applicationId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT))
                ->set('version', $qb->createFunction('version + 1'))
                ->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($qb->expr()->eq('id', $qb->createNamedParameter($messageId, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($expectedVersion, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->in('state', $qb->createNamedParameter($allowedFrom, IQueryBuilder::PARAM_STR_ARRAY)));
            if ($qb->executeStatement() !== 1) {
                throw new ConflictException('Die Eingangsnachricht wurde zwischenzeitlich geändert.');
            }
            $prefilled = $applicationId === null ? [] : $this->prefillHiringData($applicationId, $hiringDefaults);
            $prefilledApplication = $applicationId === null ? [] : $this->prefillApplicationData($applicationId, $applicationDefaults);
            $this->insertInboxAudit($messageId, (string)$message['state'], $toState, $actorUid, [...[
                'applicationId' => $applicationId,
                'prefilledHiringFields' => $prefilled,
                'prefilledApplicationFields' => $prefilledApplication,
            ], ...$additionalAuditDetails]);
            if ($manageTransaction) $this->db->commit();
        } catch (\Throwable $error) {
            if ($manageTransaction) $this->db->rollBack();
            throw $error;
        }
        return $this->inboxMessage($messageId);
    }

    /** @param array<string,mixed> $defaults
     *  @return list<string>
     */
    private function prefillHiringData(int $applicationId, array $defaults): array {
        if ($defaults === []) return [];
        $stored = $this->hiringData($applicationId);
        $data = $stored['data'];
        $filled = [];
        foreach ($defaults as $field => $value) {
            if (trim((string)($data[$field] ?? '')) !== '') continue;
            $data[$field] = $value;
            $filled[] = $field;
        }
        if ($filled === []) return [];
        if ($stored['version'] === 0) {
            $this->insert('rec_hiring_data', [
                'application_id' => [$applicationId, IQueryBuilder::PARAM_INT],
                'data_json' => [$this->encode($data), IQueryBuilder::PARAM_STR],
                'version' => [1, IQueryBuilder::PARAM_INT],
            ]);
        } else {
            $qb = $this->db->getQueryBuilder();
            $affected = $qb->update('rec_hiring_data')
                ->set('data_json', $qb->createNamedParameter($this->encode($data), IQueryBuilder::PARAM_STR))
                ->set('version', $qb->createFunction('version + 1'))
                ->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
                ->where($qb->expr()->eq('application_id', $qb->createNamedParameter($applicationId, IQueryBuilder::PARAM_INT)))
                ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($stored['version'], IQueryBuilder::PARAM_INT)))
                ->executeStatement();
            if ($affected !== 1) throw new ConflictException('Die Vertragsdaten wurden zwischenzeitlich geändert.');
        }
        return $filled;
    }

    /** @param array<string,string> $defaults
     *  @return list<string>
     */
    private function prefillApplicationData(int $applicationId, array $defaults): array {
        if ($defaults === []) return [];
        $columns = [
            'previousExperience' => 'previous_experience',
            'germanLanguageLevel' => 'german_language_level',
        ];
        $row = $this->findRow('rec_applications', $applicationId);
        if ($row === null) throw new NotFoundException('Die Bewerbung wurde nicht gefunden.');
        $qb = $this->db->getQueryBuilder();
        $qb->update('rec_applications');
        $filled = [];
        if (array_key_exists('desiredWeeklyHours', $defaults)) {
            if (($row['desired_weekly_hours'] ?? null) === null) {
                $minimum = $defaults['desiredWeeklyHours'];
                $maximum = $defaults['desiredWeeklyHoursMax'] ?? null;
                $qb->set('desired_weekly_hours', $qb->createNamedParameter($minimum, IQueryBuilder::PARAM_STR));
                $qb->set('desired_weekly_hours_max', $qb->createNamedParameter($maximum, $maximum === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR));
                $filled[] = 'desiredWeeklyHours';
                if ($maximum !== null) $filled[] = 'desiredWeeklyHoursMax';
            }
            unset($defaults['desiredWeeklyHours'], $defaults['desiredWeeklyHoursMax']);
        }
        foreach ($defaults as $field => $value) {
            $column = $columns[$field] ?? null;
            if ($column === null) throw new \LogicException('Ungültiges Bewerbungsfeld.');
            if (trim((string)($row[$column] ?? '')) !== '') continue;
            $qb->set($column, $qb->createNamedParameter($value, IQueryBuilder::PARAM_STR));
            $filled[] = $field;
        }
        if ($filled === []) return [];
        $qb->set('version', $qb->createFunction('version + 1'))
            ->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($applicationId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('version', $qb->createNamedParameter((int)$row['version'], IQueryBuilder::PARAM_INT)));
        if ($qb->executeStatement() !== 1) throw new ConflictException('Die Bewerbungsdaten wurden zwischenzeitlich geändert.');
        return $filled;
    }

    /** @param array<string,mixed> $details */
    private function insertInboxAudit(int $messageId, string $fromState, string $toState, string $actorUid, array $details): void {
        $this->insert('rec_message_audit', [
            'message_id' => [$messageId, IQueryBuilder::PARAM_INT],
            'from_state' => [$fromState, IQueryBuilder::PARAM_STR],
            'to_state' => [$toState, IQueryBuilder::PARAM_STR],
            'actor_uid' => [$actorUid, IQueryBuilder::PARAM_STR],
            'details_json' => [$this->encode($details), IQueryBuilder::PARAM_STR],
            'changed_at' => [$this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
        ], false);
    }

    public function candidatePoolApplicationContext(int $applicationId): array {
        $application = $this->findApplication($applicationId);
        $job = $this->findRow('rec_jobs', (int)$application['jobId']);
        if ($job === null) throw new NotFoundException('Die zugehörige Stelle wurde nicht gefunden.');
        return ['application' => $application, 'job' => $this->mapJob($job)];
    }

    public function candidatePoolEntryForApplication(int $applicationId): ?array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb->select('*')->from('rec_pool_entries')->where($qb->expr()->eq('source_application_id', $qb->createNamedParameter($applicationId, IQueryBuilder::PARAM_INT)))->executeQuery()->fetchAssociative();
        return $row === false ? null : $this->mapCandidatePoolEntry($row);
    }

    public function createCandidatePoolEntry(array $entry): array {
        $id = $this->insert('rec_pool_entries', [
            'person_id' => [(int)$entry['personId'], IQueryBuilder::PARAM_INT], 'source_application_id' => [(int)$entry['sourceApplicationId'], IQueryBuilder::PARAM_INT],
            'status' => [(string)$entry['status'], IQueryBuilder::PARAM_STR], 'profession_category' => [(string)$entry['professionCategory'], IQueryBuilder::PARAM_STR],
            'desired_weekly_hours' => [$entry['desiredWeeklyHours'], $entry['desiredWeeklyHours'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR],
            'desired_weekly_hours_max' => [$entry['desiredWeeklyHoursMax'], $entry['desiredWeeklyHoursMax'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR],
            'area_keys_json' => [$this->encode($entry['areaKeys']), IQueryBuilder::PARAM_STR], 'consent_notice_version' => [(string)$entry['consentNoticeVersion'], IQueryBuilder::PARAM_STR],
            'requested_at' => [new DateTimeImmutable((string)$entry['requestedAt']), IQueryBuilder::PARAM_DATETIME_IMMUTABLE], 'consented_at' => [null, IQueryBuilder::PARAM_NULL],
            'expires_at' => [null, IQueryBuilder::PARAM_NULL], 'reminder_sent_at' => [null, IQueryBuilder::PARAM_NULL], 'withdrawn_at' => [null, IQueryBuilder::PARAM_NULL],
            'actor_uid' => [(string)$entry['actorUid'], IQueryBuilder::PARAM_STR], 'version' => [1, IQueryBuilder::PARAM_INT],
        ]);
        return $this->candidatePoolEntry($id);
    }

    public function candidatePoolEntry(int $id): array {
        $row = $this->findRow('rec_pool_entries', $id);
        if ($row === null) throw new NotFoundException('Der Rückstellungsvorgang wurde nicht gefunden.');
        return $this->mapCandidatePoolEntry($row);
    }

    public function updateCandidatePoolEntry(int $id, string $fromStatus, array $changes): array {
        $allowed = ['status', 'areaKeys', 'consentedAt', 'expiresAt', 'reminderSentAt', 'withdrawnAt', 'actorUid'];
        $columns = ['areaKeys' => 'area_keys_json', 'consentedAt' => 'consented_at', 'expiresAt' => 'expires_at', 'reminderSentAt' => 'reminder_sent_at', 'withdrawnAt' => 'withdrawn_at', 'actorUid' => 'actor_uid'];
        $qb = $this->db->getQueryBuilder()->update('rec_pool_entries');
        foreach ($changes as $key => $value) {
            if (!in_array($key, $allowed, true)) continue;
            $column = $columns[$key] ?? $key;
            if ($key === 'areaKeys') { $value = $this->encode($value); $type = IQueryBuilder::PARAM_STR; }
            elseif (in_array($key, ['consentedAt', 'expiresAt', 'reminderSentAt', 'withdrawnAt'], true)) { $value = $value === null ? null : new DateTimeImmutable((string)$value); $type = $value === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_DATETIME_IMMUTABLE; }
            else $type = IQueryBuilder::PARAM_STR;
            $qb->set($column, $qb->createNamedParameter($value, $type));
        }
        $qb->set('version', $qb->createFunction('version + 1'))->set('updated_at', $qb->createNamedParameter($this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($fromStatus, IQueryBuilder::PARAM_STR)));
        if ($qb->executeStatement() !== 1) throw new ConflictException('Der Rückstellungsvorgang wurde zwischenzeitlich geändert.');
        return $this->candidatePoolEntry($id);
    }

    public function appendCandidatePoolConsent(array $event): void {
        $this->insert('rec_pool_consents', [
            'entry_id' => [(int)$event['entryId'], IQueryBuilder::PARAM_INT], 'action' => [(string)$event['action'], IQueryBuilder::PARAM_STR],
            'notice_version' => [(string)$event['noticeVersion'], IQueryBuilder::PARAM_STR], 'evidence_type' => [(string)$event['evidenceType'], IQueryBuilder::PARAM_STR],
            'evidence_reference' => [(string)$event['evidenceReference'], IQueryBuilder::PARAM_STR], 'actor_uid' => [(string)$event['actorUid'], IQueryBuilder::PARAM_STR],
            'occurred_at' => [new DateTimeImmutable((string)$event['occurredAt']), IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
            'valid_until' => [$event['validUntil'] === null ? null : new DateTimeImmutable((string)$event['validUntil']), $event['validUntil'] === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
        ], false);
    }

    public function candidatePoolEntries(): array { return array_map([$this, 'mapCandidatePoolEntry'], $this->all('rec_pool_entries', ['*'], ['expires_at' => 'ASC'])); }
    public function activeCandidatePoolEntries(): array {
        $qb = $this->db->getQueryBuilder(); $rows = $qb->select('*')->from('rec_pool_entries')->where($qb->expr()->eq('status', $qb->createNamedParameter('active', IQueryBuilder::PARAM_STR)))->executeQuery()->fetchAllAssociative();
        return array_map([$this, 'mapCandidatePoolEntry'], $rows);
    }
    public function activeCandidatePoolJobs(): array {
        $rows = $this->all('rec_jobs', ['id', 'active', 'profession_category', 'advertised_weekly_hours'], ['id' => 'ASC']);
        return array_map(static fn(array $row): array => ['id' => (int)$row['id'], 'active' => (bool)$row['active'], 'professionCategory' => (string)$row['profession_category'], 'weeklyHoursMin' => $row['advertised_weekly_hours'] === null ? null : (float)$row['advertised_weekly_hours'], 'weeklyHoursMax' => $row['advertised_weekly_hours'] === null ? null : (float)$row['advertised_weekly_hours']], $rows);
    }
    public function candidatePoolMatch(int $entryId, int $jobId): ?array {
        $qb = $this->db->getQueryBuilder(); $row = $qb->select('*')->from('rec_pool_matches')->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, IQueryBuilder::PARAM_INT)))->andWhere($qb->expr()->eq('job_id', $qb->createNamedParameter($jobId, IQueryBuilder::PARAM_INT)))->executeQuery()->fetchAssociative();
        return $row === false ? null : $row;
    }
    public function createCandidatePoolMatch(array $match): void {
        $this->insert('rec_pool_matches', ['entry_id' => [(int)$match['entryId'], IQueryBuilder::PARAM_INT], 'job_id' => [(int)$match['jobId'], IQueryBuilder::PARAM_INT], 'state' => [(string)$match['state'], IQueryBuilder::PARAM_STR], 'reasons_json' => [$this->encode($match['reasons']), IQueryBuilder::PARAM_STR], 'reviewed_by_uid' => [null, IQueryBuilder::PARAM_NULL], 'version' => [1, IQueryBuilder::PARAM_INT]]);
    }

    private function mapCandidatePoolEntry(array $row): array {
        return ['id' => (int)$row['id'], 'personId' => (int)$row['person_id'], 'sourceApplicationId' => (int)$row['source_application_id'], 'status' => (string)$row['status'], 'professionCategory' => (string)$row['profession_category'], 'desiredWeeklyHours' => $row['desired_weekly_hours'] === null ? null : (float)$row['desired_weekly_hours'], 'desiredWeeklyHoursMax' => $row['desired_weekly_hours_max'] === null ? null : (float)$row['desired_weekly_hours_max'], 'areaKeys' => $this->decode((string)$row['area_keys_json']), 'consentNoticeVersion' => (string)$row['consent_notice_version'], 'requestedAt' => $row['requested_at'], 'consentedAt' => $row['consented_at'], 'expiresAt' => $row['expires_at'], 'reminderSentAt' => $row['reminder_sent_at'], 'withdrawnAt' => $row['withdrawn_at'], 'matches' => $this->candidatePoolMatchesForEntry((int)$row['id']), 'version' => (int)$row['version']];
    }

    private function candidatePoolMatchesForEntry(int $entryId): array {
        $qb = $this->db->getQueryBuilder();
        $rows = $qb->select('id', 'job_id', 'state', 'reasons_json', 'created_at')->from('rec_pool_matches')->where($qb->expr()->eq('entry_id', $qb->createNamedParameter($entryId, IQueryBuilder::PARAM_INT)))->orderBy('created_at', 'DESC')->executeQuery()->fetchAllAssociative();
        return array_map(fn(array $match): array => ['id' => (int)$match['id'], 'jobId' => (int)$match['job_id'], 'state' => (string)$match['state'], 'reasons' => $this->decode((string)$match['reasons_json']), 'createdAt' => $match['created_at']], $rows);
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

    /** @param array<string,mixed> $details */
    private function insertAudit(string $actorUid, string $action, string $subjectUid, ?int $applicationId, array $details): void {
        $this->insert('rec_permission_audit', [
            'actor_uid' => [$actorUid, IQueryBuilder::PARAM_STR],
            'action' => [$action, IQueryBuilder::PARAM_STR],
            'subject_uid' => [$subjectUid, IQueryBuilder::PARAM_STR],
            'application_id' => [
                $applicationId,
                $applicationId === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_INT,
            ],
            'details_json' => [$this->encode($details), IQueryBuilder::PARAM_STR],
            'created_at' => [$this->now(), IQueryBuilder::PARAM_DATETIME_IMMUTABLE],
        ], false);
    }

    /** @return array<string,mixed> */
    private function mapJob(array $row): array {
        $professionCategory = (bool)($row['basis_qualification_required'] ?? false)
            && in_array((string)($row['profession_category'] ?? ''), ['', 'other'], true)
                ? 'assistance'
                : ((string)($row['profession_category'] ?? '') ?: 'other');
        return [
            'id' => (int)$row['id'],
            'internalTitle' => (string)$row['internal_title'],
            'publicTitle' => (string)$row['public_title'],
            'active' => (bool)$row['active'],
            'responsibleUsers' => $this->decode((string)$row['responsible_users']),
            'responsibleGroups' => $this->decode((string)$row['responsible_groups']),
            'assignmentKey' => (string)($row['assignment_key'] ?? ''),
            'basisQualificationRequired' => $professionCategory === 'assistance',
            'professionCategory' => $professionCategory,
            'contractTerm' => (string)($row['contract_term'] ?? ''), 'payGrade' => (string)($row['pay_grade'] ?? ''),
            'advertisedWeeklyHours' => ($row['advertised_weekly_hours'] ?? null) === null ? null : (float)$row['advertised_weekly_hours'],
            'fullTimeWeeklyHours' => ($row['full_time_weekly_hours'] ?? null) === null ? null : (float)$row['full_time_weekly_hours'],
            'vacationDays' => ($row['vacation_days'] ?? null) === null ? null : (float)$row['vacation_days'],
            'workLocation' => (string)($row['work_location'] ?? 'Berlin'),
            'workingTimeModel' => $professionCategory === 'assistance' ? 'kapovaz' : 'fixed',
            'version' => (int)$row['version'],
        ];
    }

    /** @return array<string,mixed> */
    private function mapDocumentComment(array $row): array {
        return [
            'id' => (int)$row['id'],
            'attachmentId' => (int)$row['attachment_id'],
            'kind' => (string)$row['kind'],
            'body' => (string)$row['body'],
            'pageNumber' => $row['page_number'] === null ? null : (int)$row['page_number'],
            'anchorColumn' => $row['anchor_column'] === null ? '' : (string)$row['anchor_column'],
            'actorUid' => (string)$row['actor_uid'],
            'clientKey' => (string)$row['client_key'],
            'version' => (int)$row['version'],
            'createdAt' => (string)$row['created_at'],
        ];
    }

    /** @return array<string,mixed> */
    private function mapDocumentFieldLink(array $row): array {
        return [
            'id' => (int)$row['id'],
            'attachmentId' => (int)$row['attachment_id'],
            'applicationId' => (int)$row['application_id'],
            'targetField' => (string)$row['target_field'],
            'selectedText' => (string)$row['selected_text'],
            'appliedValue' => (string)$row['applied_value'],
            'resultValue' => (string)$row['result_value'],
            'pageNumber' => (int)$row['page_number'],
            'rectangles' => $this->decode((string)$row['rectangles_json']),
            'replacedExisting' => (bool)$row['replaced_existing'],
            'actorUid' => (string)$row['actor_uid'],
            'clientKey' => (string)$row['client_key'],
            'createdAt' => (string)$row['created_at'],
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
            'desiredWeeklyHours' => ($row['desired_weekly_hours'] ?? null) === null
                ? null
                : (float)$row['desired_weekly_hours'],
            'desiredWeeklyHoursMax' => ($row['desired_weekly_hours_max'] ?? null) === null
                ? null
                : (float)$row['desired_weekly_hours_max'],
            'closureReason' => (string)$row['closure_reason'],
            'retentionState' => (string)$row['retention_state'],
            'areaKey' => (string)($row['area_key'] ?? ''),
            'firstGuideAccess' => (bool)($row['first_guide_access'] ?? false),
            'previousExperience' => (string)($row['previous_experience'] ?? ''),
            'germanLanguageLevel' => (string)($row['german_language_level'] ?? ''),
            'freeComment' => (string)($row['free_comment'] ?? ''),
            'version' => (int)$row['version'],
        ];
    }

    /** @return array<string,mixed> */
    private function mapApplicationWithBasisQualification(array $row): array {
        $application = $this->mapApplication($row);
        $application['basisQualification'] = $this->latestBasisQualificationAssignment((int)$application['id']);
        return $application;
    }

    /** @return array<string,mixed> */
    private function mapApplicationSummary(array $row): array {
        return $this->publicApplication($this->mapApplicationWithBasisQualification($row));
    }

    /** @param array<string,mixed> $application
     *  @return array<string,mixed>
     */
    private function publicApplication(array $application): array {
        $basisQualification = $application['basisQualification'] ?? null;
        if (is_array($basisQualification)) {
            $application['basisQualification'] = [
                'id' => (int)$basisQualification['id'],
                'runId' => (int)$basisQualification['runId'],
                'label' => (string)$basisQualification['label'],
                'startsOn' => (string)$basisQualification['startsOn'],
                'endsOn' => (string)$basisQualification['endsOn'],
            ];
        }
        return $application;
    }

    /** @return array<string,mixed>|null */
    private function latestBasisQualificationAssignment(int $applicationId): ?array {
        $qb = $this->db->getQueryBuilder();
        $row = $qb
            ->select('id', 'application_id', 'run_id', 'result', 'evaluation_note', 'actor_uid', 'version', 'evaluated_at', 'created_at', 'updated_at')
            ->from('rec_bq_assignments')
            ->where($qb->expr()->eq('application_id', $qb->createNamedParameter($applicationId, IQueryBuilder::PARAM_INT)))
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : $this->mapBasisQualificationAssignment($row);
    }

    /** @return array<string,mixed> */
    private function mapBasisQualificationRun(array $row): array {
        return [
            'id' => (int)$row['id'],
            'label' => (string)$row['label'],
            'startsOn' => (string)$row['starts_on'],
            'endsOn' => (string)$row['ends_on'],
            'actorUid' => (string)$row['actor_uid'],
            'version' => (int)$row['version'],
            'createdAt' => (string)$row['created_at'],
            'updatedAt' => (string)$row['updated_at'],
        ];
    }

    /** @return array<string,mixed> */
    private function mapBasisQualificationAssignment(array $row): array {
        $run = $this->basisQualificationRun((int)$row['run_id']);
        return [
            'id' => (int)$row['id'],
            'applicationId' => (int)$row['application_id'],
            'runId' => (int)$row['run_id'],
            'label' => $run['label'],
            'startsOn' => $run['startsOn'],
            'endsOn' => $run['endsOn'],
            'result' => (string)$row['result'],
            'evaluationNote' => (string)$row['evaluation_note'],
            'actorUid' => (string)$row['actor_uid'],
            'version' => (int)$row['version'],
            'evaluatedAt' => $row['evaluated_at'] === null ? null : (string)$row['evaluated_at'],
            'createdAt' => (string)$row['created_at'],
            'updatedAt' => (string)$row['updated_at'],
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
    private function mapMailTemplate(array $row): array {
        return [
            'id' => (int)$row['id'], 'name' => (string)$row['name'], 'active' => (bool)$row['active'],
            'revision' => (int)$row['current_revision'], 'version' => (int)$row['version'],
            'actorUid' => (string)$row['actor_uid'], 'createdAt' => (string)$row['created_at'], 'updatedAt' => (string)$row['updated_at'],
        ];
    }

    /** @return array<string,mixed> */
    private function mapStatusMailRule(array $row): array {
        return [
            'id' => (int)$row['id'], 'fromStatus' => (string)$row['from_status'], 'toStatus' => (string)$row['to_status'],
            'templateId' => (int)$row['template_id'], 'enabled' => (bool)$row['enabled'], 'defaultTiming' => (string)$row['default_timing'],
            'version' => (int)$row['version'], 'actorUid' => (string)$row['actor_uid'],
        ];
    }

    /** @return array<string,mixed> */
    private function mapMailTextBlock(array $row): array {
        return [
            'id' => (int)$row['id'], 'label' => (string)$row['label'], 'insertText' => (string)$row['insert_text'],
            'active' => (bool)$row['active'], 'version' => (int)$row['version'],
        ];
    }

    /** @return array<string,mixed> */
    private function mapMailDraft(array $row): array {
        return [
            'id' => (int)$row['id'], 'applicationId' => (int)$row['application_id'], 'fromStatus' => (string)$row['from_status'],
            'toStatus' => (string)$row['to_status'], 'templateId' => (int)$row['template_id'], 'templateRevision' => (int)$row['template_revision'],
            'originalRecipient' => (string)$row['original_recipient'],
            'intendedRecipient' => $row['intended_recipient'] === null ? null : (string)$row['intended_recipient'],
            'deliveryRecipient' => $row['delivery_recipient'] === null ? null : (string)$row['delivery_recipient'],
            'subject' => (string)$row['subject'], 'body' => (string)$row['body'],
            'bodyFormat' => (string)($row['body_format'] ?? 'plain'), 'status' => (string)$row['state'],
            'defaultTiming' => (string)$row['default_timing'],
            'testMode' => (bool)$row['test_mode'], 'scheduledAt' => $row['scheduled_at'] === null ? null : (string)$row['scheduled_at'],
            'actorUid' => (string)$row['actor_uid'], 'approvedBy' => $row['approved_by'] === null ? null : (string)$row['approved_by'],
            'version' => (int)$row['version'], 'createdAt' => (string)$row['created_at'], 'updatedAt' => (string)$row['updated_at'],
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
