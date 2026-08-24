<?php

declare(strict_types=1);

namespace OCA\Recruitment\Contract;

interface ApplicationStatusStore {
    /** @return array<string,mixed> */
    public function findApplication(int $id): array;

    /** @return array<string,mixed>|null */
    public function statusMailPreparation(int $id, string $fromStatus, string $toStatus): ?array;

    /** @return array<string,mixed> */
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
    ): array;
}
