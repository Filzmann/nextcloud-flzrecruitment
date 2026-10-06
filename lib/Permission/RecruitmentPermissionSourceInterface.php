<?php

declare(strict_types=1);

namespace OCA\Recruitment\Permission;

use OCA\Recruitment\Organization\OrganizationSnapshot;

interface RecruitmentPermissionSourceInterface {
    public function organization(): OrganizationSnapshot;
    public function permissionSettings(): array;
}
