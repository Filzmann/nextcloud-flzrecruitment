<?php

declare(strict_types=1);

namespace OCA\Recruitment\Controller;

use OCA\Recruitment\AppInfo\Application;
use OCA\Recruitment\Exception\AccessDeniedException;
use OCA\Recruitment\Exception\ConflictException;
use OCA\Recruitment\Exception\NotFoundException;
use OCA\Recruitment\Exception\ValidationException;
use OCA\Recruitment\Service\RecruitmentAccessService;
use OCA\Recruitment\Service\RecruitmentPermissionPolicy;
use OCA\Recruitment\Service\RecruitmentUseCaseService;
use OCA\Recruitment\Service\MailInboxService;
use OCA\Recruitment\Service\DocumentReviewService;
use OCA\Recruitment\Service\DocumentFieldLinkService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * HTTP-Adapter des ersten vertikalen Recruitment-Durchstichs.
 */
final class ApiController extends Controller {
    public function __construct(
        IRequest $request,
        private RecruitmentAccessService $access,
        private RecruitmentUseCaseService $useCases,
        private MailInboxService $inboxService,
        private DocumentReviewService $documentReview,
        private DocumentFieldLinkService $documentFieldLinks,
        private LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function attachmentDocument(int $id): DataDisplayResponse|JSONResponse {
        try {
            $context = $this->documentReview->context($id);
            $this->requireDocumentRead($context);
            $document = $this->documentReview->document($id);
            return new DataDisplayResponse($document['content'], Http::STATUS_OK, [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "sandbox; default-src 'none'",
            ]);
        } catch (\Throwable $error) {
            return $this->errorResponse($error);
        }
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function attachmentComments(int $id): JSONResponse {
        return $this->respond(function () use ($id): array {
            $context = $this->documentReview->context($id);
            $this->requireDocumentRead($context);
            return ['comments' => $this->documentReview->comments($id)];
        });
    }

    #[NoAdminRequired]
    public function createAttachmentComment(
        int $id,
        string $kind,
        string $body,
        ?int $pageNumber,
        string $anchorColumn,
        string $clientKey,
    ): JSONResponse {
        return $this->respond(function () use ($id, $kind, $body, $pageNumber, $anchorColumn, $clientKey): array {
            $context = $this->documentReview->context($id);
            $this->requireDocumentWrite($context);
            return $this->documentReview->addComment(
                $id,
                $kind,
                $body,
                $pageNumber,
                $anchorColumn,
                $clientKey,
                $this->access->currentUid(),
            );
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function attachmentFieldContext(int $id, string $targetField): JSONResponse {
        return $this->respond(function () use ($id, $targetField): array {
            $context = $this->documentReview->context($id);
            $this->requireDocumentFieldWrite($context, $targetField);
            return $this->documentFieldLinks->fieldContext($id, $targetField);
        });
    }

    /** @param list<array<string,mixed>> $rectangles */
    #[NoAdminRequired]
    public function createAttachmentFieldLink(
        int $id,
        string $targetField,
        string $selectedText,
        string $appliedValue,
        int $pageNumber,
        array $rectangles,
        bool $replaceExisting,
        int $expectedVersion,
        string $clientKey,
    ): JSONResponse {
        return $this->respond(function () use (
            $id, $targetField, $selectedText, $appliedValue, $pageNumber,
            $rectangles, $replaceExisting, $expectedVersion, $clientKey,
        ): array {
            $context = $this->documentReview->context($id);
            $this->requireDocumentFieldWrite($context, $targetField);
            return $this->documentFieldLinks->linkSelection(
                $id,
                $targetField,
                $selectedText,
                $appliedValue,
                $pageNumber,
                $rectangles,
                $replaceExisting,
                $expectedVersion,
                $clientKey,
                $this->access->currentUid(),
            );
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function inbox(): JSONResponse {
        return $this->respond(function (): array {
            $this->access->requireManageUnassignedInbox();
            return ['messages' => $this->inboxService->messages()];
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function inboxMessage(int $id): JSONResponse {
        return $this->respond(function () use ($id): array {
            $message = $this->inboxService->message($id);
            $applicationId = $message['applicationId'] ?? null;
            if ($applicationId === null) {
                $this->access->requireManageUnassignedInbox();
            } else {
                $application = $this->useCases->applicationSummary((int)$applicationId);
                $this->access->require(RecruitmentAccessService::VIEW, $application);
            }
            return $message;
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function applicationMessages(int $id): JSONResponse {
        return $this->respond(function () use ($id): array {
            $application = $this->useCases->applicationSummary($id);
            $this->access->require(RecruitmentAccessService::VIEW, $application);
            return ['messages' => $this->inboxService->messagesForApplication($id)];
        });
    }

    #[NoAdminRequired]
    public function assignInboxMessage(int $id, int $applicationId, int $version): JSONResponse {
        return $this->respond(function () use ($id, $applicationId, $version): array {
            $this->access->requireManageUnassignedInbox();
            return $this->inboxService->assign($id, $applicationId, $version, $this->access->currentUid());
        });
    }

    #[NoAdminRequired]
    public function ignoreInboxMessage(int $id, int $version): JSONResponse {
        return $this->respond(function () use ($id, $version): array {
            $this->access->requireManageUnassignedInbox();
            return $this->inboxService->ignore($id, $version, $this->access->currentUid());
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function bootstrap(): JSONResponse {
        return $this->respond(function (): array {
            $this->access->requireAnyAccess();
            $capabilities = $this->access->capabilities();
            $areas = $this->access->organization()->toArray()['areas'];
            $payload = [
                'data' => $this->access->filterOverview($this->useCases->overview()),
                'capabilities' => $capabilities,
                'areas' => array_map(
                    static fn(string $key, array $area): array => ['key' => $key, 'label' => $area['label']],
                    array_keys($areas),
                    array_values($areas),
                ),
            ];
            if ($capabilities[RecruitmentAccessService::VIEW_HIRING_DATA] ?? false) {
                $payload['hiringData'] = $this->access->filterHiringData($this->useCases->payrollList());
            }
            if ($capabilities[RecruitmentAccessService::MANAGE_BASIS_QUALIFICATION] ?? false) {
                $payload['basisQualificationRuns'] = $this->useCases->basisQualificationRuns();
            }
            if ($capabilities[RecruitmentAccessService::MANAGE_DELEGATIONS] ?? false) {
                $payload['permissionSettings'] = $this->access->permissionSettings();
                $payload['delegatableCapabilities'] = RecruitmentPermissionPolicy::DELEGATABLE_CAPABILITIES;
                $payload['isNextcloudAdmin'] = $this->access->isNextcloudAdmin();
            }
            return $payload;
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function applicationDetail(int $id): JSONResponse {
        return $this->respond(function () use ($id): array {
            $application = $this->useCases->applicationSummary($id);
            $this->access->require(RecruitmentAccessService::VIEW, $application);
            return $this->useCases->applicationDetail($id);
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function templateDetail(int $id): JSONResponse {
        return $this->respond(function () use ($id): array {
            if (!$this->access->canSomewhere(RecruitmentAccessService::INTERVIEW)
                && !$this->access->canSomewhere(RecruitmentAccessService::MANAGE_CATALOG)) {
                throw new AccessDeniedException();
            }
            return $this->useCases->templateDetail($id);
        });
    }

    #[NoAdminRequired]
    public function createJob(
        string $internalTitle,
        string $publicTitle = '',
        bool $active = true,
        array $responsibleUsers = [],
        array $responsibleGroups = [],
        string $assignmentKey = '',
        bool $basisQualificationRequired = false,
        string $professionCategory = '',
    ): JSONResponse {
        return $this->respond(function () use (
            $internalTitle,
            $publicTitle,
            $active,
            $responsibleUsers,
            $responsibleGroups,
            $assignmentKey,
            $basisQualificationRequired,
            $professionCategory,
        ): array {
            $this->access->require(RecruitmentAccessService::MANAGE_CATALOG);
            return ['id' => $this->useCases->createJob(
                $internalTitle,
                $publicTitle,
                $active,
                $responsibleUsers,
                $responsibleGroups,
                $assignmentKey,
                $basisQualificationRequired,
                $professionCategory,
            )];
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function createBasisQualificationRun(string $startsOn, string $endsOn): JSONResponse {
        return $this->respond(function () use ($startsOn, $endsOn): array {
            $this->access->require(RecruitmentAccessService::MANAGE_BASIS_QUALIFICATION);
            return ['id' => $this->useCases->createBasisQualificationRun(
                $startsOn,
                $endsOn,
                $this->access->currentUid(),
            )];
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function setJobBasisQualificationRequired(int $id, bool $required, int $version): JSONResponse {
        return $this->respond(function () use ($id, $required, $version): array {
            $this->access->require(RecruitmentAccessService::MANAGE_BASIS_QUALIFICATION);
            return $this->useCases->setJobBasisQualificationRequired(
                $id,
                $required,
                $version,
                $this->access->currentUid(),
            );
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function basisQualificationAssignments(int $id): JSONResponse {
        return $this->respond(function () use ($id): array {
            $this->access->require(RecruitmentAccessService::MANAGE_BASIS_QUALIFICATION);
            return $this->useCases->basisQualificationAssignments($id);
        });
    }

    #[NoAdminRequired]
    public function assignBasisQualification(int $id, int $runId, int $version): JSONResponse {
        return $this->respond(function () use ($id, $runId, $version): array {
            $this->access->require(RecruitmentAccessService::MANAGE_BASIS_QUALIFICATION);
            return $this->useCases->assignBasisQualification(
                $id,
                $runId,
                $version,
                $this->access->currentUid(),
            );
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function recordBasisQualificationResult(
        int $id,
        string $result,
        string $note,
        int $version,
    ): JSONResponse {
        return $this->respond(function () use ($id, $result, $note, $version): array {
            $this->access->require(RecruitmentAccessService::MANAGE_BASIS_QUALIFICATION);
            return $this->useCases->recordBasisQualificationResult(
                $id,
                $result,
                $note,
                $version,
                $this->access->currentUid(),
            );
        });
    }

    #[NoAdminRequired]
    public function createPerson(
        string $givenName,
        string $familyName,
        string $email = '',
        string $phone = '',
    ): JSONResponse {
        return $this->respond(function () use ($givenName, $familyName, $email, $phone): array {
            $this->access->require(RecruitmentAccessService::EDIT_APPLICATIONS);
            return ['id' => $this->useCases->createPerson(
                $givenName,
                $familyName,
                $email,
                $phone,
            )];
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function createApplication(
        int $personId,
        int $jobId,
        string $source,
        string $receivedOn,
        string $assigneeUid = '',
        ?float $desiredWeeklyHours = null,
        ?float $desiredWeeklyHoursMax = null,
    ): JSONResponse {
        return $this->respond(function () use ($personId, $jobId, $source, $receivedOn, $assigneeUid, $desiredWeeklyHours, $desiredWeeklyHoursMax): array {
            $this->access->require(RecruitmentAccessService::EDIT_APPLICATIONS);
            return ['id' => $this->useCases->createApplication(
                $personId,
                $jobId,
                $source,
                $receivedOn,
                $assigneeUid,
                $desiredWeeklyHours,
                $desiredWeeklyHoursMax,
            )];
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function createTemplate(
        string $name,
        string $type,
        string $description,
        string $audience,
    ): JSONResponse {
        return $this->respond(function () use ($name, $type, $description, $audience): array {
            $this->access->require(RecruitmentAccessService::MANAGE_CATALOG);
            return ['id' => $this->useCases->createTemplate(
                $name,
                $type,
                $description,
                $audience,
            )];
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function createQuestion(
        int $templateId,
        string $prompt,
        string $hint,
        string $type,
        bool $required,
        int $sortOrder,
        array $options,
        string $visibility,
    ): JSONResponse {
        return $this->respond(function () use (
            $templateId,
            $prompt,
            $hint,
            $type,
            $required,
            $sortOrder,
            $options,
            $visibility,
        ): array {
            $this->access->require(RecruitmentAccessService::MANAGE_CATALOG);
            return ['id' => $this->useCases->createQuestion(
                $templateId,
                $prompt,
                $hint,
                $type,
                $required,
                $sortOrder,
                $options,
                $visibility,
            )];
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
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
    ): JSONResponse {
        return $this->respond(function () use (
            $id,
            $prompt,
            $hint,
            $type,
            $required,
            $sortOrder,
            $options,
            $visibility,
            $active,
        ): array {
            $this->access->require(RecruitmentAccessService::MANAGE_CATALOG);
            $this->useCases->updateQuestion(
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
            return ['updated' => true];
        });
    }

    #[NoAdminRequired]
    public function createBubble(
        int $questionId,
        string $label,
        string $insertText,
        int $sortOrder,
        bool $active = true,
    ): JSONResponse {
        return $this->respond(function () use ($questionId, $label, $insertText, $sortOrder, $active): array {
            $this->access->require(RecruitmentAccessService::MANAGE_CATALOG);
            return ['id' => $this->useCases->createBubble(
                $questionId,
                $label,
                $insertText,
                $sortOrder,
                $active,
            )];
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function createInterview(int $applicationId, int $templateId): JSONResponse {
        return $this->respond(function () use ($applicationId, $templateId): array {
            $application = $this->useCases->applicationSummary($applicationId);
            $this->access->require(RecruitmentAccessService::INTERVIEW, $application);
            return ['id' => $this->useCases->createInterview(
                $applicationId,
                $templateId,
                $this->access->currentUid(),
            )];
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function saveInterviewDraft(int $id, array $answers, int $version): JSONResponse {
        return $this->respond(function () use ($id, $answers, $version): array {
            $application = $this->useCases->applicationForInterview($id);
            $this->access->require(RecruitmentAccessService::INTERVIEW, $application);
            return $this->useCases->saveInterviewDraft($id, $answers, $version);
        });
    }

    #[NoAdminRequired]
    public function completeInterview(int $id, array $answers, int $version): JSONResponse {
        return $this->respond(function () use ($id, $answers, $version): array {
            $application = $this->useCases->applicationForInterview($id);
            $this->access->require(RecruitmentAccessService::INTERVIEW, $application);
            return $this->useCases->completeInterview($id, $answers, $version);
        });
    }

    #[NoAdminRequired]
    public function transitionStatus(int $id, string $status, int $version, string $areaKey = ''): JSONResponse {
        return $this->respond(function () use ($id, $status, $version, $areaKey): array {
            $application = $this->useCases->applicationSummary($id);
            $this->access->require(RecruitmentAccessService::EDIT_APPLICATIONS, $application);
            return $this->useCases->transitionStatus(
                $id,
                $status,
                $version,
                $this->access->currentUid(),
                $areaKey,
                $this->access->organization()->areaKeys(),
            );
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function hiringData(int $id): JSONResponse {
        return $this->respond(function () use ($id): array {
            $application = $this->useCases->applicationSummary($id);
            $this->access->require(RecruitmentAccessService::VIEW_HIRING_DATA, $application);
            return $this->useCases->hiringData($id);
        });
    }

    #[NoAdminRequired]
    public function saveHiringData(int $id, array $data, int $version): JSONResponse {
        return $this->respond(function () use ($id, $data, $version): array {
            $application = $this->useCases->applicationSummary($id);
            $this->access->require(RecruitmentAccessService::EDIT_HIRING_DATA, $application);
            return $this->useCases->saveHiringData($id, $data, $version, $this->access->currentUid());
        });
    }

    #[NoAdminRequired]
    public function setFirstGuideAccess(int $id, bool $enabled, int $version): JSONResponse {
        return $this->respond(function () use ($id, $enabled, $version): array {
            $application = $this->useCases->applicationSummary($id);
            $this->access->require(RecruitmentAccessService::MANAGE_FIRST_GUIDE_ACCESS, $application);
            return $this->useCases->setFirstGuideAccess($id, $enabled, $version, $this->access->currentUid());
        });
    }

    #[NoAdminRequired]
    public function saveRepresentatives(array $representatives, int $revision): JSONResponse {
        return $this->respond(function () use ($representatives, $revision): array {
            $this->access->require(RecruitmentAccessService::MANAGE_DELEGATIONS);
            return $this->useCases->saveRepresentatives(
                $representatives,
                $revision,
                $this->access->currentUid(),
                $this->access->organization()->areaKeys(),
            );
        });
    }

    #[NoAdminRequired]
    public function saveFirstGuideGroup(string $groupId, int $revision): JSONResponse {
        return $this->respond(function () use ($groupId, $revision): array {
            if (!$this->access->isNextcloudAdmin()) throw new AccessDeniedException();
            return $this->useCases->saveFirstGuideGroup($groupId, $revision, $this->access->currentUid());
        });
    }

    private function respond(callable $operation, int $successStatus = Http::STATUS_OK): JSONResponse {
        try {
            return new JSONResponse($operation(), $successStatus);
        } catch (\Throwable $error) {
            return $this->errorResponse($error);
        }
    }

    /** @param array<string,mixed> $context */
    private function requireDocumentRead(array $context): void {
        if (($context['applicationId'] ?? null) === null) {
            $this->access->requireManageUnassignedInbox();
            return;
        }
        $application = $this->useCases->applicationSummary((int)$context['applicationId']);
        $this->access->require(RecruitmentAccessService::VIEW, $application);
    }

    /** @param array<string,mixed> $context */
    private function requireDocumentWrite(array $context): void {
        if (($context['applicationId'] ?? null) === null) {
            $this->access->requireManageUnassignedInbox();
            $this->access->require(RecruitmentAccessService::MANAGE_DOCUMENTS);
            return;
        }
        $application = $this->useCases->applicationSummary((int)$context['applicationId']);
        $this->access->require(RecruitmentAccessService::VIEW, $application);
        $this->access->require(RecruitmentAccessService::MANAGE_DOCUMENTS, $application);
    }

    /** @param array<string,mixed> $context */
    private function requireDocumentFieldWrite(array $context, string $targetField): void {
        if (($context['applicationId'] ?? null) === null) {
            throw new ValidationException('Das Dokument muss zuerst einer Bewerbung zugeordnet werden.');
        }
        $application = $this->useCases->applicationSummary((int)$context['applicationId']);
        $this->access->require(RecruitmentAccessService::VIEW, $application);
        $this->access->require(RecruitmentAccessService::MANAGE_DOCUMENTS, $application);
        $this->access->require($this->documentFieldLinks->requiredCapability($targetField), $application);
    }

    private function errorResponse(\Throwable $error): JSONResponse {
        if ($error instanceof AccessDeniedException) {
            return new JSONResponse(['message' => $error->getMessage()], Http::STATUS_FORBIDDEN);
        }
        if ($error instanceof NotFoundException) {
            return new JSONResponse(['message' => $error->getMessage()], Http::STATUS_NOT_FOUND);
        }
        if ($error instanceof ConflictException) {
            return new JSONResponse(['message' => $error->getMessage()], Http::STATUS_CONFLICT);
        }
        if ($error instanceof ValidationException) {
            return new JSONResponse(['message' => $error->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
        }
        $this->logger->error('AD-Recruitment-Anfrage fehlgeschlagen.', ['exceptionClass' => $error::class]);
        return new JSONResponse(
            ['message' => 'Die Anfrage konnte technisch nicht verarbeitet werden.'],
            Http::STATUS_INTERNAL_SERVER_ERROR,
        );
    }
}
