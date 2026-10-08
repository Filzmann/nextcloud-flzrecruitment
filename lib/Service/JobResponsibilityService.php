<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;

use OCA\FlzRecruitment\Exception\ValidationException;
use OCA\FlzRecruitment\Organization\OrganizationSnapshotService;
use OCP\IGroupManager;
use OCP\IUser;

/** Begrenzt Stellenzuständigkeiten auf fachlich passende FLZ-Organisationsgruppen. */
final class JobResponsibilityService {
    /** @var array<string,list<string>> */
    private const ROLE_KEYS_BY_PROFESSION = [
        'assistance' => ['staff_hr', 'gf_as', 'pdl', 'deputy_pdl', 'bl', 'deputy_bl', 'office', 'eb'],
        'nursing' => ['staff_hr', 'pdl', 'deputy_pdl', 'care_office', 'pfk'],
        'social_work' => ['staff_hr', 'gf_as', 'bl', 'deputy_bl', 'office'],
        'administration' => ['staff_hr', 'gf_as', 'gf_digi', 'pdl', 'finance_lead', 'bl', 'secretariat'],
        'other' => ['staff_hr', 'gf_as', 'pdl', 'bl'],
    ];

    public function __construct(
        private OrganizationSnapshotService $organization,
        private IGroupManager $groups,
    ) {}

    /** @return list<array{id:string,label:string}> */
    public function groups(string $professionCategory): array {
        $roleKeys = self::ROLE_KEYS_BY_PROFESSION[$professionCategory] ?? null;
        if ($roleKeys === null) throw new ValidationException('Die Berufsgruppe der Stelle ist ungültig.');

        $snapshot = $this->organization->snapshot();
        $roles = $snapshot->roles();
        $result = [];
        foreach ($roleKeys as $roleKey) {
            $role = $roles[$roleKey] ?? null;
            if (!is_array($role) || trim((string)($role['groupId'] ?? '')) === '') continue;
            $result[] = ['id' => (string)$role['groupId'], 'label' => (string)$role['label']];
        }
        return $result;
    }

    /** @return list<array{id:string,label:string,professionCategories:list<string>}> */
    public function allGroups(): array {
        $result = [];
        foreach (array_keys(self::ROLE_KEYS_BY_PROFESSION) as $professionCategory) {
            foreach ($this->groups($professionCategory) as $group) {
                $id = $group['id'];
                $result[$id] ??= $group + ['professionCategories' => []];
                $result[$id]['professionCategories'][] = $professionCategory;
            }
        }
        return array_values($result);
    }

    /**
     * @param list<string> $groupIds
     * @return list<array{uid:string,displayName:string}>
     */
    public function searchUsers(string $professionCategory, array $groupIds, string $query): array {
        $query = trim($query);
        $queryLength = function_exists('mb_strlen') ? \mb_strlen($query) : strlen($query);
        if ($queryLength < 2) {
            throw new ValidationException('Bitte geben Sie mindestens zwei Zeichen für die Personensuche ein.');
        }
        $groupIds = $this->validateGroups($professionCategory, $groupIds);
        if ($groupIds === []) {
            throw new ValidationException('Wählen Sie zuerst mindestens eine beteiligte Gruppe.');
        }

        $users = [];
        foreach ($groupIds as $groupId) {
            $group = $this->groups->get($groupId);
            if ($group === null) continue;
            foreach ($group->searchUsers($query, 20, 0) as $user) {
                if (!$user instanceof IUser) continue;
                $uid = $user->getUID();
                $users[$uid] = [
                    'uid' => $uid,
                    'displayName' => trim($user->getDisplayName()) ?: $uid,
                ];
            }
        }
        uasort($users, static fn(array $left, array $right): int =>
            strnatcasecmp($left['displayName'], $right['displayName'])
            ?: strcmp($left['uid'], $right['uid'])
        );
        return array_slice(array_values($users), 0, 20);
    }

    /** @param list<string> $groupIds @param list<string> $userIds */
    public function validate(string $professionCategory, array $groupIds, array $userIds): void {
        $groupIds = $this->validateGroups($professionCategory, $groupIds);
        $userIds = $this->clean($userIds);
        if ($userIds !== [] && $groupIds === []) {
            throw new ValidationException('Verantwortliche Personen benötigen mindestens eine beteiligte Gruppe.');
        }

        foreach ($userIds as $uid) {
            $found = false;
            foreach ($groupIds as $groupId) {
                $group = $this->groups->get($groupId);
                if ($group === null) continue;
                foreach ($group->searchUsers($uid, 20, 0) as $candidate) {
                    if ($candidate instanceof IUser && $candidate->getUID() === $uid) {
                        $found = true;
                        break 2;
                    }
                }
            }
            if (!$found) {
                throw new ValidationException('Eine verantwortliche Person gehört keiner ausgewählten beteiligten Gruppe an.');
            }
        }
    }

    /** @param list<string> $groupIds @return list<string> */
    private function validateGroups(string $professionCategory, array $groupIds): array {
        $groupIds = $this->clean($groupIds);
        $allowed = array_fill_keys(array_column($this->groups($professionCategory), 'id'), true);
        foreach ($groupIds as $groupId) {
            if (!isset($allowed[$groupId]) || $this->groups->get($groupId) === null) {
                throw new ValidationException('Eine ausgewählte Gruppe ist für diese Berufsgruppe nicht zulässig.');
            }
        }
        return $groupIds;
    }

    /** @param list<string> $values @return list<string> */
    private function clean(array $values): array {
        return array_values(array_unique(array_filter(
            array_map(static fn(mixed $value): string => trim((string)$value), $values),
            static fn(string $value): bool => $value !== '',
        )));
    }
}
