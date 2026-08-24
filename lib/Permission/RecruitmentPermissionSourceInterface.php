<?php

declare(strict_types=1);

namespace OCA\Recruitment\Permission;

use OCA\LocalBase\Organization\AdOrganizationSnapshot;

interface RecruitmentPermissionSourceInterface {
    public function organization(): AdOrganizationSnapshot;
    public function permissionSettings(): array;
}
