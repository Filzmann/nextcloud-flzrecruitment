<?php

declare(strict_types=1);

namespace OCA\Recruitment\Contract;

interface DocumentFieldLinkStore {
    /** @return array<string,mixed> */
    public function attachmentContext(int $id): array;
    /** @return array<string,mixed> */
    public function documentFieldState(int $applicationId): array;
    /** @return list<array<string,mixed>> */
    public function documentFieldLinks(int $attachmentId): array;
    /** @return array<string,mixed>|null */
    public function findDocumentFieldLinkByClientKey(int $attachmentId, string $clientKey): ?array;
    /** @param array<string,mixed> $link */
    public function createDocumentFieldLinkAndApply(array $link, string $resultValue, int $expectedVersion): int;
    /** @return array<string,mixed> */
    public function documentFieldLink(int $id): array;
}
