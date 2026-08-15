<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use DateTimeImmutable;
use OCA\Recruitment\Contract\CandidatePoolStore;
use OCA\Recruitment\Exception\ConflictException;
use OCA\Recruitment\Exception\ValidationException;

final class CandidatePoolService {
    private const SOURCE_STATUSES = ['rejected', 'withdrawn', 'archived'];

    /** @param array<string,mixed> $settings @return array<string,mixed> */
    public function request(CandidatePoolStore $store, int $applicationId, array $settings, string $actorUid, DateTimeImmutable $now): array {
        $this->requireEnabled($settings);
        if ($store->candidatePoolEntryForApplication($applicationId) !== null) throw new ConflictException('Für diese Bewerbung besteht bereits ein Rückstellungsvorgang.');
        $context = $store->candidatePoolApplicationContext($applicationId);
        if (!in_array((string)($context['application']['status'] ?? ''), self::SOURCE_STATUSES, true)) throw new ValidationException('Eine Rückstellung ist erst nach Absage, Rückzug oder Archivierung zulässig.');
        $entry = $store->createCandidatePoolEntry([
            'personId' => (int)$context['application']['personId'],
            'sourceApplicationId' => $applicationId,
            'status' => 'requested',
            'professionCategory' => (string)$context['job']['professionCategory'],
            'desiredWeeklyHours' => $context['application']['desiredWeeklyHours'] ?? null,
            'desiredWeeklyHoursMax' => $context['application']['desiredWeeklyHoursMax'] ?? null,
            'areaKeys' => [],
            'consentNoticeVersion' => (string)$settings['noticeVersion'],
            'requestedAt' => $now->format('Y-m-d H:i:s'),
            'consentedAt' => null,
            'expiresAt' => null,
            'reminderSentAt' => null,
            'withdrawnAt' => null,
            'actorUid' => trim($actorUid),
        ]);
        $this->event($store, $entry['id'], 'requested', (string)$settings['noticeVersion'], 'internal', '', $actorUid, $now, null);
        return $entry;
    }

    /** @param list<string> $areaKeys @param array<string,mixed> $settings @return array<string,mixed> */
    public function grant(CandidatePoolStore $store, int $entryId, string $evidenceType, string $evidenceReference, string $noticeVersion, array $areaKeys, string $actorUid, DateTimeImmutable $now, array $settings): array {
        $this->requireEnabled($settings);
        $entry = $store->candidatePoolEntry($entryId);
        if ((string)$entry['status'] !== 'requested') throw new ValidationException('Die Einwilligung kann nur für eine angefragte Rückstellung erfasst werden.');
        if (!in_array($evidenceType, ['email_reply', 'signed_form', 'self_service'], true) || trim($evidenceReference) === '') throw new ValidationException('Art und Referenz des Einwilligungsnachweises sind erforderlich.');
        if ($noticeVersion !== (string)$settings['noticeVersion'] || $noticeVersion !== (string)$entry['consentNoticeVersion']) throw new ValidationException('Die Einwilligung bezieht sich nicht auf den aktuellen Datenschutzhinweis.');
        $areaKeys = array_values(array_unique(array_filter(array_map('trim', $areaKeys), static fn(string $v): bool => $v !== '')));
        $expiresAt = $now->modify('+' . (int)$settings['consentMonths'] . ' months');
        $updated = $store->updateCandidatePoolEntry($entryId, 'requested', [
            'status' => 'active', 'areaKeys' => $areaKeys, 'consentedAt' => $now->format('Y-m-d H:i:s'),
            'expiresAt' => $expiresAt->format('Y-m-d H:i:s'), 'actorUid' => trim($actorUid),
        ]);
        $this->event($store, $entryId, 'granted', $noticeVersion, $evidenceType, trim($evidenceReference), $actorUid, $now, $expiresAt);
        return $updated;
    }

    /** @return array<string,mixed> */
    public function withdraw(CandidatePoolStore $store, int $entryId, string $actorUid, DateTimeImmutable $now): array {
        $entry = $store->candidatePoolEntry($entryId);
        if ((string)$entry['status'] !== 'active') throw new ValidationException('Nur eine aktive Einwilligung kann widerrufen werden.');
        $updated = $store->updateCandidatePoolEntry($entryId, 'active', ['status' => 'withdrawn', 'withdrawnAt' => $now->format('Y-m-d H:i:s'), 'actorUid' => trim($actorUid)]);
        $this->event($store, $entryId, 'withdrawn', (string)$entry['consentNoticeVersion'], 'internal', '', $actorUid, $now, null);
        return $updated;
    }

    public function expireDue(CandidatePoolStore $store, DateTimeImmutable $now): int {
        $count = 0;
        foreach ($store->activeCandidatePoolEntries() as $entry) {
            if (($entry['expiresAt'] ?? null) === null || new DateTimeImmutable((string)$entry['expiresAt']) > $now) continue;
            $store->updateCandidatePoolEntry((int)$entry['id'], 'active', ['status' => 'expired', 'actorUid' => 'system']);
            $this->event($store, (int)$entry['id'], 'expired', (string)$entry['consentNoticeVersion'], 'system', '', 'system', $now, null);
            $count++;
        }
        return $count;
    }

    /** Markiert fällige Hinweise einmalig; die Oberfläche zeigt diese als Aufgabe an. */
    public function markRemindersDue(CandidatePoolStore $store, DateTimeImmutable $now, int $reminderDays): int {
        $count = 0; $threshold = $now->modify('+' . $reminderDays . ' days');
        foreach ($store->activeCandidatePoolEntries() as $entry) {
            if (($entry['reminderSentAt'] ?? null) !== null || ($entry['expiresAt'] ?? null) === null || new DateTimeImmutable((string)$entry['expiresAt']) > $threshold) continue;
            $store->updateCandidatePoolEntry((int)$entry['id'], 'active', ['reminderSentAt' => $now->format('Y-m-d H:i:s'), 'actorUid' => 'system']);
            $this->event($store, (int)$entry['id'], 'reminded', (string)$entry['consentNoticeVersion'], 'internal_task', '', 'system', $now, null);
            $count++;
        }
        return $count;
    }

    private function requireEnabled(array $settings): void {
        if (($settings['enabled'] ?? false) !== true || trim((string)($settings['noticeVersion'] ?? '')) === '') throw new ValidationException('Der Bewerberpool ist bis zur Freigabe des Datenschutzhinweises deaktiviert.');
    }

    private function event(CandidatePoolStore $store, int $entryId, string $action, string $noticeVersion, string $evidenceType, string $evidenceReference, string $actorUid, DateTimeImmutable $at, ?DateTimeImmutable $validUntil): void {
        $store->appendCandidatePoolConsent(['entryId' => $entryId, 'action' => $action, 'noticeVersion' => $noticeVersion, 'evidenceType' => $evidenceType, 'evidenceReference' => $evidenceReference, 'actorUid' => trim($actorUid), 'occurredAt' => $at->format('Y-m-d H:i:s'), 'validUntil' => $validUntil?->format('Y-m-d H:i:s')]);
    }
}
