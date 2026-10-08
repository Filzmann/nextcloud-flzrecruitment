<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;

use OCA\FlzRecruitment\Exception\AccessDeniedException;
use OCA\FlzRecruitment\Organization\OrganizationSnapshot;
use OCA\FlzRecruitment\Organization\OrganizationSnapshotService;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

/** Zentrale serverseitige Auswertung von Fähigkeit, Akteur und konkretem Objektscope. */
final class RecruitmentAccessService {
    public const VIEW = RecruitmentPermissionPolicy::VIEW_DOSSIER;
    public const MANAGE_CATALOG = RecruitmentPermissionPolicy::MANAGE_CATALOG;
    public const EDIT_APPLICATIONS = RecruitmentPermissionPolicy::EDIT_APPLICATIONS;
    public const INTERVIEW = RecruitmentPermissionPolicy::INTERVIEW;
    public const EDIT_HIRING_DATA = RecruitmentPermissionPolicy::EDIT_HIRING_DATA;
    public const EDIT_PAYROLL_DATA = RecruitmentPermissionPolicy::EDIT_PAYROLL_DATA;
    public const VIEW_HIRING_DATA = RecruitmentPermissionPolicy::VIEW_HIRING_DATA;
    public const MANAGE_DOCUMENTS = RecruitmentPermissionPolicy::MANAGE_DOCUMENTS;
    public const COMMUNICATE = RecruitmentPermissionPolicy::COMMUNICATE;
    public const MANAGE_FIRST_GUIDE_ACCESS = RecruitmentPermissionPolicy::MANAGE_FIRST_GUIDE_ACCESS;
    public const MANAGE_BASIS_QUALIFICATION = RecruitmentPermissionPolicy::MANAGE_BASIS_QUALIFICATION;
    public const MANAGE_MAIL_TEMPLATES = RecruitmentPermissionPolicy::MANAGE_MAIL_TEMPLATES;
    public const MANAGE_CANDIDATE_POOL = RecruitmentPermissionPolicy::MANAGE_CANDIDATE_POOL;
    public const MANAGE_DELEGATIONS = RecruitmentPermissionPolicy::MANAGE_DELEGATIONS;
    public const OVERRIDE_STATUS_TRANSITIONS = RecruitmentPermissionPolicy::OVERRIDE_STATUS_TRANSITIONS;

    public function __construct(
        private IUserSession $session,
        private IGroupManager $groups,
        private OrganizationSnapshotService $organization,
        private RecruitmentPermissionSettingsService $settings,
        private TemporaryAdminAccessChecker $temporaryAdminAccess,
    ) {}

    public function currentUser(): ?IUser { return $this->session->getUser(); }
    public function currentUid(): string { return $this->currentUser()?->getUID() ?? ''; }
    public function isNextcloudAdmin(): bool {
        $uid = $this->currentUid();
        return $uid !== '' && $this->groups->isAdmin($uid);
    }

    /** @param array<string, mixed>|null $application */
    public function can(string $capability, ?array $application = null): bool {
        return $this->policy()->can($this->actor(), $capability, $application);
    }

    /** @param array<string, mixed>|null $application */
    public function require(string $capability, ?array $application = null): void {
        if (!$this->can($capability, $application)) throw new AccessDeniedException();
    }

    public function requireAnyAccess(): void {
        if (!$this->policy()->hasAnyAccess($this->actor())) throw new AccessDeniedException();
    }

    public function requireSomewhere(string $capability): void {
        if (!$this->policy()->canSomewhere($this->actor(), $capability)) throw new AccessDeniedException();
    }

    public function canSomewhere(string $capability): bool {
        return $this->policy()->canSomewhere($this->actor(), $capability);
    }

    public function canManageUnassignedInbox(): bool {
        return $this->policy()->canManageUnassignedInbox($this->actor());
    }

    public function requireManageUnassignedInbox(): void {
        if (!$this->canManageUnassignedInbox()) throw new AccessDeniedException();
    }

