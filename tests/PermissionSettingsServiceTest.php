<?php

declare(strict_types=1);

namespace OCP {
    if (!interface_exists(IAppConfig::class)) {
        interface IAppConfig {
            public function getValueString(string $appId, string $key, string $default = ''): string;
            public function setValueString(string $appId, string $key, string $value): void;
        }
    }
}

namespace OCA\Recruitment\AppInfo {
    if (!class_exists(Application::class, false)) {
        final class Application { public const APP_ID = 'adrecruitment'; }
    }
}

namespace {
    use OCA\Recruitment\Exception\ConflictException;
    use OCA\Recruitment\Exception\ValidationException;
    use OCA\Recruitment\Service\RecruitmentPermissionPolicy;
    use OCA\Recruitment\Service\RecruitmentPermissionSettingsService;
    use RecruitmentTests\TestRunner;

    use function RecruitmentTests\assertSame;
    use function RecruitmentTests\assertThrows;

    $config = new class implements \OCP\IAppConfig {
        public array $values = [];
        public function getValueString(string $appId, string $key, string $default = ''): string { return $this->values[$appId][$key] ?? $default; }
        public function setValueString(string $appId, string $key, string $value): void { $this->values[$appId][$key] = $value; }
    };
    $users = new class implements \OCP\IUserManager {
        public function userExists(string $uid): bool { return in_array($uid, ['representative', 'unsafe', 'area'], true); }
    };
    $groups = new class implements \OCP\IGroupManager {
        public function isAdmin(string $uid): bool { return false; }
        public function get(string $gid): ?\OCP\IGroup {
            return $gid === 'first-guides-custom' ? new class implements \OCP\IGroup {
                public function inGroup(\OCP\IUser $user): bool { return false; }
            } : null;
        }
    };

    TestRunner::test('permission settings default deny and persist bounded granular representatives', static function () use ($config, $users, $groups): void {
        $service = new RecruitmentPermissionSettingsService($config, $users, $groups);
        $defaults = $service->settings();
        assertSame('adrecruitment-first-guides', $defaults['firstGuideGroupId']);
        assertSame([], $defaults['representatives']);
        assertSame(0, $defaults['revision']);

        $saved = $service->saveRepresentatives([[
            'uid' => ' representative ',
            'capabilities' => [RecruitmentPermissionPolicy::INTERVIEW, RecruitmentPermissionPolicy::VIEW_DOSSIER, RecruitmentPermissionPolicy::INTERVIEW],
            'all' => false,
            'areaKeys' => ['west', 'west'],
            'applicationIds' => [42, 42],
        ]], 0, 'hr-user', ['west', 'south']);
        assertSame(1, $saved['revision']);
        assertSame('representative', $saved['representatives'][0]['uid']);
        assertSame([RecruitmentPermissionPolicy::VIEW_DOSSIER, RecruitmentPermissionPolicy::INTERVIEW], $saved['representatives'][0]['capabilities']);
        assertSame(['west'], $saved['representatives'][0]['areaKeys']);
        assertSame([42], $saved['representatives'][0]['applicationIds']);
        assertSame('hr-user', $saved['updatedBy']);
    });

    TestRunner::test('invalid scopes, delegated permission administration and stale revisions are rejected', static function () use ($config, $users, $groups): void {
        $service = new RecruitmentPermissionSettingsService($config, $users, $groups);
        assertThrows(static fn () => $service->saveRepresentatives([[
            'uid' => 'missing-user', 'capabilities' => [RecruitmentPermissionPolicy::VIEW_DOSSIER],
            'all' => true, 'areaKeys' => [], 'applicationIds' => [],
        ]], 1, 'hr-user', ['west']), ValidationException::class);
        assertThrows(static fn () => $service->saveRepresentatives([[
            'uid' => 'unsafe', 'capabilities' => [RecruitmentPermissionPolicy::MANAGE_DELEGATIONS],
            'all' => true, 'areaKeys' => [], 'applicationIds' => [],
        ]], 1, 'hr-user', ['west']), ValidationException::class);
        assertThrows(static fn () => $service->saveRepresentatives([[
            'uid' => 'area', 'capabilities' => [RecruitmentPermissionPolicy::VIEW_DOSSIER],
            'all' => false, 'areaKeys' => ['unknown'], 'applicationIds' => [],
        ]], 1, 'hr-user', ['west']), ValidationException::class);
        assertThrows(static fn () => $service->saveRepresentatives([[
            'uid' => 'area', 'capabilities' => [RecruitmentPermissionPolicy::MANAGE_CATALOG],
            'all' => false, 'areaKeys' => ['west'], 'applicationIds' => [],
        ]], 1, 'hr-user', ['west']), ValidationException::class);
        assertThrows(static fn () => $service->saveRepresentatives([], 0, 'hr-user', ['west']), ConflictException::class);
    });

    TestRunner::test('only structural administration can change the first-guide group', static function () use ($config, $users, $groups): void {
        $service = new RecruitmentPermissionSettingsService($config, $users, $groups);
        $saved = $service->saveFirstGuideGroup(' first-guides-custom ', 1, 'admin-user');
        assertSame('first-guides-custom', $saved['firstGuideGroupId']);
        assertSame(2, $saved['revision']);
        assertThrows(static fn () => $service->saveFirstGuideGroup('missing-group', 2, 'admin-user'), ValidationException::class);
        assertThrows(static fn () => $service->saveFirstGuideGroup('', 2, 'admin-user'), ValidationException::class);
    });
}
