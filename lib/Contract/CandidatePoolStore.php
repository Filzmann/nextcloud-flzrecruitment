<?php

declare(strict_types=1);

namespace OCA\Recruitment\Contract;

interface CandidatePoolStore {
    /** @return array<string,mixed> */
    public function candidatePoolApplicationContext(int $applicationId): array;
    /** @return array<string,mixed>|null */
    public function candidatePoolEntryForApplication(int $applicationId): ?array;
    /** @param array<string,mixed> $entry @return array<string,mixed> */
    public function createCandidatePoolEntry(array $entry): array;
    /** @return array<string,mixed> */
    public function candidatePoolEntry(int $id): array;
    /** @param array<string,mixed> $changes @return array<string,mixed> */
    public function updateCandidatePoolEntry(int $id, string $fromStatus, array $changes): array;
    /** @param array<string,mixed> $event */
    public function appendCandidatePoolConsent(array $event): void;
    /** @return list<array<string,mixed>> */
    public function candidatePoolEntries(): array;
    /** @return list<array<string,mixed>> */
    public function activeCandidatePoolEntries(): array;
    /** @return list<array<string,mixed>> */
    public function activeCandidatePoolJobs(): array;
    /** @return array<string,mixed>|null */
    public function candidatePoolMatch(int $entryId, int $jobId): ?array;
    /** @param array<string,mixed> $match */
    public function createCandidatePoolMatch(array $match): void;
}
