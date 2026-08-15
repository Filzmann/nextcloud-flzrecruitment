<?php

declare(strict_types=1);

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
    assertTrue($policy->can($payroll, RecruitmentPermissionPolicy::EDIT_PAYROLL_DATA, $approvedWest));
    assertSame(false, $policy->can($payroll, RecruitmentPermissionPolicy::VIEW_DOSSIER, $approvedWest));
    assertSame(false, $policy->can($payroll, RecruitmentPermissionPolicy::VIEW_HIRING_DATA, array_replace($approvedWest, ['status' => 'screening'])));
});

TestRunner::test('BQ assignment does not expose LoBu master data before hire approval', static function () use ($snapshot, $settings): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    $payroll = ['uid' => 'payroll', 'isAdmin' => false, 'groupIds' => ['group-payroll']];
    $pending = [
        'id' => 51,
        'status' => 'basis_qualification',
        'areaKey' => '',
        'firstGuideAccess' => false,
        'basisQualification' => ['id' => 8, 'result' => 'pending'],
    ];

    assertSame(false, $policy->can($payroll, RecruitmentPermissionPolicy::VIEW_HIRING_DATA, $pending));
    assertSame(false, $policy->can($payroll, RecruitmentPermissionPolicy::EDIT_PAYROLL_DATA, $pending));
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

TestRunner::test('only HR and Nextcloud admins manage mail templates while communication remains scoped', static function () use ($snapshot, $settings): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    assertSame(true, $policy->can(['uid' => 'hr', 'isAdmin' => false, 'groupIds' => ['group-hr']], RecruitmentPermissionPolicy::MANAGE_MAIL_TEMPLATES));
    assertSame(true, $policy->can(['uid' => 'admin', 'isAdmin' => true, 'groupIds' => []], RecruitmentPermissionPolicy::MANAGE_MAIL_TEMPLATES));
    assertSame(false, $policy->can(['uid' => 'representative', 'isAdmin' => false, 'groupIds' => []], RecruitmentPermissionPolicy::MANAGE_MAIL_TEMPLATES));
});

TestRunner::test('candidate pool is restricted to HR and Nextcloud admins and cannot be delegated', static function () use ($snapshot, $settings): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    assertSame(true, $policy->can(['uid' => 'hr', 'isAdmin' => false, 'groupIds' => ['group-hr']], RecruitmentPermissionPolicy::MANAGE_CANDIDATE_POOL));
    assertSame(true, $policy->can(['uid' => 'admin', 'isAdmin' => true, 'groupIds' => []], RecruitmentPermissionPolicy::MANAGE_CANDIDATE_POOL));
    assertSame(false, $policy->can(['uid' => 'representative', 'isAdmin' => false, 'groupIds' => []], RecruitmentPermissionPolicy::MANAGE_CANDIDATE_POOL));
    assertSame(false, in_array(RecruitmentPermissionPolicy::MANAGE_CANDIDATE_POOL, RecruitmentPermissionPolicy::DELEGATABLE_CAPABILITIES, true));
});

TestRunner::test('exceptional status transitions are restricted to HR and admins and cannot be delegated', static function () use ($snapshot, $settings, $approvedWest): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    assertSame(true, $policy->can(['uid' => 'hr', 'isAdmin' => false, 'groupIds' => ['group-hr']], RecruitmentPermissionPolicy::OVERRIDE_STATUS_TRANSITIONS, $approvedWest));
    assertSame(true, $policy->can(['uid' => 'admin', 'isAdmin' => true, 'groupIds' => []], RecruitmentPermissionPolicy::OVERRIDE_STATUS_TRANSITIONS, $approvedWest));
    assertSame(false, $policy->can(['uid' => 'representative-case', 'isAdmin' => false, 'groupIds' => []], RecruitmentPermissionPolicy::OVERRIDE_STATUS_TRANSITIONS, $approvedWest));
    assertSame(false, in_array(RecruitmentPermissionPolicy::OVERRIDE_STATUS_TRANSITIONS, RecruitmentPermissionPolicy::DELEGATABLE_CAPABILITIES, true));
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
