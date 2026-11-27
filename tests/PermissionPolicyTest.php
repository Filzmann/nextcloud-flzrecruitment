<?php

declare(strict_types=1);

use OCA\FlzRecruitment\Organization\OrganizationSnapshot;
use OCA\FlzRecruitment\Service\RecruitmentPermissionPolicy;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertTrue;

$snapshot = OrganizationSnapshot::valid('1.0', 3, 'test-checksum', [
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
    'roleCapabilities' => [
        ...array_fill_keys(RecruitmentPermissionPolicy::ACTION_CAPABILITIES, ['staff_hr']),
        RecruitmentPermissionPolicy::VIEW_FIRST_GUIDE_DOSSIER => ['eb'],
    ],
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
        'capabilities' => [RecruitmentPermissionPolicy::EDIT_APPLICATIONS, RecruitmentPermissionPolicy::MANAGE_UNASSIGNED_INBOX],
        'all' => true,
        'areaKeys' => [],
        'applicationIds' => [],
    ]],
];

$approvedWest = ['id' => 42, 'status' => 'approved_for_hire', 'areaKey' => 'west', 'firstGuideAccess' => true];
$approvedSouth = ['id' => 43, 'status' => 'approved_for_hire', 'areaKey' => 'south', 'firstGuideAccess' => true];

TestRunner::test('configured semantic roles grant action capabilities while unassigned roles grant none', static function () use ($snapshot, $settings, $approvedWest): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    assertTrue($policy->can(['uid' => 'admin', 'isAdmin' => true, 'isPrivacyOfficer' => false, 'groupIds' => []], RecruitmentPermissionPolicy::MANAGE_DELEGATIONS, $approvedWest));
    assertTrue($policy->can(['uid' => 'hr', 'isAdmin' => false, 'isPrivacyOfficer' => false, 'groupIds' => ['group-hr']], RecruitmentPermissionPolicy::EDIT_HIRING_DATA, $approvedWest));
    assertTrue($policy->can(['uid' => 'hr', 'isAdmin' => false, 'isPrivacyOfficer' => false, 'groupIds' => ['group-hr']], RecruitmentPermissionPolicy::EDIT_PAYROLL_DATA, $approvedWest));
    assertSame(false, $policy->can(['uid' => 'finance', 'isAdmin' => false, 'isPrivacyOfficer' => false, 'groupIds' => ['group-finance']], RecruitmentPermissionPolicy::VIEW_DOSSIER, $approvedWest));

    $custom = $settings;
    $custom['roleCapabilities'][RecruitmentPermissionPolicy::INTERVIEW] = ['eb'];
    $customPolicy = new RecruitmentPermissionPolicy($snapshot, $custom);
    assertTrue($customPolicy->can(['uid' => 'eb', 'isAdmin' => false, 'isPrivacyOfficer' => false, 'groupIds' => ['group-eb']], RecruitmentPermissionPolicy::INTERVIEW, $approvedWest));
    assertSame(false, $customPolicy->can(['uid' => 'hr', 'isAdmin' => false, 'isPrivacyOfficer' => false, 'groupIds' => ['group-hr']], RecruitmentPermissionPolicy::INTERVIEW, $approvedWest));
});

TestRunner::test('privacy officers manage the permission matrix and delegations without receiving dossier rights', static function () use ($snapshot, $settings, $approvedWest): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    $privacy = ['uid' => 'privacy', 'isAdmin' => false, 'isPrivacyOfficer' => true, 'groupIds' => []];
    assertTrue($policy->canManagePermissionMatrix($privacy));
    assertTrue($policy->can($privacy, RecruitmentPermissionPolicy::MANAGE_DELEGATIONS));
    assertTrue($policy->can($privacy, RecruitmentPermissionPolicy::MANAGE_TEMPORARY_ADMIN_ACCESS));
    assertSame(false, $policy->can($privacy, RecruitmentPermissionPolicy::VIEW_DOSSIER, $approvedWest));
    assertSame(false, $policy->canManagePermissionMatrix(['uid' => 'hr', 'isAdmin' => false, 'isPrivacyOfficer' => false, 'groupIds' => ['group-hr']]));
});

