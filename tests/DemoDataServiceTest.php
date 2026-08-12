<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service {
    final class RecruitmentUseCaseService {
        public array $jobs = [];
        public array $people = [];
        public array $applications = [];
        public array $runs = [];
        public array $assignments = [];
        public array $templates = [];
        public array $interviews = [];
        public array $bubbles = [];
        public array $hiring = [];
        public array $createdJobs = [];

        public function overview(): array {
            return [
                'jobs' => array_values($this->jobs),
                'people' => array_values($this->people),
                'applications' => array_values($this->applications),
                'templates' => array_values($this->templates),
            ];
        }

        public function createJob(string $internalTitle, string $publicTitle, bool $active, array $users, array $groups, string $assignmentKey, bool $bq, string $professionCategory = ''): int {
            $id = count($this->jobs) + 1;
            $job = ['id' => $id, 'internalTitle' => $internalTitle, 'publicTitle' => $publicTitle, 'active' => $active, 'assignmentKey' => $assignmentKey, 'basisQualificationRequired' => $bq, 'professionCategory' => $professionCategory, 'version' => 1];
            $this->jobs[$id] = $job;
            $this->createdJobs[] = $job;
            return $id;
        }

        public function createPerson(string $givenName, string $familyName, string $email, string $phone): int {
            $id = count($this->people) + 1;
            $this->people[$id] = compact('id', 'givenName', 'familyName', 'email', 'phone');
            return $id;
        }

        public function createApplication(int $personId, int $jobId, string $source, string $receivedOn, string $assigneeUid, ?float $desiredWeeklyHours = null, ?float $desiredWeeklyHoursMax = null): int {
            $id = count($this->applications) + 1;
            $this->applications[$id] = ['id' => $id, 'personId' => $personId, 'jobId' => $jobId, 'source' => $source, 'receivedOn' => $receivedOn, 'assigneeUid' => $assigneeUid, 'desiredWeeklyHours' => $desiredWeeklyHours, 'desiredWeeklyHoursMax' => $desiredWeeklyHoursMax, 'status' => 'received', 'version' => 1];
            return $id;
        }

        public function applicationSummary(int $id): array { return $this->applications[$id]; }

        public function transitionStatus(int $id, string $status, int $version, string $actorUid): array {
            $this->applications[$id]['status'] = $status;
            $this->applications[$id]['version']++;
            return $this->applications[$id];
        }

        public function basisQualificationRuns(): array { return array_values($this->runs); }

        public function createBasisQualificationRun(string $startsOn, string $endsOn, string $actorUid): int {
            $id = count($this->runs) + 1;
            $this->runs[$id] = ['id' => $id, 'label' => 'BQ 09/26', 'startsOn' => $startsOn, 'endsOn' => $endsOn];
            return $id;
        }

        public function basisQualificationAssignments(int $applicationId): array {
            return array_values(array_filter($this->assignments, static fn(array $item): bool => $item['applicationId'] === $applicationId));
        }

        public function assignBasisQualification(int $applicationId, int $runId, int $version, string $actorUid): array {
            $id = count($this->assignments) + 1;
            $this->applications[$applicationId]['status'] = 'basis_qualification';
            $this->applications[$applicationId]['version']++;
            return $this->assignments[$id] = ['id' => $id, 'applicationId' => $applicationId, 'runId' => $runId, 'result' => 'pending'];
        }

        public function hiringData(int $applicationId): array {
            return $this->hiring[$applicationId] ?? ['data' => ['city' => ''], 'version' => 0];
        }

        public function saveHiringData(int $applicationId, array $data, int $version, string $actorUid): array {
            return $this->hiring[$applicationId] = ['data' => $data, 'version' => $version + 1];
        }

        public function createTemplate(string $name, string $type, string $description, string $audience): int {
            $id = count($this->templates) + 1;
            $this->templates[$id] = ['id' => $id, 'name' => $name, 'type' => $type, 'description' => $description, 'audience' => $audience, 'questions' => []];
            return $id;
        }

        public function templateDetail(int $id): array { return $this->templates[$id]; }
        public function createQuestion(int $templateId, string $prompt, string $hint, string $type, bool $required, int $sortOrder, array $options, string $visibility): int {
            $id = count($this->templates[$templateId]['questions']) + 1;
            $this->templates[$templateId]['questions'][] = ['id' => $id, 'prompt' => $prompt, 'type' => $type, 'bubbles' => []];
            return $id;
        }
        public function createBubble(int $questionId, string $label, string $insertText, int $sortOrder, bool $active): int {
            $id = count($this->bubbles) + 1;
            $this->bubbles[$id] = compact('id', 'questionId', 'label', 'insertText', 'sortOrder', 'active');
            foreach ($this->templates as &$template) {
                foreach ($template['questions'] as &$question) {
                    if ($question['id'] === $questionId) $question['bubbles'][] = $this->bubbles[$id];
                }
            }
            return $id;
        }
        public function applicationDetail(int $id): array {
            return ['application' => $this->applications[$id], 'interviews' => array_values(array_filter(
                $this->interviews,
                static fn(array $item): bool => $item['applicationId'] === $id,
            ))];
        }
        public function createInterview(int $applicationId, int $templateId, string $actorUid): int {
            $id = count($this->interviews) + 1;
            $this->interviews[$id] = compact('id', 'applicationId', 'templateId', 'actorUid');
            return $id;
        }
    }
}

