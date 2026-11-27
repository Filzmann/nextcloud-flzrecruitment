<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;

use OCA\FlzRecruitment\Organization\OrganizationSnapshot;

/** Bewertet fachliche Fähigkeiten und Objektscopes ohne UI- oder Controllerannahmen. */
final class RecruitmentPermissionPolicy {
    public const VIEW_DOSSIER = 'view_dossier';
    public const MANAGE_CATALOG = 'manage_catalog';
    public const EDIT_APPLICATIONS = 'edit_applications';
    public const INTERVIEW = 'interview';
    public const EDIT_HIRING_DATA = 'edit_hiring_data';
    public const EDIT_PAYROLL_DATA = 'edit_payroll_data';
    public const VIEW_HIRING_DATA = 'view_hiring_data';
    public const MANAGE_DOCUMENTS = 'manage_documents';
    public const COMMUNICATE = 'communicate';
    public const MANAGE_FIRST_GUIDE_ACCESS = 'manage_first_guide_access';
    public const MANAGE_BASIS_QUALIFICATION = 'manage_basis_qualification';
    public const MANAGE_MAIL_TEMPLATES = 'manage_mail_templates';
    public const MANAGE_CANDIDATE_POOL = 'manage_candidate_pool';
    public const MANAGE_DELEGATIONS = 'manage_delegations';
    public const OVERRIDE_STATUS_TRANSITIONS = 'override_status_transitions';
    public const MANAGE_UNASSIGNED_INBOX = 'manage_unassigned_inbox';
    public const MANAGE_TEMPORARY_ADMIN_ACCESS = 'manage_temporary_admin_access';
    public const VIEW_FIRST_GUIDE_DOSSIER = 'view_first_guide_dossier';

    public const ACTION_CAPABILITIES = [
        self::VIEW_DOSSIER,
        self::MANAGE_CATALOG,
        self::EDIT_APPLICATIONS,
        self::INTERVIEW,
        self::EDIT_HIRING_DATA,
        self::EDIT_PAYROLL_DATA,
        self::VIEW_HIRING_DATA,
        self::MANAGE_DOCUMENTS,
        self::COMMUNICATE,
        self::MANAGE_FIRST_GUIDE_ACCESS,
        self::MANAGE_BASIS_QUALIFICATION,
        self::MANAGE_MAIL_TEMPLATES,
        self::MANAGE_CANDIDATE_POOL,
        self::MANAGE_DELEGATIONS,
        self::OVERRIDE_STATUS_TRANSITIONS,
        self::MANAGE_UNASSIGNED_INBOX,
    ];
    public const ROLE_ASSIGNABLE_CAPABILITIES = [
        ...self::ACTION_CAPABILITIES,
        self::VIEW_FIRST_GUIDE_DOSSIER,
    ];
    public const DELEGATABLE_CAPABILITIES = [
        self::VIEW_DOSSIER,
        self::MANAGE_CATALOG,
        self::EDIT_APPLICATIONS,
        self::INTERVIEW,
        self::EDIT_HIRING_DATA,
        self::EDIT_PAYROLL_DATA,
        self::VIEW_HIRING_DATA,
        self::MANAGE_DOCUMENTS,
        self::COMMUNICATE,
        self::MANAGE_FIRST_GUIDE_ACCESS,
        self::MANAGE_BASIS_QUALIFICATION,
        self::MANAGE_MAIL_TEMPLATES,
        self::MANAGE_CANDIDATE_POOL,
        self::OVERRIDE_STATUS_TRANSITIONS,
        self::MANAGE_UNASSIGNED_INBOX,
    ];
    private const RELEASED_STATUSES = ['approved_for_hire', 'hired'];

    /** @param array{roleCapabilities?: array<string,list<string>>, representatives?: list<array<string, mixed>>} $settings */
    public function __construct(
        private OrganizationSnapshot $organization,
        private array $settings,
    ) {}

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor
     *  @param array<string, mixed>|null $application
     */
    public function can(array $actor, string $capability, ?array $application = null): bool {
        if ($capability === self::VIEW_FIRST_GUIDE_DOSSIER) {
            return $this->isFirstGuideFor($actor, $application);
        }
        if ($capability === self::MANAGE_TEMPORARY_ADMIN_ACCESS) {
            return ($actor['isPrivacyOfficer'] ?? false) === true
                || ($this->organization->isValid() && $this->hasRole($actor, 'staff_hr'));
        }
        if ($capability === self::MANAGE_DELEGATIONS) {
            if (($actor['isPrivacyOfficer'] ?? false) === true || $actor['isAdmin']) {
                return true;
            }
            return $this->organization->isValid()
                && $this->hasRole($actor, 'staff_hr')
                && $this->hasConfiguredRole($actor, self::MANAGE_DELEGATIONS);
        }
        if ($actor['isAdmin']) {
            return in_array($capability, self::ACTION_CAPABILITIES, true);
        }
        if ($this->representativeCan($actor, $capability, $application)) {
            return true;
        }
        if (!$this->organization->isValid()) {
            return false;
        }

        if ($this->hasConfiguredRole($actor, $capability)) {
            if ($capability === self::EDIT_PAYROLL_DATA) {
                return $this->isPayrollEligible($application);
            }
            if ($capability === self::VIEW_HIRING_DATA
                && $this->hasRole($actor, 'payroll')
                && !$this->hasRole($actor, 'staff_hr')) {
                return $this->isPayrollEligible($application);
            }
            return true;
        }
        if ($capability === self::VIEW_DOSSIER && $this->isFirstGuideFor($actor, $application)) {
            return true;
        }

        return false;
    }

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor */
    public function hasAnyAccess(array $actor): bool {
        if ($actor['isAdmin']) return true;
        if (($actor['isPrivacyOfficer'] ?? false) === true) return true;
        if ($this->representativeHasAnyAccess($actor)) return true;
        if (!$this->organization->isValid()) return false;
        foreach (self::ACTION_CAPABILITIES as $capability) {
            if ($this->hasConfiguredRole($actor, $capability)) return true;
        }
        if ($this->hasConfiguredRole($actor, self::VIEW_FIRST_GUIDE_DOSSIER) && $this->hasRole($actor, 'eb')) {
            return true;
        }
        return false;
    }

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor */
    public function canSomewhere(array $actor, string $capability): bool {
        if ($capability === self::VIEW_FIRST_GUIDE_DOSSIER) {
            return $this->organization->isValid()
                && $this->hasRole($actor, 'eb')
                && $this->hasConfiguredRole($actor, self::VIEW_FIRST_GUIDE_DOSSIER);
        }
        if ($capability === self::MANAGE_TEMPORARY_ADMIN_ACCESS) return $this->can($actor, $capability);
        if ($capability === self::MANAGE_DELEGATIONS) return $this->can($actor, $capability);
        if ($actor['isAdmin']) return in_array($capability, self::ACTION_CAPABILITIES, true);
        if ($this->representativeCanSomewhere($actor, $capability)) return true;
        if (!$this->organization->isValid()) return false;
        if ($this->hasConfiguredRole($actor, $capability)) return true;
        if ($capability === self::VIEW_DOSSIER && $this->canSomewhere($actor, self::VIEW_FIRST_GUIDE_DOSSIER)) return true;
        return false;
    }

