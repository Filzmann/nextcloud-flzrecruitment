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
            'recruitment-admin',
            'recruitment-managers',
            'recruitment-editors',
            'recruitment-interviewers',
            'recruitment-readers',
            'recruitment-documents',
            'recruitment-communication',
        ],
        self::MANAGE_CATALOG => ['recruitment-admin', 'recruitment-managers'],
        self::EDIT_APPLICATIONS => ['recruitment-admin', 'recruitment-editors'],
        self::INTERVIEW => ['recruitment-admin', 'recruitment-interviewers', 'recruitment-editors'],
        self::MANAGE_DOCUMENTS => ['recruitment-admin', 'recruitment-documents'],
        self::COMMUNICATE => ['recruitment-admin', 'recruitment-communication'],
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
