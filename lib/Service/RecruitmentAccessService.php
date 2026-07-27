<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\Recruitment\Exception\AccessDeniedException;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

/**
 * Zentrale serverseitige Auswertung der Nextcloud-Gruppenrechte.
 */
final class RecruitmentAccessService {
    public const VIEW = 'view';
    public const MANAGE_CATALOG = 'manage_catalog';
    public const EDIT_APPLICATIONS = 'edit_applications';
    public const INTERVIEW = 'interview';
    public const MANAGE_DOCUMENTS = 'manage_documents';
    public const COMMUNICATE = 'communicate';

    /** @var array<string,list<string>> */
    private const CAPABILITY_GROUPS = [
        self::VIEW => [
            'adrecruitment-admin',
            'adrecruitment-managers',
            'adrecruitment-editors',
            'adrecruitment-interviewers',
            'adrecruitment-readers',
            'adrecruitment-documents',
            'adrecruitment-communication',
        ],
        self::MANAGE_CATALOG => ['adrecruitment-admin', 'adrecruitment-managers'],
        self::EDIT_APPLICATIONS => ['adrecruitment-admin', 'adrecruitment-editors'],
        self::INTERVIEW => ['adrecruitment-admin', 'adrecruitment-interviewers', 'adrecruitment-editors'],
        self::MANAGE_DOCUMENTS => ['adrecruitment-admin', 'adrecruitment-documents'],
        self::COMMUNICATE => ['adrecruitment-admin', 'adrecruitment-communication'],
    ];

    public function __construct(
        private IUserSession $session,
        private IGroupManager $groups,
    ) {
    }

    public function currentUser(): ?IUser {
        return $this->session->getUser();
    }

    public function currentUid(): string {
        return $this->currentUser()?->getUID() ?? '';
    }

    public function can(string $capability): bool {
        $user = $this->currentUser();
        if ($user === null) {
            return false;
        }

        if ($this->groups->isAdmin($user->getUID())) {
            return true;
        }

        foreach (self::CAPABILITY_GROUPS[$capability] ?? [] as $groupId) {
            if ($this->groups->get($groupId)?->inGroup($user) === true) {
                return true;
            }
        }

        return false;
    }

    public function require(string $capability): void {
        if (!$this->can($capability)) {
            throw new AccessDeniedException();
        }
    }

    /** @return array<string,bool> */
    public function capabilities(): array {
        $result = [];
        foreach (array_keys(self::CAPABILITY_GROUPS) as $capability) {
            $result[$capability] = $this->can($capability);
        }
        return $result;
    }
}
