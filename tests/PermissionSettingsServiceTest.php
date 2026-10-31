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

namespace OCA\FlzRecruitment\AppInfo {
    if (!class_exists(Application::class, false)) {
        final class Application { public const APP_ID = 'flzrecruitment'; }
    }
}

namespace {
    use OCA\FlzRecruitment\Exception\ConflictException;
    use OCA\FlzRecruitment\Exception\ValidationException;
    use OCA\FlzRecruitment\Organization\OrganizationSnapshot;
    use OCA\FlzRecruitment\Organization\OrganizationSnapshotService;
    use OCA\FlzRecruitment\Service\RecruitmentPermissionPolicy;
    use OCA\FlzRecruitment\Service\RecruitmentPermissionSettingsService;
    use RecruitmentTests\TestRunner;

    use function RecruitmentTests\assertSame;
    use function RecruitmentTests\assertThrows;

    final class PermissionSettingsOrganizationService extends OrganizationSnapshotService {
        public function __construct(private OrganizationSnapshot $fixedSnapshot) {}
        public function snapshot(): OrganizationSnapshot { return $this->fixedSnapshot; }
    }

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
            return in_array($gid, ['group-eb', 'area-west', 'first-guides-custom'], true) ? new class implements \OCP\IGroup {
                public function inGroup(\OCP\IUser $user): bool { return false; }
            } : null;
        }
    };
    $organization = new PermissionSettingsOrganizationService(OrganizationSnapshot::valid('1.0', 4, 'test-checksum', [
        'staff_hr' => ['groupId' => 'group-hr', 'label' => 'Personalreferat'],
        'eb' => ['groupId' => 'group-eb', 'label' => 'Einsatzbegleitung'],
        'payroll' => ['groupId' => 'group-payroll', 'label' => 'Lohnbuchhaltung'],
    ], [
        'west' => ['groupId' => 'area-west', 'label' => 'West'],
    ]));

    TestRunner::test('all sixteen action capabilities default to staff HR and granular representatives stay bounded', static function () use ($config, $users, $groups, $organization): void {
        $service = new RecruitmentPermissionSettingsService($config, $users, $organization);
        $defaults = $service->settings();
        assertSame(2, $defaults['version']);
        assertSame(true, $defaults['valid']);
        assertSame(RecruitmentPermissionPolicy::ROLE_ASSIGNABLE_CAPABILITIES, array_keys($defaults['roleCapabilities']));
        foreach ($defaults['roleCapabilities'] as $capability => $roleKeys) {
            assertSame(
                $capability === RecruitmentPermissionPolicy::VIEW_FIRST_GUIDE_DOSSIER ? ['eb'] : ['staff_hr'],
                $roleKeys,
            );
        }
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
        assertSame(false, array_key_exists('updatedBy', $saved));
    });

    TestRunner::test('role capability matrix stores semantic organization roles and rejects manipulated mappings', static function () use ($config, $users, $groups, $organization): void {
        $service = new RecruitmentPermissionSettingsService($config, $users, $organization);
        $saved = $service->saveRoleCapabilities([
            RecruitmentPermissionPolicy::VIEW_DOSSIER => ['staff_hr', 'eb', 'eb'],
            RecruitmentPermissionPolicy::EDIT_PAYROLL_DATA => ['staff_hr', 'payroll'],
        ], 1, 'privacy-officer');
        assertSame(['staff_hr', 'eb'], $saved['roleCapabilities'][RecruitmentPermissionPolicy::VIEW_DOSSIER]);
        assertSame(['staff_hr', 'payroll'], $saved['roleCapabilities'][RecruitmentPermissionPolicy::EDIT_PAYROLL_DATA]);
        assertSame([], $saved['roleCapabilities'][RecruitmentPermissionPolicy::INTERVIEW]);
        assertSame([], $saved['roleCapabilities'][RecruitmentPermissionPolicy::VIEW_FIRST_GUIDE_DOSSIER]);
        assertSame(2, $saved['revision']);

        $before = $config->values;
        assertThrows(static fn () => $service->saveRoleCapabilities([
            RecruitmentPermissionPolicy::VIEW_DOSSIER => ['unknown-role'],
        ], 2, 'privacy-officer'), ValidationException::class);
        assertSame($before, $config->values);
        assertThrows(static fn () => $service->saveRoleCapabilities([
            'forged_capability' => ['staff_hr'],
        ], 2, 'privacy-officer'), ValidationException::class);
        assertSame($before, $config->values);
        assertThrows(static fn () => $service->saveRoleCapabilities([
            RecruitmentPermissionPolicy::MANAGE_DELEGATIONS => ['eb'],
        ], 2, 'privacy-officer'), ValidationException::class);
        assertSame($before, $config->values);
    });

    TestRunner::test('invalid scopes, delegated permission administration and stale revisions are rejected', static function () use ($config, $users, $groups, $organization): void {
        $service = new RecruitmentPermissionSettingsService($config, $users, $organization);
        assertThrows(static fn () => $service->saveRepresentatives([[
            'uid' => 'missing-user', 'capabilities' => [RecruitmentPermissionPolicy::VIEW_DOSSIER],
            'all' => true, 'areaKeys' => [], 'applicationIds' => [],
        ]], 2, 'hr-user', ['west']), ValidationException::class);
        assertThrows(static fn () => $service->saveRepresentatives([[
            'uid' => 'unsafe', 'capabilities' => ['forged_capability'],
            'all' => true, 'areaKeys' => [], 'applicationIds' => [],
        ]], 2, 'hr-user', ['west']), ValidationException::class);
        assertThrows(static fn () => $service->saveRepresentatives([[
            'uid' => 'area', 'capabilities' => [RecruitmentPermissionPolicy::VIEW_DOSSIER],
            'all' => false, 'areaKeys' => ['unknown'], 'applicationIds' => [],
        ]], 2, 'hr-user', ['west']), ValidationException::class);
        assertThrows(static fn () => $service->saveRepresentatives([[
            'uid' => 'area', 'capabilities' => [RecruitmentPermissionPolicy::MANAGE_CATALOG],
            'all' => false, 'areaKeys' => ['west'], 'applicationIds' => [],
        ]], 2, 'hr-user', ['west']), ValidationException::class);
        $before = $config->values;
        assertThrows(static fn () => $service->saveRepresentatives([[
            'uid' => 'representative', 'capabilities' => [RecruitmentPermissionPolicy::MANAGE_DELEGATIONS],
            'all' => true, 'areaKeys' => [], 'applicationIds' => [],
        ]], 2, 'hr-user', ['west']), ValidationException::class);
        assertSame($before, $config->values);
        assertThrows(static fn () => $service->saveRepresentatives([], 0, 'hr-user', ['west']), ConflictException::class);
    });

    TestRunner::test('role capability changes fail closed without a valid organization snapshot', static function () use ($config, $users, $groups): void {
        $service = new RecruitmentPermissionSettingsService(
            $config,
            $users,
            new PermissionSettingsOrganizationService(OrganizationSnapshot::unavailable(OrganizationSnapshot::UNAVAILABLE)),
        );
        assertThrows(static fn () => $service->saveRoleCapabilities([
            RecruitmentPermissionPolicy::VIEW_DOSSIER => ['staff_hr'],
        ], 2, 'privacy-officer'), ValidationException::class);
    });

    TestRunner::test('a corrupted stored permission configuration denies role and representative rights', static function () use ($config, $users, $organization): void {
        $previous = $config->values['flzrecruitment']['permission_settings'] ?? null;
        $config->values['flzrecruitment']['permission_settings'] = '{"version":2';

        $settings = (new RecruitmentPermissionSettingsService($config, $users, $organization))->settings();

        assertSame(0, $settings['revision']);
        assertSame(false, $settings['valid']);
        assertSame([], $settings['representatives']);
        foreach ($settings['roleCapabilities'] as $roleKeys) {
            assertSame([], $roleKeys);
        }

        if ($previous === null) {
            unset($config->values['flzrecruitment']['permission_settings']);
        } else {
            $config->values['flzrecruitment']['permission_settings'] = $previous;
        }
    });
}
