<?php

declare(strict_types=1);

namespace OCA\LocalBase\AppInfo { final class Application { public const APP_ID = 'localbase'; } }
namespace OCA\Recruitment\AppInfo { final class Application { public const APP_ID = 'adrecruitment'; } }
namespace OCA\Recruitment\Service { interface TemporaryAdminAccessChecker { public function hasActiveGrant(string $uid): bool; } }

namespace {
    use OCA\Recruitment\Exception\AccessDeniedException;
    use OCA\Recruitment\Organization\OrganizationSnapshot;
    use OCA\Recruitment\Organization\OrganizationSnapshotService;
    use OCA\Recruitment\Service\RecruitmentAccessService;
    use OCA\Recruitment\Service\RecruitmentPermissionSettingsService;
    use OCA\Recruitment\Service\TemporaryAdminAccessChecker;
    use OCP\IAppConfig;
    use OCP\IGroup;
    use OCP\IGroupManager;
    use OCP\IUser;
    use OCP\IUserSession;
    use OCP\IUserManager;
    use RecruitmentTests\TestRunner;

    use function RecruitmentTests\assertSame;
    use function RecruitmentTests\assertThrows;
    use function RecruitmentTests\assertTrue;

    final class TestUser implements IUser { public function __construct(private string $uid) {} public function getUID(): string { return $this->uid; } }
    final class TestSession implements IUserSession { public function __construct(private ?IUser $user) {} public function getUser(): ?IUser { return $this->user; } }
    final class TestGroup implements IGroup { public function __construct(private array $members) {} public function inGroup(IUser $user): bool { return in_array($user->getUID(), $this->members, true); } }
    final class TestGroups implements IGroupManager {
        public function __construct(private array $members, private array $admins = []) {}
        public function isAdmin(string $uid): bool { return in_array($uid, $this->admins, true); }
        public function get(string $gid): ?IGroup { return isset($this->members[$gid]) ? new TestGroup($this->members[$gid]) : null; }
    }
    final class TestConfig implements IAppConfig {
        public array $values = [];
        public function getValueString(string $appId, string $key, string $default = ''): string { return $this->values[$appId][$key] ?? $default; }
        public function setValueString(string $appId, string $key, string $value): void { $this->values[$appId][$key] = $value; }
    }
    final class TestUsers implements IUserManager { public function userExists(string $uid): bool { return true; } }

    final class FixedOrganizationSnapshotService extends OrganizationSnapshotService {
        public function __construct(private OrganizationSnapshot $fixedSnapshot) {}
        public function snapshot(): OrganizationSnapshot { return $this->fixedSnapshot; }
    }

    $organization = static fn(): OrganizationSnapshot => OrganizationSnapshot::valid('1.0', 4, 'test-checksum', [
        'staff_hr' => ['groupId' => 'ad-Stab-HR', 'label' => 'Personalreferat'],
        'finance' => ['groupId' => 'ad-Finanzen', 'label' => 'Finanzen'],
        'payroll' => ['groupId' => 'ad-Lohn', 'label' => 'Lohn'],
        'eb' => ['groupId' => 'ad-EB', 'label' => 'Einsatzbegleitung'],
    ], [
        'west' => ['groupId' => 'ad-Bereich-West', 'label' => 'West'],
    ]);

    $dependencies = static function (string $uid, array $members = [], array $admins = [], bool $activeGrant = false) use ($organization): RecruitmentAccessService {
        $config = new TestConfig();
        $groups = new TestGroups($members, $admins);
        return new RecruitmentAccessService(
            new TestSession(new TestUser($uid)),
            $groups,
            new FixedOrganizationSnapshotService($organization()),
            new RecruitmentPermissionSettingsService($config, new TestUsers(), $groups),
            new class($activeGrant) implements TemporaryAdminAccessChecker { public function __construct(private bool $active) {} public function hasActiveGrant(string $uid): bool { return $this->active; } },
        );
    };

    TestRunner::test('anonymous and finance users are denied server-side', static function () use ($dependencies, $organization): void {
        $config = new TestConfig();
        $groups = new TestGroups([]);
        $anonymous = new RecruitmentAccessService(new TestSession(null), $groups, new FixedOrganizationSnapshotService($organization()), new RecruitmentPermissionSettingsService($config, new TestUsers(), $groups), new class implements TemporaryAdminAccessChecker { public function hasActiveGrant(string $uid): bool { return false; } });
        assertThrows(static fn () => $anonymous->requireAnyAccess(), AccessDeniedException::class);

        $finance = $dependencies('finance-user', ['ad-Finanzen' => ['finance-user']]);
        assertSame(false, $finance->can(RecruitmentAccessService::VIEW));
    });

    TestRunner::test('HR receives full access and payroll only released hiring data', static function () use ($dependencies): void {
        $hr = $dependencies('hr-user', ['ad-Stab-HR' => ['hr-user']]);
        assertTrue($hr->can(RecruitmentAccessService::MANAGE_DELEGATIONS));
        assertTrue($hr->can(RecruitmentAccessService::OVERRIDE_STATUS_TRANSITIONS));
        assertTrue($hr->can(RecruitmentAccessService::INTERVIEW, ['id' => 1, 'status' => 'screening', 'areaKey' => '', 'firstGuideAccess' => false]));

        $payroll = $dependencies('payroll-user', ['ad-Lohn' => ['payroll-user']]);
        $approved = ['id' => 2, 'status' => 'approved_for_hire', 'areaKey' => 'west', 'firstGuideAccess' => true];
        assertTrue($payroll->can(RecruitmentAccessService::VIEW_HIRING_DATA, $approved));
        assertTrue($payroll->can(RecruitmentAccessService::EDIT_PAYROLL_DATA, $approved));
        assertSame(false, $payroll->can(RecruitmentAccessService::VIEW, $approved));
    });

    TestRunner::test('Nextcloud admins require an active app-local grant', static function () use ($dependencies): void {
        $admin = $dependencies('admin-user', [], ['admin-user']);
        assertSame(false, $admin->can(RecruitmentAccessService::MANAGE_CATALOG));
        assertSame(false, $admin->can(RecruitmentAccessService::MANAGE_DELEGATIONS));
        $admin = $dependencies('admin-user', [], ['admin-user'], true);
        assertTrue($admin->can(RecruitmentAccessService::MANAGE_CATALOG));
        assertTrue($admin->can(RecruitmentAccessService::MANAGE_DELEGATIONS));
        assertTrue($admin->can(RecruitmentAccessService::OVERRIDE_STATUS_TRANSITIONS));
    });

    TestRunner::test('scoped overview keeps ordered workbench statuses', static function () use ($dependencies): void {
        $guide = $dependencies('guide-user', [
            'ad-EB' => ['guide-user'],
            'ad-Bereich-West' => ['guide-user'],
            'adrecruitment-first-guides' => ['guide-user'],
        ]);
        $overview = $guide->filterOverview([
            'jobs' => [['id' => 4]],
            'people' => [['id' => 3]],
            'applications' => [[
                'id' => 2,
                'personId' => 3,
                'jobId' => 4,
                'status' => 'approved_for_hire',
                'areaKey' => 'west',
                'firstGuideAccess' => true,
            ]],
            'templates' => [],
            'applicationStatuses' => ['received', 'screening', 'approved_for_hire'],
        ]);

        assertSame(['received', 'screening', 'approved_for_hire'], $overview['applicationStatuses'] ?? null);
        assertSame([2], array_column($overview['applications'], 'id'));
    });
}
