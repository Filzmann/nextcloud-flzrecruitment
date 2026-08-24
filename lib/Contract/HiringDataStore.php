<?php

declare(strict_types=1);

namespace OCA\Recruitment\Contract;

interface HiringDataStore {
    /** @return array<string,mixed> */
    public function findApplication(int $id): array;
    /** @return array<string,mixed> */
    public function hiringContext(int $id): array;
    /** @return array{data: array<string,mixed>, version: int} */
    public function hiringData(int $applicationId): array;
    /** @param array<string,mixed> $data
     *  @return array{data: array<string,mixed>, version: int}
     */
    public function saveHiringData(int $applicationId, array $data, int $expectedVersion, string $actorUid): array;
    /** @return list<array<string,mixed>> */
    public function releasedHiringApplications(): array;
    /** @return array<string,mixed> */
    public function setFirstGuideAccess(int $applicationId, bool $enabled, int $expectedVersion, string $actorUid): array;
}