    /** @return array<string, bool> */
    public function capabilities(): array {
        $actor = $this->actor();
        $policy = $this->policy();
        $result = [];
        foreach ([
            ...RecruitmentPermissionPolicy::DELEGATABLE_CAPABILITIES,
            self::MANAGE_BASIS_QUALIFICATION,
            self::EDIT_PAYROLL_DATA,
            self::MANAGE_MAIL_TEMPLATES,
            self::MANAGE_CANDIDATE_POOL,
            self::MANAGE_DELEGATIONS,
            self::OVERRIDE_STATUS_TRANSITIONS,
        ] as $capability) {
            $result[$capability] = $policy->canSomewhere($actor, $capability);
        }
        $result['manage_unassigned_inbox'] = $policy->canManageUnassignedInbox($actor);
        return $result;
    }

    /** @param array<string, mixed> $overview
     *  @return array<string, mixed>
     */
    public function filterOverview(array $overview): array {
        if ($this->can(self::VIEW)) return $overview;
        $applications = array_values(array_filter(
            $overview['applications'] ?? [],
            fn(array $application): bool => $this->can(self::VIEW, $application),
        ));
        $personIds = array_fill_keys(array_column($applications, 'personId'), true);
        $jobIds = array_fill_keys(array_column($applications, 'jobId'), true);
        $actor = $this->actor();
        $policy = $this->policy();
        return [
            'jobs' => $policy->canSomewhere($actor, self::MANAGE_CATALOG)
                ? ($overview['jobs'] ?? [])
                : array_values(array_filter($overview['jobs'] ?? [], static fn(array $job): bool => isset($jobIds[$job['id']]))),
            'people' => array_values(array_filter($overview['people'] ?? [], static fn(array $person): bool => isset($personIds[$person['id']]))),
            'applications' => $applications,
            'templates' => ($policy->canSomewhere($actor, self::INTERVIEW) || $policy->canSomewhere($actor, self::MANAGE_CATALOG))
                ? ($overview['templates'] ?? [])
                : [],
            'applicationStatuses' => $overview['applicationStatuses'] ?? [],
        ];
    }

    /** @param list<array<string,mixed>> $items
     *  @return list<array<string,mixed>>
     */
    public function filterHiringData(array $items): array {
        return array_values(array_filter($items, fn(array $item): bool => $this->can(self::VIEW_HIRING_DATA, [
            'id' => (int)($item['applicationId'] ?? 0),
            'status' => (string)($item['status'] ?? ''),
            'areaKey' => (string)($item['areaKey'] ?? ''),
            'basisQualification' => $item['basisQualification'] ?? null,
        ])));
    }

    public function organization(): OrganizationSnapshot { return $this->organization->snapshot(); }
    /** @return array<string, mixed> */
    public function permissionSettings(): array { return $this->settings->settings(); }

    /** @return array{uid: string, isAdmin: bool, groupIds: list<string>} */
    private function actor(): array {
        $user = $this->currentUser();
        if ($user === null) return ['uid' => '', 'isAdmin' => false, 'groupIds' => []];
        $snapshot = $this->organization->snapshot();
        $candidateGroups = [];
        foreach ($snapshot->roleKeys() as $roleKey) {
            $groupId = $snapshot->roleGroupId($roleKey);
            if ($groupId !== null) $candidateGroups[] = $groupId;
        }
        foreach ($snapshot->areaKeys() as $areaKey) {
            $groupId = $snapshot->areaGroupId($areaKey);
            if ($groupId !== null) $candidateGroups[] = $groupId;
        }
        $candidateGroups[] = (string)$this->settings->settings()['firstGuideGroupId'];
        $groupIds = [];
        foreach (array_values(array_unique($candidateGroups)) as $groupId) {
            if ($groupId !== '' && $this->groups->get($groupId)?->inGroup($user) === true) $groupIds[] = $groupId;
        }
        return ['uid' => $user->getUID(), 'isAdmin' => $this->groups->isAdmin($user->getUID()) && $this->temporaryAdminAccess->hasActiveGrant($user->getUID()), 'groupIds' => $groupIds];
    }

    private function policy(): RecruitmentPermissionPolicy {
        return new RecruitmentPermissionPolicy($this->organization->snapshot(), $this->settings->settings());
    }
}