    /** Unzugeordnete Mails besitzen noch keinen Objekt-Scope und benötigen deshalb einen globalen Bearbeitungsscope. */
    public function canManageUnassignedInbox(array $actor): bool {
        return $this->can($actor, self::MANAGE_UNASSIGNED_INBOX);
    }

    /** @param array{uid: string, isAdmin: bool, isPrivacyOfficer?: bool, groupIds: list<string>} $actor */
    public function canManagePermissionMatrix(array $actor): bool {
        return ($actor['isPrivacyOfficer'] ?? false) === true;
    }

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor
     *  @param array<string, mixed>|null $application
     */
    private function representativeCan(array $actor, string $capability, ?array $application): bool {
        foreach ($this->settings['representatives'] ?? [] as $representative) {
            if (($representative['uid'] ?? null) !== $actor['uid']
                || !in_array($capability, $representative['capabilities'] ?? [], true)) {
                continue;
            }
            if (($representative['all'] ?? false) === true) return true;
            if ($application === null) continue;
            if (in_array((int)($application['id'] ?? 0), $representative['applicationIds'] ?? [], true)) return true;
            if ($this->organization->isValid()
                && in_array((string)($application['areaKey'] ?? ''), $representative['areaKeys'] ?? [], true)) {
                return true;
            }
        }
        return false;
    }

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor */
    private function representativeHasAnyAccess(array $actor): bool {
        foreach ($this->settings['representatives'] ?? [] as $representative) {
            if (($representative['uid'] ?? null) !== $actor['uid'] || ($representative['capabilities'] ?? []) === []) continue;
            if (($representative['all'] ?? false) === true || ($representative['applicationIds'] ?? []) !== []) return true;
            if ($this->organization->isValid() && ($representative['areaKeys'] ?? []) !== []) return true;
        }
        return false;
    }

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor */
    private function representativeCanSomewhere(array $actor, string $capability): bool {
        foreach ($this->settings['representatives'] ?? [] as $representative) {
            if (($representative['uid'] ?? null) !== $actor['uid']
                || !in_array($capability, $representative['capabilities'] ?? [], true)) {
                continue;
            }
            if (($representative['all'] ?? false) === true || ($representative['applicationIds'] ?? []) !== []) return true;
            if ($this->organization->isValid() && ($representative['areaKeys'] ?? []) !== []) return true;
        }
        return false;
    }

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor */
    private function hasRole(array $actor, string $roleKey): bool {
        $groupId = $this->organization->roleGroupId($roleKey);
        return $groupId !== null && in_array($groupId, $actor['groupIds'], true);
    }

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor */
    private function hasConfiguredRole(array $actor, string $capability): bool {
        if (!in_array($capability, self::ROLE_ASSIGNABLE_CAPABILITIES, true)) return false;
        foreach ($this->settings['roleCapabilities'][$capability] ?? [] as $roleKey) {
            if ($this->hasRole($actor, (string)$roleKey)) return true;
        }
        return false;
    }

    /** @param array{uid: string, isAdmin: bool, groupIds: list<string>} $actor
     *  @param array<string, mixed>|null $application
     */
    private function isFirstGuideFor(array $actor, ?array $application): bool {
        if (!$this->isReleased($application) || ($application['firstGuideAccess'] ?? false) !== true || !$this->hasRole($actor, 'eb')) {
            return false;
        }
        $areaGroup = $this->organization->areaGroupId((string)($application['areaKey'] ?? ''));
        return $areaGroup !== null
            && $this->hasConfiguredRole($actor, self::VIEW_FIRST_GUIDE_DOSSIER)
            && in_array($areaGroup, $actor['groupIds'], true);
    }

    /** @param array<string, mixed>|null $application */
    private function isReleased(?array $application): bool {
        return $application !== null && in_array((string)($application['status'] ?? ''), self::RELEASED_STATUSES, true);
    }

    /** @param array<string,mixed>|null $application */
    private function isPayrollEligible(?array $application): bool {
        return $this->isReleased($application);
    }

}