namespace {
    require_once __DIR__ . '/bootstrap.php';

    use OCA\Recruitment\Service\RecruitmentDemoDataService;
    use OCA\Recruitment\Service\RecruitmentUseCaseService;
    use RecruitmentTests\TestRunner;

    use function RecruitmentTests\assertSame;
    use function RecruitmentTests\assertTrue;

    TestRunner::test('demo seed starts with an assistant job and remains idempotent', static function (): void {
        $useCases = new RecruitmentUseCaseService();
        $service = new RecruitmentDemoDataService($useCases);

        $first = $service->install();
        assertSame('demo-assistenz', $useCases->createdJobs[0]['assignmentKey']);
        assertSame(true, $useCases->createdJobs[0]['basisQualificationRequired']);
        assertSame('assistance', $useCases->createdJobs[0]['professionCategory']);
        assertSame(2, count($useCases->jobs));
        assertSame(4, count($useCases->people));
        assertSame(4, count($useCases->applications));
        assertTrue(count(array_filter($useCases->applications, static fn(array $item): bool => $item['source'] === 'email_import')) >= 3);
        assertSame(20.0, $useCases->applications[1]['desiredWeeklyHours']);
        assertSame(25.0, $useCases->applications[1]['desiredWeeklyHoursMax']);
        assertSame(1, count($useCases->runs));
        assertSame(1, count($useCases->assignments));
        assertSame(1, count($useCases->templates));
        assertSame(1, count($useCases->bubbles));
        assertSame(1, count($useCases->interviews));
        assertSame(1, count($useCases->hiring));
        assertSame(4, $first['applications']);

        $useCases->jobs[99] = ['id' => 99, 'internalTitle' => 'Fremde Stelle', 'assignmentKey' => 'foreign-job', 'basisQualificationRequired' => false];
        $useCases->templates[99] = ['id' => 99, 'name' => 'Fremde Vorlage', 'questions' => []];

        $second = $service->install();
        assertSame(3, count($useCases->jobs));
        assertSame(4, count($useCases->people));
        assertSame(4, count($useCases->applications));
        assertSame(1, count($useCases->runs));
        assertSame(1, count($useCases->assignments));
        assertSame(2, count($useCases->templates));
        assertSame(1, count($useCases->bubbles));
        assertSame(1, count($useCases->interviews));
        assertSame(1, count($useCases->hiring));
        assertSame(4, $second['applications']);
        assertSame(2, $second['jobs']);
        assertSame(1, $second['templates']);
    });
}
