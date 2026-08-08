<?php

declare(strict_types=1);

namespace {
    require_once __DIR__ . '/bootstrap.php';
    require_once dirname(__DIR__) . '/lib/Contract/BasisQualificationStore.php';
    require_once dirname(__DIR__) . '/lib/Exception/ConflictException.php';
    require_once dirname(__DIR__) . '/lib/Exception/ValidationException.php';
    require_once dirname(__DIR__) . '/lib/Service/BasisQualificationService.php';

    use OCA\Recruitment\Contract\BasisQualificationStore;
    use OCA\Recruitment\Exception\ConflictException;
    use OCA\Recruitment\Exception\ValidationException;
    use OCA\Recruitment\Service\BasisQualificationService;
    use RecruitmentTests\TestRunner;

    use function RecruitmentTests\assertSame;
    use function RecruitmentTests\assertThrows;

    final class MemoryBasisQualificationStore implements BasisQualificationStore {
        public array $applications = [
            1 => ['id' => 1, 'jobId' => 10, 'status' => 'decision_pending', 'version' => 4],
            2 => ['id' => 2, 'jobId' => 11, 'status' => 'decision_pending', 'version' => 2],
            3 => ['id' => 3, 'jobId' => 10, 'status' => 'screening', 'version' => 1],
        ];
        public array $jobs = [
            10 => ['id' => 10, 'basisQualificationRequired' => true, 'version' => 2],
            11 => ['id' => 11, 'basisQualificationRequired' => false, 'version' => 1],
        ];
        public array $runs = [];
        public array $assignments = [];

        public function basisQualificationContext(int $applicationId): array {
            return ['application' => $this->applications[$applicationId], 'job' => $this->jobs[$this->applications[$applicationId]['jobId']]];
        }
        public function basisQualificationJob(int $jobId): array { return $this->jobs[$jobId]; }
        public function setJobBasisQualificationRequired(int $jobId, bool $required, int $expectedVersion, string $actorUid): array {
            if ($this->jobs[$jobId]['version'] !== $expectedVersion) throw new ConflictException('stale');
            $this->jobs[$jobId]['basisQualificationRequired'] = $required;
            $this->jobs[$jobId]['version']++;
            return $this->jobs[$jobId];
        }
        public function basisQualificationRun(int $runId): array { return $this->runs[$runId]; }
        public function basisQualificationRuns(): array { return array_values($this->runs); }
        public function activeBasisQualificationAssignment(int $applicationId): ?array {
            $items = array_values(array_filter($this->assignments, static fn(array $item): bool => $item['applicationId'] === $applicationId));
            if ($items === []) return null;
            $latest = $items[array_key_last($items)];
            return in_array($latest['result'], ['pending', 'suitable'], true) ? $latest : null;
        }
        public function createBasisQualificationRun(array $run): int {
            $id = count($this->runs) + 1;
            $this->runs[$id] = ['id' => $id, 'version' => 1] + $run;
            return $id;
        }
        public function assignBasisQualification(int $applicationId, int $runId, int $expectedApplicationVersion, string $actorUid): array {
            $application = $this->applications[$applicationId];
            if ($application['version'] !== $expectedApplicationVersion || $application['status'] !== 'decision_pending') {
                throw new ConflictException('stale');
            }
            $this->applications[$applicationId]['status'] = 'basis_qualification';
            $this->applications[$applicationId]['version']++;
            $id = count($this->assignments) + 1;
            return $this->assignments[$id] = [
                'id' => $id,
                'applicationId' => $applicationId,
                'runId' => $runId,
                'label' => $this->runs[$runId]['label'],
                'result' => 'pending',
                'evaluationNote' => '',
                'version' => 1,
            ];
        }
        public function basisQualificationAssignment(int $assignmentId): array { return $this->assignments[$assignmentId]; }
        public function basisQualificationAssignments(int $applicationId): array {
            return array_values(array_filter(
                $this->assignments,
                static fn(array $item): bool => $item['applicationId'] === $applicationId,
            ));
        }
        public function recordBasisQualificationResult(int $assignmentId, string $result, string $note, int $expectedVersion, string $actorUid): array {
            if ($this->assignments[$assignmentId]['version'] !== $expectedVersion) throw new ConflictException('stale');
            $this->assignments[$assignmentId]['result'] = $result;
            $this->assignments[$assignmentId]['evaluationNote'] = $note;
            $this->assignments[$assignmentId]['version']++;
            return $this->assignments[$assignmentId];
        }
    }

    TestRunner::test('BQ run derives its visible label and validates its date range', static function (): void {
        $store = new MemoryBasisQualificationStore();
        $service = new BasisQualificationService();

        $id = $service->createRun($store, '2026-09-07', '2026-09-18', 'hr-user');
        assertSame('BQ 09/26', $store->runs[$id]['label']);
        assertThrows(
            static fn () => $service->createRun($store, '2026-09-18', '2026-09-07', 'hr-user'),
            ValidationException::class,
        );
    });

    TestRunner::test('only an eligible assistant application in decision state can enter BQ', static function (): void {
        $store = new MemoryBasisQualificationStore();
        $service = new BasisQualificationService();
        $runId = $service->createRun($store, '2026-09-07', '2026-09-18', 'hr-user');

        $assignment = $service->assign($store, 1, $runId, 4, 'hr-user');
        assertSame('pending', $assignment['result']);
        assertSame('basis_qualification', $store->applications[1]['status']);

        assertThrows(static fn () => $service->assign($store, 2, $runId, 2, 'hr-user'), ValidationException::class);
        assertThrows(static fn () => $service->assign($store, 3, $runId, 1, 'hr-user'), ValidationException::class);
        assertSame('decision_pending', $store->applications[2]['status']);
        assertSame('screening', $store->applications[3]['status']);
    });

    TestRunner::test('HR can mark an existing job as requiring basis qualification with optimistic locking', static function (): void {
        $store = new MemoryBasisQualificationStore();
        $service = new BasisQualificationService();

        $updated = $service->setJobRequirement($store, 11, true, 1, 'hr-user');
        assertSame(true, $updated['basisQualificationRequired']);
        assertSame(2, $updated['version']);
        assertThrows(
            static fn () => $service->setJobRequirement($store, 11, false, 1, 'hr-user'),
            ConflictException::class,
        );
    });

    TestRunner::test('simple BQ result is versioned without automatically changing application status', static function (): void {
        $store = new MemoryBasisQualificationStore();
        $service = new BasisQualificationService();
        $runId = $service->createRun($store, '2026-09-07', '2026-09-18', 'hr-user');
        $assignment = $service->assign($store, 1, $runId, 4, 'hr-user');

        $evaluated = $service->recordResult($store, $assignment['id'], 'not_suitable', 'Einfache fachliche Bewertung.', 1, 'hr-user');
        assertSame('not_suitable', $evaluated['result']);
        assertSame('basis_qualification', $store->applications[1]['status']);
        assertThrows(
            static fn () => $service->recordResult($store, $assignment['id'], 'unknown', '', 2, 'hr-user'),
            ValidationException::class,
        );
        assertThrows(
            static fn () => $service->recordResult($store, $assignment['id'], 'suitable', '', 1, 'hr-user'),
            ConflictException::class,
        );
    });
}
