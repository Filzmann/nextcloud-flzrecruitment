<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Contract;

use DateTimeImmutable;

interface StatusMailOutboxStore {
    /** @return array<string,mixed>|null */
    public function claimDueMailJob(DateTimeImmutable $now): ?array;
    public function markMailJobSent(int $jobId, int $draftId, DateTimeImmutable $sentAt): void;
    public function markMailJobFailed(int $jobId, int $draftId, string $errorCode, DateTimeImmutable $retryAt): void;
}
