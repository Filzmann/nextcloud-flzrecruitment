<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Contract;

interface BasisQualificationStore {
    /** @return array{application: array<string,mixed>, job: array<string,mixed>} */
    public function basisQualificationContext(int $applicationId): array;
    /** @return array<string,mixed> */
    public function basisQualificationJob(int $jobId): array;
    /** @return array<string,mixed> */
    public function setJobBasisQualificationRequired(
        int $jobId,
        bool $required,
        int $expectedVersion,
        string $actorUid,
    ): array;
    /** @return array<string,mixed> */
    public function basisQualificationRun(int $runId): array;
    /** @return list<array<string,mixed>> */
    public function basisQualificationRuns(): array;
    /** @return array<string,mixed>|null */
    public function activeBasisQualificationAssignment(int $applicationId): ?array;
    /** @param array<string,mixed> $run */
    public function createBasisQualificationRun(array $run): int;
    /** @return array<string,mixed> */
    public function assignBasisQualification(
        int $applicationId,
        int $runId,
        int $expectedApplicationVersion,
        string $actorUid,
    ): array;
    /** @return array<string,mixed> */
    public function basisQualificationAssignment(int $assignmentId): array;
    /** @return list<array<string,mixed>> */
    public function basisQualificationAssignments(int $applicationId): array;
    /** @return array<string,mixed> */
    public function recordBasisQualificationResult(
        int $assignmentId,
        string $result,
        string $note,
        int $expectedVersion,
        string $actorUid,
    ): array;
}
