<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\Recruitment\Repository\RecruitmentRepository;

/**
 * Orchestriert die API-Anwendungsfälle zwischen Fachservices und dem app-eigenen Repository.
 * Berechtigungen und HTTP-Abbildung bleiben im Controller; Fachregeln bleiben in den spezialisierten Services.
 */
final class RecruitmentUseCaseService {
    public function __construct(
        private RecruitmentRepository $repository,
        private RecruitmentService $recruitment,
        private TemplateService $templates,
        private InterviewService $interviews,
        private ApplicationStatusService $statuses,
    ) {}

    public function overview(): array {
        return $this->recruitment->overview($this->repository);
    }

    public function applicationDetail(int $id): array {
        $detail = $this->recruitment->applicationDetail($this->repository, $id);
        $detail['allowedStatuses'] = $this->statuses->allowedTargets(
            (string)$detail['application']['status'],
        );
        return $detail;
    }

    public function templateDetail(int $id): array {
        return $this->repository->templateSnapshot($id);
    }

    public function createJob(
        string $internalTitle,
        string $publicTitle,
        bool $active,
        array $responsibleUsers,
        array $responsibleGroups,
        string $assignmentKey,
    ): int {
        return $this->recruitment->createJob(
            $this->repository,
            $internalTitle,
            $publicTitle,
            $active,
            $responsibleUsers,
            $responsibleGroups,
            $assignmentKey,
        );
    }

    public function createPerson(string $givenName, string $familyName, string $email, string $phone): int {
        return $this->recruitment->createPerson(
            $this->repository,
            $givenName,
            $familyName,
            $email,
            $phone,
        );
    }

    public function createApplication(
        int $personId,
        int $jobId,
        string $source,
        string $receivedOn,
        string $assigneeUid,
    ): int {
        return $this->recruitment->createApplication(
            $this->repository,
            $personId,
            $jobId,
            $source,
            $receivedOn,
            $assigneeUid,
        );
    }

    public function createTemplate(string $name, string $type, string $description, string $audience): int {
        return $this->templates->create($this->repository, $name, $type, $description, $audience);
    }

    public function createQuestion(
        int $templateId,
        string $prompt,
        string $hint,
        string $type,
        bool $required,
        int $sortOrder,
        array $options,
        string $visibility,
    ): int {
        return $this->templates->addQuestion(
            $this->repository,
            $templateId,
            $prompt,
            $hint,
            $type,
            $required,
            $sortOrder,
            $options,
            $visibility,
        );
    }

    public function updateQuestion(
        int $id,
        string $prompt,
        string $hint,
        string $type,
        bool $required,
        int $sortOrder,
        array $options,
        string $visibility,
        bool $active,
    ): void {
        $this->templates->updateQuestion(
            $this->repository,
            $id,
            $prompt,
            $hint,
            $type,
            $required,
            $sortOrder,
            $options,
            $visibility,
            $active,
        );
    }

    public function createBubble(
        int $questionId,
        string $label,
        string $insertText,
        int $sortOrder,
        bool $active,
    ): int {
        return $this->templates->addBubble(
            $this->repository,
            $questionId,
            $label,
            $insertText,
            $sortOrder,
            $active,
        );
    }

    public function createInterview(int $applicationId, int $templateId, string $actorUid): int {
        return $this->interviews->instantiate(
            $this->repository,
            $applicationId,
            $templateId,
            $actorUid,
        );
    }

    public function saveInterviewDraft(int $id, array $answers, int $version): array {
        return $this->interviews->saveDraft($this->repository, $id, $answers, $version);
    }

    public function completeInterview(int $id, array $answers, int $version): array {
        return $this->interviews->complete($this->repository, $id, $answers, $version);
    }

    public function transitionStatus(
        int $id,
        string $status,
        int $version,
        string $actorUid,
    ): array {
        return $this->statuses->transition(
            $this->repository,
            $id,
            $status,
            $version,
            $actorUid,
        );
    }
}
