<?php

declare(strict_types=1);

use OCA\Recruitment\Contract\CandidatePoolStore;
use OCA\Recruitment\Exception\ValidationException;
use OCA\Recruitment\Service\CandidatePoolService;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;

final class CandidatePoolMemoryStore implements CandidatePoolStore {
    public array $entries = [];
    public array $events = [];
    public array $matches = [];
    public array $context = [
        'application' => ['id' => 7, 'status' => 'rejected', 'personId' => 3, 'desiredWeeklyHours' => 20.0, 'desiredWeeklyHoursMax' => 30.0],
        'job' => ['professionCategory' => 'assistance'],
    ];
    public function candidatePoolApplicationContext(int $applicationId): array { return $this->context; }
    public function candidatePoolEntryForApplication(int $applicationId): ?array { foreach ($this->entries as $entry) if ($entry['sourceApplicationId'] === $applicationId) return $entry; return null; }
    public function createCandidatePoolEntry(array $entry): array { $entry['id'] = count($this->entries) + 1; $entry['version'] = 1; return $this->entries[$entry['id']] = $entry; }
    public function candidatePoolEntry(int $id): array { return $this->entries[$id]; }
    public function updateCandidatePoolEntry(int $id, string $fromStatus, array $changes): array { if ($this->entries[$id]['status'] !== $fromStatus) throw new RuntimeException('conflict'); return $this->entries[$id] = array_replace($this->entries[$id], $changes, ['version' => $this->entries[$id]['version'] + 1]); }
    public function appendCandidatePoolConsent(array $event): void { $this->events[] = $event; }
    public function candidatePoolEntries(): array { return array_values($this->entries); }
    public function activeCandidatePoolEntries(): array { return array_values(array_filter($this->entries, static fn(array $e): bool => $e['status'] === 'active')); }
    public function activeCandidatePoolJobs(): array { return []; }
    public function candidatePoolMatch(int $entryId, int $jobId): ?array { return null; }
    public function createCandidatePoolMatch(array $match): void { $this->matches[] = $match; }
}

TestRunner::test('candidate pool is opt-in and only requested for completed unsuccessful applications', static function (): void {
    $store = new CandidatePoolMemoryStore();
    $service = new CandidatePoolService();
    assertThrows(fn() => $service->request($store, 7, ['enabled' => false, 'noticeVersion' => ''], 'hr', new DateTimeImmutable('2026-08-15')), ValidationException::class);
    assertSame([], $store->entries);
    $entry = $service->request($store, 7, ['enabled' => true, 'noticeVersion' => '2026-08'], 'hr', new DateTimeImmutable('2026-08-15'));
    assertSame('requested', $entry['status']);
    assertSame(null, $entry['expiresAt']);
});

TestRunner::test('explicit evidence activates a minimized pool profile and withdrawal stops it immediately', static function (): void {
    $store = new CandidatePoolMemoryStore();
    $service = new CandidatePoolService();
    $settings = ['enabled' => true, 'noticeVersion' => '2026-08', 'consentMonths' => 12, 'reminderDays' => 30];
    $requested = $service->request($store, 7, $settings, 'hr', new DateTimeImmutable('2026-08-15'));
    $active = $service->grant($store, $requested['id'], 'email_reply', 'msg-2026-0815-7', '2026-08', ['west'], 'hr', new DateTimeImmutable('2026-08-16'), $settings);
    assertSame('active', $active['status']);
    assertSame('2027-08-16 00:00:00', $active['expiresAt']);
    assertSame(['west'], $active['areaKeys']);
    assertSame(false, array_key_exists('interviews', $active));
    assertSame('withdrawn', $service->withdraw($store, $active['id'], 'hr', new DateTimeImmutable('2026-09-01'))['status']);
    assertSame(3, count($store->events));
});

TestRunner::test('consent version and evidence are mandatory and expiry is idempotent', static function (): void {
    $store = new CandidatePoolMemoryStore();
    $service = new CandidatePoolService();
    $settings = ['enabled' => true, 'noticeVersion' => '2026-08', 'consentMonths' => 12, 'reminderDays' => 30];
    $entry = $service->request($store, 7, $settings, 'hr', new DateTimeImmutable('2026-08-15'));
    assertThrows(fn() => $service->grant($store, $entry['id'], 'email_reply', '', '2026-08', [], 'hr', new DateTimeImmutable('2026-08-16'), $settings), ValidationException::class);
    assertThrows(fn() => $service->grant($store, $entry['id'], 'email_reply', 'msg-1', 'old', [], 'hr', new DateTimeImmutable('2026-08-16'), $settings), ValidationException::class);
    $service->grant($store, $entry['id'], 'email_reply', 'msg-1', '2026-08', [], 'hr', new DateTimeImmutable('2026-08-16'), $settings);
    assertSame(1, $service->expireDue($store, new DateTimeImmutable('2027-08-17')));
    assertSame(0, $service->expireDue($store, new DateTimeImmutable('2027-08-17')));
});

TestRunner::test('expiry reminder is created once inside the configured window', static function (): void {
    $store = new CandidatePoolMemoryStore(); $service = new CandidatePoolService();
    $settings = ['enabled' => true, 'noticeVersion' => '2026-08', 'consentMonths' => 12, 'reminderDays' => 30];
    $entry = $service->request($store, 7, $settings, 'hr', new DateTimeImmutable('2026-08-15'));
    $service->grant($store, $entry['id'], 'email_reply', 'msg-1', '2026-08', [], 'hr', new DateTimeImmutable('2026-08-16'), $settings);
    assertSame(0, $service->markRemindersDue($store, new DateTimeImmutable('2027-07-15'), 30));
    assertSame(1, $service->markRemindersDue($store, new DateTimeImmutable('2027-07-17'), 30));
    assertSame(0, $service->markRemindersDue($store, new DateTimeImmutable('2027-07-18'), 30));
});
