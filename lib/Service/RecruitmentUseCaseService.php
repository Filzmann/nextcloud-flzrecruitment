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
        private HiringWorkflowService $hiring,
        private BasisQualificationService $basisQualifications,
        private RecruitmentPermissionSettingsService $permissionSettings,
    ) {}

    public function overview(): array {
        $overview = $this->recruitment->overview($this->repository);
        foreach ($overview['applications'] as &$application) {
            $application['allowedStatuses'] = $this->allowedStatusesFor($application);
        }
        unset($application);
        $overview['applicationStatuses'] = $this->statuses->orderedStatuses();
        return $overview;
    }

    public function applicationDetail(int $id): array {
        $detail = $this->recruitment->applicationDetail($this->repository, $id);
        $detail['allowedStatuses'] = $this->allowedStatusesFor($detail['application']);
        return $detail;
    }

    /** @param array<string,mixed> $application
     *  @return list<string>
     */
    private function allowedStatusesFor(array $application): array {
        $allowedStatuses = $this->statuses->allowedTargets((string)$application['status']);
        if (is_array($application['basisQualification'] ?? null)) {
            $internalApplication = $this->repository->findApplication((int)$application['id']);
            if ((string)($internalApplication['basisQualification']['result'] ?? '') !== 'suitable') {
                $allowedStatuses = array_values(array_filter(
                    $allowedStatuses,
                    static fn(string $status): bool => $status !== 'approved_for_hire',
                ));
            }
        }
        return $allowedStatuses;
    }

    /** @return array<string,mixed> */
    public function applicationSummary(int $id): array { return $this->repository->findApplication($id); }
    /** @return array<string,mixed> */
    public function applicationForInterview(int $id): array { return $this->repository->applicationForInterview($id); }

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
        bool $basisQualificationRequired = false,
        string $professionCategory = '',
        string $contractTerm = '', string $payGrade = '', ?float $advertisedWeeklyHours = null,
        ?float $fullTimeWeeklyHours = null, ?float $vacationDays = null, string $workLocation = 'Berlin',
    ): int {
        return $this->recruitment->createJob(
            $this->repository,
            $internalTitle,
            $publicTitle,
            $active,
            $responsibleUsers,
            $responsibleGroups,
            $assignmentKey,
            $basisQualificationRequired,
            $professionCategory,
            $contractTerm, $payGrade, $advertisedWeeklyHours, $fullTimeWeeklyHours, $vacationDays, $workLocation,
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
        ?float $desiredWeeklyHours = null,
        ?float $desiredWeeklyHoursMax = null,
    ): int {
        return $this->recruitment->createApplication(
            $this->repository,
            $personId,
            $jobId,
            $source,
            $receivedOn,
            $assigneeUid,
            $desiredWeeklyHours,
            $desiredWeeklyHoursMax,
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
        string $areaKey = '',
        array $validAreaKeys = [],
        string $clientKey = '',
        bool $override = false,
    ): array {
        return $this->statuses->transition(
            $this->repository,
            $id,
            $status,
            $version,
            $actorUid,
            $areaKey,
            $validAreaKeys,
            $clientKey,
            $override,
        );
    }

    /** @return array{data: array<string,mixed>, version: int} */
    public function hiringData(int $applicationId): array {
        return $this->hiring->detail($this->repository, $applicationId);
    }

    /** @param array<string,mixed> $data
     *  @return array{data: array<string,mixed>, version: int}
     */
    public function saveHiringData(int $applicationId, array $data, int $version, string $actorUid): array {
        return $this->hiring->save($this->repository, $applicationId, $data, $version, $actorUid);
    }
    public function savePayrollData(int $applicationId, array $data, int $version, string $actorUid): array {
        return $this->hiring->savePayroll($this->repository, $applicationId, $data, $version, $actorUid);
    }

    /** @return list<array<string,mixed>> */
    public function payrollList(): array { return $this->hiring->payrollList($this->repository); }

    /** @return list<array<string,mixed>> */
    public function basisQualificationRuns(): array {
        return $this->basisQualifications->runs($this->repository);
    }

    /** @return list<array<string,mixed>> */
    public function basisQualificationAssignments(int $applicationId): array {
        $this->repository->findApplication($applicationId);
        return $this->repository->basisQualificationAssignments($applicationId);
    }

    public function createBasisQualificationRun(string $startsOn, string $endsOn, string $actorUid): int {
        return $this->basisQualifications->createRun($this->repository, $startsOn, $endsOn, $actorUid);
    }

    /** @return array<string,mixed> */
    public function setJobBasisQualificationRequired(
        int $jobId,
        bool $required,
        int $version,
        string $actorUid,
    ): array {
        return $this->basisQualifications->setJobRequirement(
            $this->repository,
            $jobId,
            $required,
            $version,
            $actorUid,
        );
    }

    /** @return array<string,mixed> */
    public function assignBasisQualification(
        int $applicationId,
        int $runId,
        int $applicationVersion,
        string $actorUid,
    ): array {
        return $this->basisQualifications->assign(
            $this->repository,
            $applicationId,
            $runId,
            $applicationVersion,
            $actorUid,
        );
    }

    /** @return array<string,mixed> */
    public function recordBasisQualificationResult(
        int $assignmentId,
        string $result,
        string $note,
        int $version,
        string $actorUid,
    ): array {
        return $this->basisQualifications->recordResult(
            $this->repository,
            $assignmentId,
            $result,
            $note,
            $version,
            $actorUid,
        );
    }

    /** @return array<string,mixed> */
    public function setFirstGuideAccess(int $applicationId, bool $enabled, int $version, string $actorUid): array {
        return $this->hiring->setFirstGuideAccess($this->repository, $applicationId, $enabled, $version, $actorUid);
    }

    /** @param list<array<string,mixed>> $representatives
     *  @param list<string> $validAreaKeys
     *  @return array<string,mixed>
     */
    public function saveRepresentatives(array $representatives, int $revision, string $actorUid, array $validAreaKeys): array {
        $saved = $this->permissionSettings->saveRepresentatives($representatives, $revision, $actorUid, $validAreaKeys);
        $this->repository->recordPermissionAudit($actorUid, 'representatives_saved', '', null, [
            'representatives' => array_map(static fn(array $item): array => [
                'uid' => $item['uid'],
                'capabilities' => $item['capabilities'],
                'all' => $item['all'],
                'areaKeys' => $item['areaKeys'],
                'applicationIds' => $item['applicationIds'],
            ], $saved['representatives']),
            'revision' => $saved['revision'],
        ]);
        return $saved;
    }

    /** @return array<string,mixed> */
    public function saveFirstGuideGroup(string $groupId, int $revision, string $actorUid): array {
        $saved = $this->permissionSettings->saveFirstGuideGroup($groupId, $revision, $actorUid);
        $this->repository->recordPermissionAudit($actorUid, 'first_guide_group_saved', '', null, [
            'groupId' => $saved['firstGuideGroupId'],
            'revision' => $saved['revision'],
        ]);
        return $saved;
    }
}
