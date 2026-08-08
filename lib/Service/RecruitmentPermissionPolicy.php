<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\LocalBase\Organization\AdOrganizationSnapshot;

/** Bewertet fachliche Fähigkeiten und Objektscopes ohne UI- oder Controllerannahmen. */
final class RecruitmentPermissionPolicy {
    public const VIEW_DOSSIER = 'view_dossier';
    public const MANAGE_CATALOG = 'manage_catalog';
    public const EDIT_APPLICATIONS = 'edit_applications';
    public const INTERVIEW = 'interview';
    public const EDIT_HIRING_DATA = 'edit_hiring_data';
    public const VIEW_HIRING_DATA = 'view_hiring_data';
    public const MANAGE_DOCUMENTS = 'manage_documents';
    public const COMMUNICATE = 'communicate';
    public const MANAGE_FIRST_GUIDE_ACCESS = 'manage_first_guide_access';
    public const MANAGE_BASIS_QUALIFICATION = 'manage_basis_qualification';
    public const MANAGE_DELEGATIONS = 'manage_delegations';

    public const DELEGATABLE_CAPABILITIES = [
        self::VIEW_DOSSIER,
        self::MANAGE_CATALOG,
        self::EDIT_APPLICATIONS,
        self::INTERVIEW,
        self::EDIT_HIRING_DATA,
        self::VIEW_HIRING_DATA,
        self::MANAGE_DOCUMENTS,
        self::COMMUNICATE,
        self::MANAGE_FIRST_GUIDE_ACCESS,
    ];

    private const FULL_CAPABILITIES = [
        ...self::DELEGATABLE_CAPABILITIES,
        self::MANAGE_BASIS_QUALIFICATION,
        self::MANAGE_DELEGATIONS,
    ];
    private const RELEASED_STATUSES = ['approved_for_hire', 'hired'];

    /** @param array{firstGuideGroupId?: string, representatives?: list<array<string, mixed>>} $settings */
    public function __construct(
        private AdOrganizationSnapshot $organization,
        private array $settings,
    ) {}

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor
     *  @param array<string, mixed>|null $application
     */
    public function can(array $actor, string $capability, ?array $application = null): bool {
        if ($actor['isAdmin']) {
            return in_array($capability, self::FULL_CAPABILITIES, true);
        }
        if (!$this->organization->isValid()) {
            return false;
        }

        if ($this->hasRole($actor, 'staff_hr')) {
            return in_array($capability, self::FULL_CAPABILITIES, true);
        }
        if ($capability === self::VIEW_HIRING_DATA
            && $this->hasRole($actor, 'payroll')
            && $this->isPayrollEligible($application)) {
            return true;
        }
        if ($capability === self::VIEW_DOSSIER && $this->isFirstGuideFor($actor, $application)) {
            return true;
        }

        foreach ($this->settings['representatives'] ?? [] as $representative) {
            if (($representative['uid'] ?? null) !== $actor['uid']
                || !in_array($capability, $representative['capabilities'] ?? [], true)
                || $capability === self::MANAGE_DELEGATIONS) {
                continue;
            }
            if (($representative['all'] ?? false) === true) {
                return true;
            }
            if ($application === null) {
                continue;
            }
            if (in_array((int)($application['id'] ?? 0), $representative['applicationIds'] ?? [], true)
                || in_array((string)($application['areaKey'] ?? ''), $representative['areaKeys'] ?? [], true)) {
                return true;
            }
        }
        return false;
    }

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor */
    public function hasAnyAccess(array $actor): bool {
        if ($actor['isAdmin']) return true;
        if (!$this->organization->isValid()) return false;
        if ($this->hasRole($actor, 'staff_hr') || $this->hasRole($actor, 'payroll')) {
            return true;
        }
        $firstGuideGroup = trim((string)($this->settings['firstGuideGroupId'] ?? ''));
        if ($firstGuideGroup !== '' && in_array($firstGuideGroup, $actor['groupIds'], true) && $this->hasRole($actor, 'eb')) {
            return true;
        }
        foreach ($this->settings['representatives'] ?? [] as $representative) {
            if (($representative['uid'] ?? null) === $actor['uid'] && ($representative['capabilities'] ?? []) !== []) {
                return true;
            }
        }
        return false;
    }

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor */
    public function canSomewhere(array $actor, string $capability): bool {
        if ($actor['isAdmin']) return in_array($capability, self::FULL_CAPABILITIES, true);
        if (!$this->organization->isValid()) return false;
        if ($this->hasRole($actor, 'staff_hr')) return in_array($capability, self::FULL_CAPABILITIES, true);
        if ($capability === self::VIEW_HIRING_DATA && $this->hasRole($actor, 'payroll')) return true;
        $firstGuideGroup = trim((string)($this->settings['firstGuideGroupId'] ?? ''));
        if ($capability === self::VIEW_DOSSIER && $firstGuideGroup !== ''
            && in_array($firstGuideGroup, $actor['groupIds'], true) && $this->hasRole($actor, 'eb')) return true;
        foreach ($this->settings['representatives'] ?? [] as $representative) {
            if (($representative['uid'] ?? null) === $actor['uid']
                && in_array($capability, $representative['capabilities'] ?? [], true)) return true;
        }
        return false;
    }

    /** Unzugeordnete Mails besitzen noch keinen Objekt-Scope und benötigen deshalb einen globalen Bearbeitungsscope. */
    public function canManageUnassignedInbox(array $actor): bool {
        if ($actor['isAdmin']) return true;
        if (!$this->organization->isValid()) return false;
        if ($this->hasRole($actor, 'staff_hr')) return true;
        foreach ($this->settings['representatives'] ?? [] as $representative) {
            if (($representative['uid'] ?? null) === $actor['uid']
                && ($representative['all'] ?? false) === true
                && in_array(self::EDIT_APPLICATIONS, $representative['capabilities'] ?? [], true)) {
                return true;
            }
        }
        return false;
    }

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor */
    private function hasRole(array $actor, string $roleKey): bool {
        $groupId = $this->organization->roleGroupId($roleKey);
        return $groupId !== null && in_array($groupId, $actor['groupIds'], true);
    }

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor
     *  @param array<string, mixed>|null $application
     */
    private function isFirstGuideFor(array $actor, ?array $application): bool {
        if (!$this->isReleased($application) || ($application['firstGuideAccess'] ?? false) !== true || !$this->hasRole($actor, 'eb')) {
            return false;
        }
        $firstGuideGroup = trim((string)($this->settings['firstGuideGroupId'] ?? ''));
        $areaGroup = $this->organization->areaGroupId((string)($application['areaKey'] ?? ''));
        return $firstGuideGroup !== ''
            && $areaGroup !== null
            && in_array($firstGuideGroup, $actor['groupIds'], true)
            && in_array($areaGroup, $actor['groupIds'], true);
    }

    /** @param array<string, mixed>|null $application */
    private function isReleased(?array $application): bool {
        return $application !== null && in_array((string)($application['status'] ?? ''), self::RELEASED_STATUSES, true);
    }

    /** @param array<string,mixed>|null $application */
    private function isPayrollEligible(?array $application): bool {
        if ($this->isReleased($application)) return true;
        if ($application === null || (string)($application['status'] ?? '') !== 'basis_qualification') return false;
        return in_array(
            (string)($application['basisQualification']['result'] ?? ''),
            ['pending', 'suitable'],
            true,
        );
    }
}