TestRunner::test('payroll receives only the hiring projection after approval', static function () use ($snapshot, $settings, $approvedWest): void {
    $configured = $settings;
    $configured['roleCapabilities'][RecruitmentPermissionPolicy::VIEW_HIRING_DATA] = ['payroll'];
    $configured['roleCapabilities'][RecruitmentPermissionPolicy::EDIT_PAYROLL_DATA] = ['payroll'];
    $policy = new RecruitmentPermissionPolicy($snapshot, $configured);
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
    assertSame(false, $policy->can(['uid' => 'hr', 'isAdmin' => false, 'groupIds' => ['group-hr']], RecruitmentPermissionPolicy::EDIT_PAYROLL_DATA, $pending));
});

TestRunner::test('basis qualification management defaults to HR and remains explicitly delegatable', static function () use ($snapshot, $settings): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    $hr = ['uid' => 'hr', 'isAdmin' => false, 'groupIds' => ['group-hr']];
    $admin = ['uid' => 'admin', 'isAdmin' => true, 'groupIds' => []];
    $representative = ['uid' => 'representative-area', 'isAdmin' => false, 'groupIds' => ['group-pfk']];

    assertTrue($policy->can($hr, RecruitmentPermissionPolicy::MANAGE_BASIS_QUALIFICATION));
    assertTrue($policy->can($admin, RecruitmentPermissionPolicy::MANAGE_BASIS_QUALIFICATION));
    assertSame(false, $policy->can($representative, RecruitmentPermissionPolicy::MANAGE_BASIS_QUALIFICATION));
    assertSame(true, in_array(RecruitmentPermissionPolicy::MANAGE_BASIS_QUALIFICATION, RecruitmentPermissionPolicy::DELEGATABLE_CAPABILITIES, true));
});

TestRunner::test('only HR and Nextcloud admins manage mail templates while communication remains scoped', static function () use ($snapshot, $settings): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    assertSame(true, $policy->can(['uid' => 'hr', 'isAdmin' => false, 'groupIds' => ['group-hr']], RecruitmentPermissionPolicy::MANAGE_MAIL_TEMPLATES));
    assertSame(true, $policy->can(['uid' => 'admin', 'isAdmin' => true, 'groupIds' => []], RecruitmentPermissionPolicy::MANAGE_MAIL_TEMPLATES));
    assertSame(false, $policy->can(['uid' => 'representative', 'isAdmin' => false, 'groupIds' => []], RecruitmentPermissionPolicy::MANAGE_MAIL_TEMPLATES));
});

TestRunner::test('candidate pool defaults to HR and can be delegated explicitly', static function () use ($snapshot, $settings): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    assertSame(true, $policy->can(['uid' => 'hr', 'isAdmin' => false, 'groupIds' => ['group-hr']], RecruitmentPermissionPolicy::MANAGE_CANDIDATE_POOL));
    assertSame(true, $policy->can(['uid' => 'admin', 'isAdmin' => true, 'groupIds' => []], RecruitmentPermissionPolicy::MANAGE_CANDIDATE_POOL));
    assertSame(false, $policy->can(['uid' => 'representative', 'isAdmin' => false, 'groupIds' => []], RecruitmentPermissionPolicy::MANAGE_CANDIDATE_POOL));
    assertSame(true, in_array(RecruitmentPermissionPolicy::MANAGE_CANDIDATE_POOL, RecruitmentPermissionPolicy::DELEGATABLE_CAPABILITIES, true));
});

TestRunner::test('exceptional status transitions default to HR and can be delegated explicitly', static function () use ($snapshot, $settings, $approvedWest): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    assertSame(true, $policy->can(['uid' => 'hr', 'isAdmin' => false, 'groupIds' => ['group-hr']], RecruitmentPermissionPolicy::OVERRIDE_STATUS_TRANSITIONS, $approvedWest));
    assertSame(true, $policy->can(['uid' => 'admin', 'isAdmin' => true, 'groupIds' => []], RecruitmentPermissionPolicy::OVERRIDE_STATUS_TRANSITIONS, $approvedWest));
    assertSame(false, $policy->can(['uid' => 'representative-case', 'isAdmin' => false, 'groupIds' => []], RecruitmentPermissionPolicy::OVERRIDE_STATUS_TRANSITIONS, $approvedWest));
    assertSame(true, in_array(RecruitmentPermissionPolicy::OVERRIDE_STATUS_TRANSITIONS, RecruitmentPermissionPolicy::DELEGATABLE_CAPABILITIES, true));
});

