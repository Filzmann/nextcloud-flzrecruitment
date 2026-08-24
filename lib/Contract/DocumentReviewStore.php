<?php

declare(strict_types=1);

namespace OCA\Recruitment\Contract;

interface DocumentReviewStore {
    /** @return array<string,mixed> */
    public function attachmentContext(int $id): array;
    /** @return list<array<string,mixed>> */
    public function documentComments(int $attachmentId): array;
    /** @return array<string,mixed>|null */
    public function findDocumentCommentByClientKey(int $attachmentId, string $clientKey): ?array;
    /** @param array<string,mixed> $comment */
    public function createDocumentComment(array $comment): int;
    /** @return array<string,mixed> */
    public function documentComment(int $id): array;
}
