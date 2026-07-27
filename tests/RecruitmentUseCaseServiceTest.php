<?php

declare(strict_types=1);

namespace OCA\Recruitment\Repository {
    final class RecruitmentRepository {
        public array $calls = [];

        public function templateSnapshot(int $id): array {
            $this->calls[] = ['templateSnapshot', [$id]];
            return ['id' => $id, 'revision' => 3];
        }
    }
}

namespace OCA\Recruitment\Service {
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
}

namespace {
    require __DIR__ . '/../lib/Service/RecruitmentUseCaseService.php';

    use OCA\Recruitment\Repository\RecruitmentRepository;
    use OCA\Recruitment\Service\ApplicationStatusService;
    use OCA\Recruitment\Service\InterviewService;
    use OCA\Recruitment\Service\RecruitmentService;
    use OCA\Recruitment\Service\RecruitmentUseCaseService;
    use OCA\Recruitment\Service\TemplateService;

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
    $service = new RecruitmentUseCaseService(
        $repository,
        $recruitment,
        $templates,
        $interviews,
        $statuses,
    );

    $recruitment->returns = [
        'overview' => ['applications' => [['id' => 1]]],
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
        'transition' => ['id' => 2, 'status' => 'screening'],
    ];

    $assert($service->overview()['applications'][0]['id'] === 1, 'Overview is not delegated.');
    $detail = $service->applicationDetail(2);
    $assert($detail['allowedStatuses'] === ['screening'], 'Allowed status transitions are not composed.');
    $assert($service->templateDetail(11) === ['id' => 11, 'revision' => 3], 'Template detail is not delegated.');
    $assert($service->createJob('Intern', 'Public', true, ['user-a'], ['group-a'], 'assignment') === 3, 'Job creation is not delegated.');
    $assert($service->createPerson('Alex', 'Beispiel', 'alex@example.invalid', '123') === 4, 'Person creation is not delegated.');
    $assert($service->createApplication(4, 3, 'portal', '2026-07-27', 'editor-user') === 5, 'Application creation is not delegated.');
    $assert($service->createTemplate('Interview', 'interview', 'Description', 'internal') === 6, 'Template creation is not delegated.');
    $assert($service->createQuestion(6, 'Prompt', 'Hint', 'text', true, 10, ['A'], 'internal') === 7, 'Question creation is not delegated.');
    $service->updateQuestion(7, 'Prompt 2', 'Hint 2', 'choice', false, 20, ['B'], 'all', true);
    $assert($service->createBubble(7, 'Positive', 'Text', 10, true) === 8, 'Bubble creation is not delegated.');
    $assert($service->createInterview(5, 6, 'editor-user') === 9, 'Interview creation is not delegated.');
    $assert($service->saveInterviewDraft(9, ['7' => 'Draft'], 1)['version'] === 2, 'Interview draft is not delegated.');
    $assert($service->completeInterview(9, ['7' => 'Done'], 2)['status'] === 'completed', 'Interview completion is not delegated.');
    $assert($service->transitionStatus(2, 'screening', 1, 'editor-user')['status'] === 'screening', 'Status transition is not delegated.');

    foreach ([$recruitment, $templates, $interviews, $statuses] as $collaborator) {
        foreach ($collaborator->calls as [$method, $callArguments]) {
            if ($method === 'allowedTargets') {
                $assert($callArguments === ['received'], 'Status target lookup receives the wrong source status.');
                continue;
            }
            $assert($callArguments[0] === $repository, "$method does not receive the app repository.");
        }
    }
    $assert($templates->calls[2][0] === 'updateQuestion', 'Question update is not delegated.');
    $assert($repository->calls === [['templateSnapshot', [11]]], 'Direct repository access exceeds template detail.');

    echo "AD Recruitment use-case service tests passed\n";
}
