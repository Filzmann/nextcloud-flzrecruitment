<?php

declare(strict_types=1);

namespace OCA\Recruitment\Permission;

use OCA\LocalBase\Organization\AdOrganizationSnapshot;
use OCA\LocalBase\Organization\AdOrganizationSnapshotService;
use OCA\Recruitment\Service\RecruitmentPermissionSettingsService;

final class NextcloudRecruitmentPermissionSource implements RecruitmentPermissionSourceInterface {
    public function __construct(
        private AdOrganizationSnapshotService $organization,
        private RecruitmentPermissionSettingsService $settings,
    ) {}

    public function organization(): AdOrganizationSnapshot { return $this->organization->snapshot(); }
    public function permissionSettings(): array { return $this->settings->settings(); }
}
