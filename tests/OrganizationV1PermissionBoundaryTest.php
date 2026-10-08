<?php

declare(strict_types=1);

use OCA\FlzRecruitment\Organization\OrganizationSnapshot;
use OCA\FlzRecruitment\Service\RecruitmentPermissionPolicy;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;

$settings = [
    'firstGuideGroupId' => 'group-first-guides',
    'representatives' => [[
        'uid' => 'global-representative',
        'capabilities' => [RecruitmentPermissionPolicy::VIEW_DOSSIER, RecruitmentPermissionPolicy::EDIT_APPLICATIONS],
        'all' => true,
        'areaKeys' => [],
        'applicationIds' => [],
    ], [
        'uid' => 'case-representative',
        'capabilities' => [RecruitmentPermissionPolicy::VIEW_DOSSIER],
        'all' => false,
        'areaKeys' => [],
        'applicationIds' => [42],
    ], [
        'uid' => 'area-representative',
        'capabilities' => [RecruitmentPermissionPolicy::VIEW_DOSSIER],
        'all' => false,
        'areaKeys' => ['west'],
        'applicationIds' => [],
    ]],
];

$application = ['id' => 42, 'status' => 'approved_for_hire', 'areaKey' => 'west', 'firstGuideAccess' => true];

foreach ([
    OrganizationSnapshot::MISSING,
    OrganizationSnapshot::DISABLED,
    OrganizationSnapshot::INCOMPATIBLE,
    OrganizationSnapshot::INVALID,
    OrganizationSnapshot::UNAVAILABLE,
] as $status) {
    TestRunner::test("{$status} organization denies derived rights but preserves independent app-local rights", static function () use ($status, $settings, $application): void {
        $policy = new RecruitmentPermissionPolicy(OrganizationSnapshot::unavailable($status), $settings);

        assertSame(false, $policy->can(
            ['uid' => 'hr', 'isAdmin' => false, 'groupIds' => ['group-hr']],
            RecruitmentPermissionPolicy::VIEW_DOSSIER,
            $application,
        ));
        assertSame(false, $policy->can(
            ['uid' => 'area-representative', 'isAdmin' => false, 'groupIds' => []],
            RecruitmentPermissionPolicy::VIEW_DOSSIER,
            $application,
        ));
        assertSame(true, $policy->can(
            ['uid' => 'global-representative', 'isAdmin' => false, 'groupIds' => []],
            RecruitmentPermissionPolicy::VIEW_DOSSIER,
            $application,
        ));
        assertSame(true, $policy->can(
            ['uid' => 'case-representative', 'isAdmin' => false, 'groupIds' => []],
            RecruitmentPermissionPolicy::VIEW_DOSSIER,
            $application,
        ));
        assertSame(false, $policy->can(
            ['uid' => 'case-representative', 'isAdmin' => false, 'groupIds' => []],
            RecruitmentPermissionPolicy::VIEW_DOSSIER,
            array_replace($application, ['id' => 43]),
        ));
        assertSame(true, $policy->can(
            ['uid' => 'admin', 'isAdmin' => true, 'groupIds' => []],
            RecruitmentPermissionPolicy::MANAGE_DELEGATIONS,
        ));
        assertSame(true, $policy->canManageUnassignedInbox(
            ['uid' => 'global-representative', 'isAdmin' => false, 'groupIds' => []],
        ));
    });
}