TestRunner::test('first guides need eligibility, matching area, approved status and active manual grant', static function () use ($snapshot, $settings, $approvedWest): void {
    $policy = new RecruitmentPermissionPolicy($snapshot, $settings);
    $guide = ['uid' => 'guide', 'isAdmin' => false, 'groupIds' => ['group-eb', 'area-west']];
    assertTrue($policy->can($guide, RecruitmentPermissionPolicy::VIEW_DOSSIER, $approvedWest));
    assertTrue($policy->can($guide, RecruitmentPermissionPolicy::VIEW_FIRST_GUIDE_DOSSIER, $approvedWest));
    assertSame(false, $policy->can($guide, RecruitmentPermissionPolicy::INTERVIEW, $approvedWest));
    assertSame(false, $policy->can($guide, RecruitmentPermissionPolicy::VIEW_DOSSIER, array_replace($approvedWest, ['firstGuideAccess' => false])));
    assertSame(false, $policy->can($guide, RecruitmentPermissionPolicy::VIEW_DOSSIER, array_replace($approvedWest, ['areaKey' => 'south'])));
    assertSame(false, $policy->can(['uid' => 'not-eb', 'isAdmin' => false, 'groupIds' => ['group-pfk', 'area-west']], RecruitmentPermissionPolicy::VIEW_DOSSIER, $approvedWest));

    $disabled = $settings;
    $disabled['roleCapabilities'][RecruitmentPermissionPolicy::VIEW_FIRST_GUIDE_DOSSIER] = [];
    assertSame(false, (new RecruitmentPermissionPolicy($snapshot, $disabled))->can($guide, RecruitmentPermissionPolicy::VIEW_DOSSIER, $approvedWest));
});

TestRunner::test('delegatable action capabilities remain bounded by explicit scope', static function () use ($snapshot, $settings, $approvedWest, $approvedSouth): void {
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
    assertSame(false, in_array(RecruitmentPermissionPolicy::MANAGE_DELEGATIONS, RecruitmentPermissionPolicy::DELEGATABLE_CAPABILITIES, true));
});

TestRunner::test('delegation administration cannot be gained through a role mapping or recursive delegation', static function () use ($snapshot, $settings, $approvedWest): void {
    $manipulated = $settings;
    $manipulated['roleCapabilities'][RecruitmentPermissionPolicy::MANAGE_DELEGATIONS] = ['eb'];
    $manipulated['representatives'][] = [
        'uid' => 'recursive-delegate',
        'capabilities' => [RecruitmentPermissionPolicy::MANAGE_DELEGATIONS],
        'all' => true,
        'areaKeys' => [],
        'applicationIds' => [],
    ];
    $policy = new RecruitmentPermissionPolicy($snapshot, $manipulated);

    assertSame(false, $policy->can(
        ['uid' => 'role-user', 'isAdmin' => false, 'isPrivacyOfficer' => false, 'groupIds' => ['group-eb']],
        RecruitmentPermissionPolicy::MANAGE_DELEGATIONS,
        $approvedWest,
    ));
    assertSame(false, $policy->can(
        ['uid' => 'recursive-delegate', 'isAdmin' => false, 'isPrivacyOfficer' => false, 'groupIds' => []],
        RecruitmentPermissionPolicy::MANAGE_DELEGATIONS,
        $approvedWest,
    ));
});

TestRunner::test('invalid organization snapshots deny organization-derived non-admin paths', static function () use ($settings, $approvedWest): void {
    $invalid = OrganizationSnapshot::unavailable(OrganizationSnapshot::INVALID, '1.0', 3, 'invalid-checksum');
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
    assertSame(false, $policy->canManageUnassignedInbox(['uid' => 'guide', 'isAdmin' => false, 'groupIds' => ['group-eb', 'group-pfk', 'area-west']]));
});
