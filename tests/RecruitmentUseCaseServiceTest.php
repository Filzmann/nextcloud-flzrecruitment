<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Repository {
    final class RecruitmentRepository {
        public array $calls = [];
        public array $application = ['id' => 2, 'status' => 'received'];

        public function templateSnapshot(int $id): array {
            $this->calls[] = ['templateSnapshot', [$id]];
            return ['id' => $id, 'revision' => 3];
        }
        public function findApplication(int $id): array { return ['id' => $id] + $this->application; }
        public function applicationForInterview(int $id): array { return ['id' => 2, 'status' => 'received']; }
        public function basisQualificationAssignments(int $id): array {
            $this->calls[] = ['basisQualificationAssignments', [$id]];
            return [['id' => 7, 'applicationId' => $id, 'result' => 'pending']];
        }
        public function recordPermissionAudit(string $actorUid, string $action, string $subjectUid, ?int $applicationId, array $details): void {}
    }
}

namespace OCA\FlzRecruitment\Service {
    trait RecordsCalls {
        public array $calls = [];
        public array $returns = [];

        public function __call(string $method, array $arguments): mixed {
            $this->calls[] = [$method, $arguments];
            return $this->returns[$method] ?? null;
        }
    }

    final class RecruitmentService { use RecordsCalls; }
    final class TemplateService { use RecordsCalls; }
    final class InterviewService { use RecordsCalls; }
    final class ApplicationStatusService { use RecordsCalls; }
    final class HiringWorkflowService { use RecordsCalls; }
    final class BasisQualificationService { use RecordsCalls; }
    final class RecruitmentPermissionSettingsService { use RecordsCalls; }
}

namespace {
    use OCA\FlzRecruitment\Repository\RecruitmentRepository;
    use OCA\FlzRecruitment\Service\ApplicationStatusService;
    use OCA\FlzRecruitment\Service\InterviewService;
    use OCA\FlzRecruitment\Service\HiringWorkflowService;
    use OCA\FlzRecruitment\Service\BasisQualificationService;
    use OCA\FlzRecruitment\Service\RecruitmentService;
    use OCA\FlzRecruitment\Service\RecruitmentUseCaseService;
    use OCA\FlzRecruitment\Service\TemplateService;
    use OCA\FlzRecruitment\Service\RecruitmentPermissionSettingsService;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $repository = new RecruitmentRepository();
    $recruitment = new RecruitmentService();
    $templates = new TemplateService();
    $interviews = new InterviewService();
    $statuses = new ApplicationStatusService();
    $hiring = new HiringWorkflowService();
    $basisQualifications = new BasisQualificationService();
    $permissionSettings = new RecruitmentPermissionSettingsService();
    $service = new RecruitmentUseCaseService(
        $repository,
        $recruitment,
        $templates,
        $interviews,
        $statuses,
        $hiring,
        $basisQualifications,
        $permissionSettings,
    );

    $recruitment->returns = [
        'overview' => ['applications' => [['id' => 1, 'status' => 'received']]],
        'applicationDetail' => ['application' => ['id' => 2, 'status' => 'received']],
        'createJob' => 3,
        'createPerson' => 4,
        'createApplication' => 5,
    ];
    $templates->returns = ['create' => 6, 'addQuestion' => 7, 'addBubble' => 8];
    $interviews->returns = [
        'instantiate' => 9,
        'saveDraft' => ['id' => 9, 'version' => 2],
        'complete' => ['id' => 9, 'status' => 'completed'],
    ];
    $statuses->returns = [
        'allowedTargets' => ['screening'],
        'orderedStatuses' => ['received', 'screening', 'phone_planned'],
        'transition' => ['id' => 2, 'status' => 'screening'],
    ];
    $basisQualifications->returns = [
        'runs' => [['id' => 3, 'label' => 'BQ 09/26']],
        'createRun' => 3,
        'assign' => ['id' => 7, 'result' => 'pending'],
        'recordResult' => ['id' => 7, 'result' => 'suitable'],
    ];

