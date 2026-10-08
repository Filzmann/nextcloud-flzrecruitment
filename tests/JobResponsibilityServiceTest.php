<?php

declare(strict_types=1);

namespace OCA\LocalBase\AppInfo { final class Application { public const APP_ID = 'localbase'; } }

namespace {
    use OCA\FlzRecruitment\Exception\ValidationException;
    use OCA\FlzRecruitment\Organization\OrganizationSnapshot;
    use OCA\FlzRecruitment\Organization\OrganizationSnapshotService;
    use OCA\FlzRecruitment\Service\JobResponsibilityService;
    use OCP\IGroup;
    use OCP\IGroupManager;
    use OCP\IUser;
    use RecruitmentTests\TestRunner;

    use function RecruitmentTests\assertSame;
    use function RecruitmentTests\assertThrows;

    final class ResponsibilityUser implements IUser {
        public function __construct(private string $uid, private string $displayName) {}
        public function getUID(): string { return $this->uid; }
        public function getDisplayName(): string { return $this->displayName; }
    }

    final class ResponsibilityGroup implements IGroup {
        /** @param list<IUser> $users */
        public function __construct(private array $users) {}
        public function inGroup(IUser $user): bool { return in_array($user, $this->users, true); }
        public function getUsers(): array { return $this->users; }
        public function searchUsers(string $search, ?int $limit = null, ?int $offset = null): array {
            $search = strtolower($search);
            return array_slice(array_values(array_filter($this->users, static fn(IUser $user): bool =>
                str_contains(strtolower($user->getUID()), $search)
                || str_contains(strtolower($user->getDisplayName()), $search)
            )), $offset ?? 0, $limit);
        }
    }

    final class ResponsibilityGroups implements IGroupManager {
        /** @param array<string,IGroup> $groups */
        public function __construct(private array $groups) {}
        public function isAdmin(string $uid): bool { return false; }
        public function get(string $gid): ?IGroup { return $this->groups[$gid] ?? null; }
    }

    final class ResponsibilityOrganizationService extends OrganizationSnapshotService {
        public function __construct(private OrganizationSnapshot $fixedSnapshot) {}
        public function snapshot(): OrganizationSnapshot { return $this->fixedSnapshot; }
    }

    TestRunner::test('job responsibility choices and user search stay inside relevant organization groups', static function (): void {
        $alex = new ResponsibilityUser('alex', 'Alex Beispiel');
        $bea = new ResponsibilityUser('bea', 'Bea Muster');
        $service = new JobResponsibilityService(
            new ResponsibilityOrganizationService(OrganizationSnapshot::valid('1.0', 4, 'test-checksum', [
                'staff_hr' => ['groupId' => 'flz-Stab-HR', 'label' => 'Personalreferat'],
                'eb' => ['groupId' => 'flz-EB', 'label' => 'Einsatzbegleitung'],
                'payroll' => ['groupId' => 'flz-Lohn', 'label' => 'Lohn'],
            ], [])),
            new ResponsibilityGroups([
                'flz-Stab-HR' => new ResponsibilityGroup([$alex]),
                'flz-EB' => new ResponsibilityGroup([$bea]),
                'flz-Lohn' => new ResponsibilityGroup([new ResponsibilityUser('lohn', 'Lohn')]),
            ]),
        );

        $groups = $service->groups('assistance');
        assertSame(true, in_array('flz-Stab-HR', array_column($groups, 'id'), true));
        assertSame(true, in_array('flz-EB', array_column($groups, 'id'), true));
        assertSame(false, in_array('flz-Lohn', array_column($groups, 'id'), true));
        assertSame([['uid' => 'alex', 'displayName' => 'Alex Beispiel']], $service->searchUsers('assistance', ['flz-Stab-HR'], 'Ale'));

        $service->validate('assistance', ['flz-Stab-HR'], ['alex']);
        assertThrows(static fn () => $service->validate('assistance', ['flz-Stab-HR'], ['bea']), ValidationException::class);
        assertThrows(static fn () => $service->validate('assistance', ['flz-Lohn'], []), ValidationException::class);
        assertThrows(static fn () => $service->searchUsers('assistance', ['flz-Stab-HR'], 'a'), ValidationException::class);
    });
}
