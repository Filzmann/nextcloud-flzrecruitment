<?php

declare(strict_types=1);

namespace OCA\Recruitment\Contract;

interface RecruitmentStore {
    /** @param array<string,mixed> $job */
    public function createJob(array $job): int;

    /** @param array<string,mixed> $person */
    public function createPerson(array $person): int;

    public function personExists(int $id): bool;

    public function jobExists(int $id): bool;

    /** @param array<string,mixed> $application */
    public function createApplication(array $application): int;

    /** @return array<string,list<array<string,mixed>>> */
    public function overview(): array;

    /** @return array<string,mixed> */
    public function applicationDetail(int $id): array;
}
