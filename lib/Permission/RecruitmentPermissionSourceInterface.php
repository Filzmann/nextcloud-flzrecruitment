<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Permission;

use OCA\FlzRecruitment\Organization\OrganizationSnapshot;

interface RecruitmentPermissionSourceInterface {
    public function organization(): OrganizationSnapshot;
    public function permissionSettings(): array;
}
