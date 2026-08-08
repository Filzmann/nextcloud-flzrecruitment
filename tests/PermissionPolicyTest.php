<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/localbase/lib/Organization/AdOrganizationSnapshot.php';
require_once dirname(__DIR__) . '/lib/Service/RecruitmentPermissionPolicy.php';

use OCA\LocalBase\Organization\AdOrganizationSnapshot;
use OCA\Recruitment\Service\RecruitmentPermissionPolicy;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertTrue;

$snapshot = new AdOrganizationSnapshot(true, 3, [
    'staff_hr' => ['groupId' => 'group-hr', 'label' => 'Personalreferent*innen'],
    'finance' => ['groupId' => 'group-finance', 'label' => 'Finanzen'],
    'payroll' => ['groupId' => 'group-payroll', 'label' => 'Lohn'],
    'eb' => ['groupId' => 'group-eb', 'label' => 'Einsatzbegleitung'],
    'pfk' => ['groupId' => 'group-pfk', 'label' => 'Pflegefachkraft'],
], [
    'west' => ['groupId' => 'area-west', 'label' => 'West'],
    'south' => ['groupId' => 'area-south', 'label' => 'Süd'],
]);

$settings = [
    'firstGuideGroupId' => 'group-first-guides',
    'representatives' => [[
        'uid' => 'representative-area',
        'capabilities' => [RecruitmentPermissionPolicy::VIEW_DOSSIER, RecruitmentPermissionPolicy::INTERVIEW],
        'all' => false,
        'areaKeys' => ['west'],
        'applicationIds' => [],
    ], [
        'uid' => 'representative-case',
        'capabilities' => [RecruitmentPermissionPolicy::EDIT_APPLICATIONS, RecruitmentPermissionPolicy::MANAGE_FIRST_GUIDE_ACCESS],
        'all' => false,
        'areaKeys' => [],
        'applicationIds' => [42],
    ], [
        'uid' => 'representative-global',
        'capabilities' => [RecruitmentPermissionPolicy::EDIT_APPLICATIONS],
        'all' => true,
        'areaKeys' => [],
        'applicationIds' => [],
    ]],
];

$approvedWest = ['id' => 42, 'status' => 'approved_for_hire', 'areaKey' => 'west', 'firstGuideAccess' => true];
$approvedSouth = ['id' => 43, 'status' => 'approved_for_hire', 'areaKey' => 'south', 'firstGuideAccess' => true];

TestRunner::test('HR and Nextcloud admins have full operational access while finance has none', static function () use ($snapshot, $settings, $approvedWest): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    assertTrue($policy->can(['uid' => 'admin', 'isAdmin' => true, 'groupIds' => []], RecruitmentPermissionPolicy::MANAGE_DELEGATIONS, $approvedWest));
    assertTrue($policy->can(['uid' => 'hr', 'isAdmin' => false, 'groupIds' => ['group-hr']], RecruitmentPermissionPolicy::EDIT_HIRING_DATA, $approvedWest));
    assertSame(false, $policy->can(['uid' => 'finance', 'isAdmin' => false, 'groupIds' => ['group-finance']], RecruitmentPermissionPolicy::VIEW_DOSSIER, $approvedWest));
});

TestRunner::test('payroll receives only the hiring projection after approval', static function () use ($snapshot, $settings, $approvedWest): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    $payroll = ['uid' => 'payroll', 'isAdmin' => false, 'groupIds' => ['group-payroll']];
    assertTrue($policy->can($payroll, RecruitmentPermissionPolicy::VIEW_HIRING_DATA, $approvedWest));
    assertSame(false, $policy->can($payroll, RecruitmentPermissionPolicy::VIEW_DOSSIER, $approvedWest));
    assertSame(false, $policy->can($payroll, RecruitmentPermissionPolicy::VIEW_HIRING_DATA, array_replace($approvedWest, ['status' => 'screening'])));
});

TestRunner::test('BQ assignment opens only payroll master data and negative outcome closes it', static function () use ($snapshot, $settings): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    $payroll = ['uid' => 'payroll', 'isAdmin' => false, 'groupIds' => ['group-payroll']];
    $pending = [
        'id' => 51,
        'status' => 'basis_qualification',
        'areaKey' => '',
        'firstGuideAccess' => false,
        'basisQualification' => ['id' => 8, 'result' => 'pending'],
    ];

    assertTrue($policy->can($payroll, RecruitmentPermissionPolicy::VIEW_HIRING_DATA, $pending));
    assertSame(false, $policy->can($payroll, RecruitmentPermissionPolicy::VIEW_DOSSIER, $pending));
    assertSame(false, $policy->can($payroll, RecruitmentPermissionPolicy::VIEW_HIRING_DATA, array_replace(
        $pending,
        ['basisQualification' => ['id' => 8, 'result' => 'not_suitable']],
    )));
});