    $overview = $service->overview();
    $assert($overview['applications'][0]['id'] === 1, 'Overview is not delegated.');
    $assert($overview['applications'][0]['allowedStatuses'] === ['screening'], 'Workbench application targets are missing.');
    $assert($overview['applicationStatuses'] === ['received', 'screening', 'phone_planned'], 'Ordered workbench columns are missing.');
    $detail = $service->applicationDetail(2);
    $assert($detail['allowedStatuses'] === ['screening'], 'Allowed status transitions are not composed.');
    $recruitment->returns['applicationDetail'] = [
        'application' => ['id' => 2, 'status' => 'basis_qualification', 'basisQualification' => ['id' => 7, 'label' => 'BQ 09/26']],
    ];
    $repository->application = [
        'id' => 2,
        'status' => 'basis_qualification',
        'basisQualification' => ['id' => 7, 'label' => 'BQ 09/26', 'result' => 'pending'],
    ];
    $statuses->returns['allowedTargets'] = ['approved_for_hire', 'rejected'];
    $detail = $service->applicationDetail(2);
    $assert($detail['allowedStatuses'] === ['rejected'], 'Pending BQ still offers hire approval in the composed detail.');
    $assert($service->templateDetail(11) === ['id' => 11, 'revision' => 3], 'Template detail is not delegated.');
    $assert($service->createJob('Intern', 'Public', true, ['user-a'], ['group-a'], 'assignment', true, 'assistance') === 3, 'Job creation is not delegated.');
    $assert($service->createPerson('Alex', 'Beispiel', 'alex@example.invalid', '123') === 4, 'Person creation is not delegated.');
    $assert($service->createApplication(4, 3, 'portal', '2026-07-27', 'editor-user', 20.5, 30.0) === 5, 'Application creation is not delegated.');
    $assert($service->createTemplate('Interview', 'interview', 'Description', 'internal') === 6, 'Template creation is not delegated.');
    $assert($service->createQuestion(6, 'Prompt', 'Hint', 'text', true, 10, ['A'], 'internal') === 7, 'Question creation is not delegated.');
    $service->updateQuestion(7, 'Prompt 2', 'Hint 2', 'choice', false, 20, ['B'], 'all', true);
    $assert($service->createBubble(7, 'Positive', 'Text', 10, true) === 8, 'Bubble creation is not delegated.');
    $assert($service->createInterview(5, 6, 'editor-user') === 9, 'Interview creation is not delegated.');
    $assert($service->saveInterviewDraft(9, ['7' => 'Draft'], 1)['version'] === 2, 'Interview draft is not delegated.');
    $assert($service->completeInterview(9, ['7' => 'Done'], 2)['status'] === 'completed', 'Interview completion is not delegated.');
    $assert($service->transitionStatus(2, 'screening', 1, 'editor-user')['status'] === 'screening', 'Status transition is not delegated.');
    $assert($service->basisQualificationRuns()[0]['label'] === 'BQ 09/26', 'BQ runs are not delegated.');
    $assert($service->basisQualificationAssignments(2)[0]['applicationId'] === 2, 'BQ assignment history is not loaded.');
    $assert($service->createBasisQualificationRun('2026-09-07', '2026-09-18', 'hr-user') === 3, 'BQ run creation is not delegated.');
    $assert($service->assignBasisQualification(2, 3, 1, 'hr-user')['result'] === 'pending', 'BQ assignment is not delegated.');
    $assert($service->recordBasisQualificationResult(7, 'suitable', '', 1, 'hr-user')['result'] === 'suitable', 'BQ result is not delegated.');

    foreach ([$recruitment, $templates, $interviews, $statuses, $basisQualifications] as $collaborator) {
        foreach ($collaborator->calls as [$method, $callArguments]) {
            if ($method === 'allowedTargets') {
                $assert(in_array($callArguments, [['received'], ['basis_qualification']], true), 'Status target lookup receives the wrong source status.');
                continue;
            }
            if ($method === 'orderedStatuses') {
                $assert($callArguments === [], 'Ordered status lookup receives unexpected arguments.');
                continue;
            }
            $assert($callArguments[0] === $repository, "$method does not receive the app repository.");
        }
    }
    $assert($templates->calls[2][0] === 'updateQuestion', 'Question update is not delegated.');
    $assert($repository->calls === [
        ['templateSnapshot', [11]],
        ['basisQualificationAssignments', [2]],
    ], 'Direct repository access exceeds the explicit detail reads.');

    echo "Filzmann Recruitment use-case service tests passed\n";
}
