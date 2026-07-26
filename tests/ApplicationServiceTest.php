<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/Exception/ConflictException.php';
require_once dirname(__DIR__) . '/lib/Exception/NotFoundException.php';
require_once dirname(__DIR__) . '/lib/Exception/ValidationException.php';
require_once dirname(__DIR__) . '/lib/Contract/RecruitmentStore.php';
require_once dirname(__DIR__) . '/lib/Contract/ApplicationStatusStore.php';
require_once dirname(__DIR__) . '/lib/Service/RecruitmentService.php';
require_once dirname(__DIR__) . '/lib/Service/ApplicationStatusService.php';

use OCA\Recruitment\Contract\ApplicationStatusStore;
use OCA\Recruitment\Contract\RecruitmentStore;
use OCA\Recruitment\Exception\ConflictException;
use OCA\Recruitment\Exception\NotFoundException;
use OCA\Recruitment\Exception\ValidationException;
use OCA\Recruitment\Service\ApplicationStatusService;
use OCA\Recruitment\Service\RecruitmentService;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;

final class MemoryRecruitmentStore implements RecruitmentStore, ApplicationStatusStore {
    /** @var array<int,array<string,mixed>> */
    public array $jobs = [];
    /** @var array<int,array<string,mixed>> */
    public array $people = [];
    /** @var array<int,array<string,mixed>> */
    public array $applications = [];
    /** @var list<array<string,mixed>> */
    public array $statusLog = [];

    public function createJob(array $job): int {
        $id = count($this->jobs) + 1;
        $this->jobs[$id] = ['id' => $id] + $job;
        return $id;
    }

    public function createPerson(array $person): int {
        $id = count($this->people) + 1;
        $this->people[$id] = ['id' => $id] + $person;
        return $id;
    }

    public function personExists(int $id): bool { return isset($this->people[$id]); }
    public function jobExists(int $id): bool { return isset($this->jobs[$id]); }

    public function createApplication(array $application): int {
        $id = count($this->applications) + 1;
        $this->applications[$id] = ['id' => $id, 'status' => 'received', 'version' => 1] + $application;
        return $id;
    }

    public function overview(): array {
        return [
            'jobs' => array_values($this->jobs),
            'people' => array_values($this->people),
            'applications' => array_values($this->applications),
            'templates' => [],
        ];
    }

    public function applicationDetail(int $id): array {
        if (!isset($this->applications[$id])) {
            throw new NotFoundException('Bewerbung nicht gefunden.');
        }
        return $this->applications[$id];
    }

    public function findApplication(int $id): array {
        return $this->applicationDetail($id);
    }

    public function transitionStatus(
        int $id,
        string $fromStatus,
        string $toStatus,
        int $expectedVersion,
        string $actorUid,
    ): array {
        $application = $this->applicationDetail($id);
        if ($application['status'] !== $fromStatus || $application['version'] !== $expectedVersion) {
            throw new ConflictException('Konkurrierende Änderung.');
        }
        $this->applications[$id]['status'] = $toStatus;
        $this->applications[$id]['version']++;
        $this->statusLog[] = compact('id', 'fromStatus', 'toStatus', 'actorUid');
        return $this->applications[$id];
    }
}

TestRunner::test('person and application stay separate while one person owns multiple applications', static function (): void {
    $store = new MemoryRecruitmentStore();
    $service = new RecruitmentService();
    $jobOne = $service->createJob($store, 'Entwicklung', '', true, [], [], 'development');
    $jobTwo = $service->createJob($store, 'Organisation', '', true, [], [], 'organization');
    $person = $service->createPerson($store, 'Alex', 'Beispiel', 'alex@example.invalid', '');

    $first = $service->createApplication($store, $person, $jobOne, 'manual', '2026-07-26', '');
    $second = $service->createApplication($store, $person, $jobTwo, 'manual', '2026-07-27', '');

    assertSame(1, count($store->people));
    assertSame(2, count($store->applications));
    assertSame($person, $store->applications[$first]['personId']);
    assertSame($person, $store->applications[$second]['personId']);
});

TestRunner::test('application creation rejects foreign identifiers without mutation', static function (): void {
    $store = new MemoryRecruitmentStore();
    $service = new RecruitmentService();
    $job = $service->createJob($store, 'Entwicklung', '', true, [], [], 'development');

    assertThrows(
        static fn () => $service->createApplication($store, 999, $job, 'manual', '2026-07-26', ''),
        NotFoundException::class,
    );
    assertSame([], $store->applications);
});

TestRunner::test('status transition persists application and audit log atomically', static function (): void {
    $store = new MemoryRecruitmentStore();
    $service = new RecruitmentService();
    $status = new ApplicationStatusService();
    $job = $service->createJob($store, 'Entwicklung', '', true, [], [], 'development');
    $person = $service->createPerson($store, 'Alex', 'Beispiel', '', '');
    $application = $service->createApplication($store, $person, $job, 'manual', '2026-07-26', '');

    $changed = $status->transition($store, $application, 'screening', 1, 'editor-user');

    assertSame('screening', $changed['status']);
    assertSame('received', $store->statusLog[0]['fromStatus']);
    assertSame('editor-user', $store->statusLog[0]['actorUid']);

    assertThrows(
        static fn () => $status->transition($store, $application, 'hired', 2, 'editor-user'),
        ValidationException::class,
    );
    assertSame(1, count($store->statusLog));
});
