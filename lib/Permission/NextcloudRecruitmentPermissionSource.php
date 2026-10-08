<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Permission;

use OCA\FlzRecruitment\Organization\OrganizationSnapshot;
use OCA\FlzRecruitment\Organization\OrganizationSnapshotService;
use OCA\FlzRecruitment\Service\RecruitmentPermissionSettingsService;

final class NextcloudRecruitmentPermissionSource implements RecruitmentPermissionSourceInterface {
    public function __construct(
        private OrganizationSnapshotService $organization,
        private RecruitmentPermissionSettingsService $settings,
    ) {}

    public function organization(): OrganizationSnapshot { return $this->organization->snapshot(); }
    public function permissionSettings(): array { return $this->settings->settings(); }
}
