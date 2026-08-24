<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\Recruitment\AppInfo\Application;
use OCA\Recruitment\Exception\ConflictException;
use OCA\Recruitment\Exception\ValidationException;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserManager;

/** Persistiert delegierte Fähigkeiten versioniert in der app-eigenen Nextcloud-Konfiguration. */
final class RecruitmentPermissionSettingsService {
    private const KEY = 'permission_settings';

    public function __construct(
        private IAppConfig $config,
        private IUserManager $users,
        private IGroupManager $groups,
    ) {}

    /** @return array{version: int, revision: int, firstGuideGroupId: string, representatives: list<array<string, mixed>>, updatedBy: string, updatedAt: string} */
    public function settings(): array {
        $raw = $this->config->getValueString(Application::APP_ID, self::KEY, '');
        if ($raw === '') {
            return $this->defaults();
        }
        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || ($decoded['version'] ?? null) !== 1 || !is_array($decoded['representatives'] ?? null)) {
                throw new ValidationException('Die Berechtigungskonfiguration ist ungültig.');
            }
            return [
                'version' => 1,
                'revision' => max(0, (int)($decoded['revision'] ?? 0)),
                'firstGuideGroupId' => $this->identifier($decoded['firstGuideGroupId'] ?? ''),
                'representatives' => $this->representatives($decoded['representatives'], null),
                'updatedBy' => trim((string)($decoded['updatedBy'] ?? '')),
                'updatedAt' => trim((string)($decoded['updatedAt'] ?? '')),
            ];
        } catch (\Throwable) {
            return $this->defaults();
        }
    }

    /** @param list<array<string, mixed>> $representatives
     *  @param list<string> $validAreaKeys
     *  @return array<string, mixed>
     */
    public function saveRepresentatives(array $representatives, int $expectedRevision, string $actorUid, array $validAreaKeys): array {
        $current = $this->settings();
        $this->assertRevision($current, $expectedRevision);
        $current['representatives'] = $this->representatives($representatives, $validAreaKeys);
        return $this->persist($current, $actorUid);
    }

    /** @return array<string, mixed> */
    public function saveFirstGuideGroup(string $groupId, int $expectedRevision, string $actorUid): array {
        $current = $this->settings();
        $this->assertRevision($current, $expectedRevision);
        $groupId = $this->identifier($groupId);
        if ($this->groups->get($groupId) === null) {
            throw new ValidationException('Die ausgewählte Erstbegleitungsgruppe existiert nicht in Nextcloud.');
        }
        $current['firstGuideGroupId'] = $groupId;
        return $this->persist($current, $actorUid);
    }

    /** @param array<string, mixed> $current */
    private function assertRevision(array $current, int $expectedRevision): void {
        if ($expectedRevision !== $current['revision']) {
            throw new ConflictException('Die Berechtigungskonfiguration wurde zwischenzeitlich geändert.');
        }
    }

    /** @param array<string, mixed> $settings
     *  @return array<string, mixed>
     */
    private function persist(array $settings, string $actorUid): array {
        $settings['version'] = 1;
        $settings['revision'] = (int)$settings['revision'] + 1;
        $settings['updatedBy'] = $this->identifier($actorUid);
        $settings['updatedAt'] = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
        $this->config->setValueString(
            Application::APP_ID,
            self::KEY,
            json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
        return $settings;
    }

    /** @param list<array<string, mixed>> $items
     *  @param list<string>|null $validAreaKeys
     *  @return list<array<string, mixed>>
     */
    private function representatives(array $items, ?array $validAreaKeys): array {
        $result = [];
        $seen = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new ValidationException('Eine Vertretungsfreigabe ist ungültig.');
            }
            $uid = $this->identifier($item['uid'] ?? '');
            if ($validAreaKeys !== null && !$this->users->userExists($uid)) {
                throw new ValidationException('Die ausgewählte Vertretungskraft existiert nicht in Nextcloud.');
            }
            if (isset($seen[$uid])) {
                throw new ValidationException('Eine Vertretungskraft ist doppelt konfiguriert.');
            }
            $seen[$uid] = true;
            $requestedCapabilities = array_values(array_unique(array_map('strval', is_array($item['capabilities'] ?? null) ? $item['capabilities'] : [])));
            if (array_diff($requestedCapabilities, RecruitmentPermissionPolicy::DELEGATABLE_CAPABILITIES) !== []) {
                throw new ValidationException('Eine nicht delegierbare Fähigkeit wurde angefordert.');
            }
            $capabilities = array_values(array_filter(
                RecruitmentPermissionPolicy::DELEGATABLE_CAPABILITIES,
                static fn(string $capability): bool => in_array($capability, $requestedCapabilities, true),
            ));
            if ($capabilities === []) {
                throw new ValidationException('Eine Vertretungskraft benötigt mindestens eine Fähigkeit.');
            }
            $all = ($item['all'] ?? false) === true;
            if (in_array(RecruitmentPermissionPolicy::MANAGE_CATALOG, $capabilities, true) && !$all) {
                throw new ValidationException('Die Katalogverwaltung benötigt einen globalen Scope.');
            }
            $areaKeys = array_values(array_unique(array_map('strval', is_array($item['areaKeys'] ?? null) ? $item['areaKeys'] : [])));
            if ($validAreaKeys !== null && array_diff($areaKeys, $validAreaKeys) !== []) {
                throw new ValidationException('Eine Vertretungsfreigabe enthält einen unbekannten Bereich.');
            }
            $applicationIds = array_values(array_unique(array_map('intval', is_array($item['applicationIds'] ?? null) ? $item['applicationIds'] : [])));
            if (array_filter($applicationIds, static fn(int $id): bool => $id < 1) !== []) {
                throw new ValidationException('Eine Vertretungsfreigabe enthält eine ungültige Bewerbungs-ID.');
            }
            sort($applicationIds);
            if (!$all && $areaKeys === [] && $applicationIds === []) {
                throw new ValidationException('Eine Vertretungskraft benötigt einen globalen, Bereichs- oder Einzelakten-Scope.');
            }
            $result[] = compact('uid', 'capabilities', 'all', 'areaKeys', 'applicationIds');
        }
        return $result;
    }

    private function identifier(mixed $value): string {
        $value = trim((string)$value);
        if ($value === '' || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new ValidationException('Benutzer- oder Gruppenkennung ist ungültig.');
        }
        return $value;
    }

    /** @return array{version: int, revision: int, firstGuideGroupId: string, representatives: list<array<string, mixed>>, updatedBy: string, updatedAt: string} */
    private function defaults(): array {
        return [
            'version' => 1,
            'revision' => 0,
            'firstGuideGroupId' => 'adrecruitment-first-guides',
            'representatives' => [],
            'updatedBy' => '',
            'updatedAt' => '',
        ];
    }
}