TestRunner::test('only HR and Nextcloud admins manage basis qualifications', static function () use ($snapshot, $settings): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    $hr = ['uid' => 'hr', 'isAdmin' => false, 'groupIds' => ['group-hr']];
    $admin = ['uid' => 'admin', 'isAdmin' => true, 'groupIds' => []];
    $representative = ['uid' => 'representative-area', 'isAdmin' => false, 'groupIds' => ['group-pfk']];

    assertTrue($policy->can($hr, RecruitmentPermissionPolicy::MANAGE_BASIS_QUALIFICATION));
    assertTrue($policy->can($admin, RecruitmentPermissionPolicy::MANAGE_BASIS_QUALIFICATION));
    assertSame(false, $policy->can($representative, RecruitmentPermissionPolicy::MANAGE_BASIS_QUALIFICATION));
    assertSame(false, in_array(RecruitmentPermissionPolicy::MANAGE_BASIS_QUALIFICATION, RecruitmentPermissionPolicy::DELEGATABLE_CAPABILITIES, true));
});

TestRunner::test('first guides need eligibility, matching area, approved status and active manual grant', static function () use ($snapshot, $settings, $approvedWest): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    $guide = ['uid' => 'guide', 'isAdmin' => false, 'groupIds' => ['group-eb', 'group-first-guides', 'area-west']];
    assertTrue($policy->can($guide, RecruitmentPermissionPolicy::VIEW_DOSSIER, $approvedWest));
    assertSame(false, $policy->can($guide, RecruitmentPermissionPolicy::INTERVIEW, $approvedWest));
    assertSame(false, $policy->can($guide, RecruitmentPermissionPolicy::VIEW_DOSSIER, array_replace($approvedWest, ['firstGuideAccess' => false])));
    assertSame(false, $policy->can($guide, RecruitmentPermissionPolicy::VIEW_DOSSIER, array_replace($approvedWest, ['areaKey' => 'south'])));
    assertSame(false, $policy->can(['uid' => 'not-eb', 'isAdmin' => false, 'groupIds' => ['group-first-guides', 'area-west']], RecruitmentPermissionPolicy::VIEW_DOSSIER, $approvedWest));
});

TestRunner::test('representatives are bounded by capability and global, area or application scope', static function () use ($snapshot, $settings, $approvedWest, $approvedSouth): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    $areaRepresentative = ['uid' => 'representative-area', 'isAdmin' => false, 'groupIds' => ['group-pfk']];
    assertTrue($policy->can($areaRepresentative, RecruitmentPermissionPolicy::INTERVIEW, $approvedWest));
    assertSame(false, $policy->can($areaRepresentative, RecruitmentPermissionPolicy::INTERVIEW, $approvedSouth));
    assertSame(false, $policy->can($areaRepresentative, RecruitmentPermissionPolicy::EDIT_APPLICATIONS, $approvedWest));

    $caseRepresentative = ['uid' => 'representative-case', 'isAdmin' => false, 'groupIds' => ['group-eb']];
    assertTrue($policy->can($caseRepresentative, RecruitmentPermissionPolicy::EDIT_APPLICATIONS, $approvedWest));
    assertTrue($policy->can($caseRepresentative, RecruitmentPermissionPolicy::MANAGE_FIRST_GUIDE_ACCESS, $approvedWest));
    assertSame(false, $policy->can($caseRepresentative, RecruitmentPermissionPolicy::EDIT_APPLICATIONS, $approvedSouth));
    assertSame(false, $policy->can($caseRepresentative, RecruitmentPermissionPolicy::MANAGE_FIRST_GUIDE_ACCESS, $approvedSouth));
    assertSame(false, $policy->can($caseRepresentative, RecruitmentPermissionPolicy::MANAGE_DELEGATIONS, $approvedWest));
});

TestRunner::test('invalid organization snapshots deny every non-admin path', static function () use ($settings, $approvedWest): void {
    $invalid = new AdOrganizationSnapshot(false, 3, [], []);
    $policy = new RecruitmentPermissionPolicy($invalid, $settings);
    assertSame(false, $policy->can(['uid' => 'hr', 'isAdmin' => false, 'groupIds' => ['group-hr']], RecruitmentPermissionPolicy::VIEW_DOSSIER, $approvedWest));
});

TestRunner::test('only HR, admins and global editing representatives see unassigned incoming mail', static function () use ($snapshot, $settings): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    assertTrue($policy->canManageUnassignedInbox(['uid' => 'admin', 'isAdmin' => true, 'groupIds' => []]));
    assertTrue($policy->canManageUnassignedInbox(['uid' => 'hr', 'isAdmin' => false, 'groupIds' => ['group-hr']]));
    assertTrue($policy->canManageUnassignedInbox(['uid' => 'representative-global', 'isAdmin' => false, 'groupIds' => ['group-eb']]));
    assertSame(false, $policy->canManageUnassignedInbox(['uid' => 'representative-area', 'isAdmin' => false, 'groupIds' => ['group-pfk']]));
    assertSame(false, $policy->canManageUnassignedInbox(['uid' => 'representative-case', 'isAdmin' => false, 'groupIds' => ['group-eb']]));
    assertSame(false, $policy->canManageUnassignedInbox(['uid' => 'payroll', 'isAdmin' => false, 'groupIds' => ['group-payroll']]));
    assertSame(false, $policy->canManageUnassignedInbox(['uid' => 'guide', 'isAdmin' => false, 'groupIds' => ['group-eb', 'group-first-guides', 'area-west']]));
});
