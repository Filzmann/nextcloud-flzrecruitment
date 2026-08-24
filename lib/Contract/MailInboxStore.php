<?php

declare(strict_types=1);

namespace OCA\Recruitment\Contract;

interface MailInboxStore {
    /** @param array<string,mixed> $mailbox */
    public function ensureMailbox(array $mailbox): int;
    /** @return array<string,mixed>|null */
    public function findMessageByIdentity(int $mailboxId, ?string $externalMessageId, string $contentHash): ?array;
    /** @param array<string,mixed> $message */
    public function createInboxMessage(array $message): int;
    /** @param array<string,mixed> $attachment */
    public function addInboxAttachment(int $messageId, array $attachment): int;
    public function markInboxImportError(int $messageId, string $actorUid): void;
    /** @return array<string,mixed> */
    public function inboxMessage(int $messageId): array;
    /** @return list<array<string,mixed>> */
    public function inboxMessages(): array;
    /** @return list<array<string,mixed>> */
    public function inboxMessagesForApplication(int $applicationId): array;
    public function inboxApplicationExists(int $applicationId): bool;
    /** @return array<string,mixed> */
    /** @param array<string,string> $hiringDefaults
     *  @param array<string,string> $applicationDefaults
     */
    public function assignInboxMessage(int $messageId, int $applicationId, int $expectedVersion, string $actorUid, array $hiringDefaults = [], array $applicationDefaults = []): array;
    /** @return array<string,mixed> */
    public function ignoreInboxMessage(int $messageId, int $expectedVersion, string $actorUid): array;
}
