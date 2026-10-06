<?php

declare(strict_types=1);

namespace OCA\Recruitment\Permission;

use OCA\Recruitment\Organization\OrganizationSnapshot;
use OCA\Recruitment\Organization\OrganizationSnapshotService;
use OCA\Recruitment\Service\RecruitmentPermissionSettingsService;

final class NextcloudRecruitmentPermissionSource implements RecruitmentPermissionSourceInterface {
    public function __construct(
        private OrganizationSnapshotService $organization,
        private RecruitmentPermissionSettingsService $settings,
    ) {}

    public function organization(): OrganizationSnapshot { return $this->organization->snapshot(); }
    public function permissionSettings(): array { return $this->settings->settings(); }
}
