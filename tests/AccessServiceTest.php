<?php

declare(strict_types=1);

namespace OCA\LocalBase\AppInfo { final class Application { public const APP_ID = 'localbase'; } }
namespace OCA\Recruitment\AppInfo { final class Application { public const APP_ID = 'adrecruitment'; } }

namespace {
    require_once __DIR__ . '/bootstrap.php';
    require_once dirname(__DIR__, 2) . '/localbase/lib/Organization/AdOrganizationDefinition.php';
    require_once dirname(__DIR__, 2) . '/localbase/lib/Organization/AdOrganizationSettingsService.php';
    require_once dirname(__DIR__, 2) . '/localbase/lib/Organization/AdOrganizationSnapshot.php';
    require_once dirname(__DIR__, 2) . '/localbase/lib/Organization/AdOrganizationSnapshotService.php';
    require_once dirname(__DIR__) . '/lib/Exception/AccessDeniedException.php';
    require_once dirname(__DIR__) . '/lib/Exception/ConflictException.php';
    require_once dirname(__DIR__) . '/lib/Exception/ValidationException.php';
    require_once dirname(__DIR__) . '/lib/Service/RecruitmentPermissionPolicy.php';
    require_once dirname(__DIR__) . '/lib/Service/RecruitmentPermissionSettingsService.php';
    require_once dirname(__DIR__) . '/lib/Service/RecruitmentAccessService.php';

    use OCA\LocalBase\Organization\AdOrganizationSettingsService;
    use OCA\LocalBase\Organization\AdOrganizationSnapshotService;
    use OCA\Recruitment\Exception\AccessDeniedException;
    use OCA\Recruitment\Service\RecruitmentAccessService;
    use OCA\Recruitment\Service\RecruitmentPermissionSettingsService;
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

    $dependencies = static function (string $uid, array $members = [], array $admins = []): RecruitmentAccessService {
        $config = new TestConfig();
        $organizationSettings = new AdOrganizationSettingsService($config);
        $organizationSettings->save($organizationSettings->definition()->toArray());
        $groups = new TestGroups($members, $admins);
        return new RecruitmentAccessService(
            new TestSession(new TestUser($uid)),
            $groups,
            new AdOrganizationSnapshotService($organizationSettings),
            new RecruitmentPermissionSettingsService($config, new TestUsers(), $groups),
        );
    };

    TestRunner::test('anonymous and finance users are denied server-side', static function () use ($dependencies): void {
        $config = new TestConfig();
        $organizationSettings = new AdOrganizationSettingsService($config);
        $organizationSettings->save($organizationSettings->definition()->toArray());
        $groups = new TestGroups([]);
        $anonymous = new RecruitmentAccessService(new TestSession(null), $groups, new AdOrganizationSnapshotService($organizationSettings), new RecruitmentPermissionSettingsService($config, new TestUsers(), $groups));
        assertThrows(static fn () => $anonymous->requireAnyAccess(), AccessDeniedException::class);

        $finance = $dependencies('finance-user', ['ad-Finanzen' => ['finance-user']]);
        assertSame(false, $finance->can(RecruitmentAccessService::VIEW));
    });

    TestRunner::test('HR receives full access and payroll only released hiring data', static function () use ($dependencies): void {
        $hr = $dependencies('hr-user', ['ad-Stab-HR' => ['hr-user']]);
        assertTrue($hr->can(RecruitmentAccessService::MANAGE_DELEGATIONS));
        assertTrue($hr->can(RecruitmentAccessService::INTERVIEW, ['id' => 1, 'status' => 'screening', 'areaKey' => '', 'firstGuideAccess' => false]));

        $payroll = $dependencies('payroll-user', ['ad-Lohn' => ['payroll-user']]);
        $approved = ['id' => 2, 'status' => 'approved_for_hire', 'areaKey' => 'west', 'firstGuideAccess' => true];
        assertTrue($payroll->can(RecruitmentAccessService::VIEW_HIRING_DATA, $approved));
        assertSame(false, $payroll->can(RecruitmentAccessService::VIEW, $approved));
    });

    TestRunner::test('Nextcloud admins retain access even with invalid organization state', static function () use ($dependencies): void {
        $admin = $dependencies('admin-user', [], ['admin-user']);
        assertTrue($admin->can(RecruitmentAccessService::MANAGE_CATALOG));
        assertTrue($admin->can(RecruitmentAccessService::MANAGE_DELEGATIONS));
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
