<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Controller;

use OCA\FlzRecruitment\AppInfo\Application;
use OCA\FlzRecruitment\Exception\AccessDeniedException;
use OCA\FlzRecruitment\Exception\ConflictException;
use OCA\FlzRecruitment\Exception\NotFoundException;
use OCA\FlzRecruitment\Exception\ValidationException;
use OCA\FlzRecruitment\Service\RecruitmentAccessService;
use OCA\FlzRecruitment\Service\RecruitmentPermissionPolicy;
use OCA\FlzRecruitment\Service\RecruitmentUseCaseService;
use OCA\FlzRecruitment\Service\MailInboxService;
use OCA\FlzRecruitment\Service\DocumentReviewService;
use OCA\FlzRecruitment\Service\DocumentFieldLinkService;
use OCA\FlzRecruitment\Service\StatusMailService;
use OCA\FlzRecruitment\Service\JobResponsibilityService;
use OCA\FlzRecruitment\Contract\CandidatePoolStore;
use OCA\FlzRecruitment\Service\CandidatePoolService;
use OCA\FlzRecruitment\Service\CandidatePoolSettingsService;
use OCA\FlzRecruitment\Service\ResumeExtractionSettingsService;
use DateTimeImmutable;
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
        private JobResponsibilityService $jobResponsibilities,
        private LoggerInterface $logger,
        private ?StatusMailService $statusMail = null,
        private ?CandidatePoolStore $candidatePoolStore = null,
        private ?CandidatePoolService $candidatePool = null,
        private ?CandidatePoolSettingsService $candidatePoolSettings = null,
        private ?ResumeExtractionSettingsService $resumeExtractionSettings = null,
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
    public function assignInboxMessage(int $id, int $applicationId, int $version, array $acceptedSuggestions = []): JSONResponse {
        return $this->respond(function () use ($id, $applicationId, $version, $acceptedSuggestions): array {
            $this->access->requireManageUnassignedInbox();
            return $this->inboxService->assign($id, $applicationId, $version, $this->access->currentUid(), $acceptedSuggestions);
        });
    }

    #[NoAdminRequired]
    public function createApplicationFromInbox(
        int $id,
        int $version,
        int $jobId,
        string $givenName,
        string $familyName,
        string $email,
        string $phone = '',
        string $assigneeUid = '',
        array $acceptedSuggestions = [],
    ): JSONResponse {
        return $this->respond(function () use (
            $id, $version, $jobId, $givenName, $familyName, $email,
            $phone, $assigneeUid, $acceptedSuggestions,
        ): array {
            $this->access->requireManageUnassignedInbox();
            $this->access->require(RecruitmentAccessService::EDIT_APPLICATIONS);
            return $this->inboxService->createAndAssignApplication(
                $id,
                $version,
                $jobId,
                $givenName,
                $familyName,
                $email,
                $phone,
                $assigneeUid,
                $acceptedSuggestions,
                $this->access->currentUid(),
            );
        }, Http::STATUS_CREATED);
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
            if ($capabilities[RecruitmentAccessService::MANAGE_CATALOG] ?? false) {
                $payload['jobResponsibilityGroups'] = $this->jobResponsibilities->allGroups();
            }
            if ($capabilities[RecruitmentAccessService::MANAGE_DELEGATIONS] ?? false) {
                $payload['permissionSettings'] = $this->access->permissionSettings();
                $payload['delegatableCapabilities'] = RecruitmentPermissionPolicy::DELEGATABLE_CAPABILITIES;
            }
            if (($capabilities[RecruitmentAccessService::MANAGE_MAIL_TEMPLATES] ?? false)
                || ($capabilities[RecruitmentAccessService::COMMUNICATE] ?? false)) {
                $mailConfiguration = $this->mailService()->configuration();
                $payload['mailConfiguration'] = ($capabilities[RecruitmentAccessService::MANAGE_MAIL_TEMPLATES] ?? false)
                    ? $mailConfiguration
                    : ['textBlocks' => $mailConfiguration['textBlocks'] ?? [], 'settings' => $mailConfiguration['settings'] ?? []];
            }
            if (($capabilities[RecruitmentAccessService::MANAGE_CANDIDATE_POOL] ?? false)
                && $this->candidatePoolStore !== null && $this->candidatePoolSettings !== null) {
                $payload['candidatePool'] = ['entries' => $this->poolStore()->candidatePoolEntries(), 'settings' => $this->poolSettings()->settings()];
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
        string $contractTerm = '', string $payGrade = '', ?float $advertisedWeeklyHours = null,
        ?float $fullTimeWeeklyHours = null, ?float $vacationDays = null, string $workLocation = 'Berlin',
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
            $contractTerm, $payGrade, $advertisedWeeklyHours, $fullTimeWeeklyHours, $vacationDays, $workLocation,
        ): array {
            $this->access->require(RecruitmentAccessService::MANAGE_CATALOG);
            $this->jobResponsibilities->validate($professionCategory, $responsibleGroups, $responsibleUsers);
            return ['id' => $this->useCases->createJob(
                $internalTitle,
                $publicTitle,
                $active,
                $responsibleUsers,
                $responsibleGroups,
                $assignmentKey,
                $basisQualificationRequired,
                $professionCategory,
                $contractTerm, $payGrade, $advertisedWeeklyHours, $fullTimeWeeklyHours, $vacationDays, $workLocation,
            )];
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function jobResponsibilityUsers(string $professionCategory, array $groupIds, string $query): JSONResponse {
        return $this->respond(function () use ($professionCategory, $groupIds, $query): array {
            $this->access->require(RecruitmentAccessService::MANAGE_CATALOG);
            return ['users' => $this->jobResponsibilities->searchUsers($professionCategory, $groupIds, $query)];
        });
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
    public function transitionStatus(int $id, string $status, int $version, string $areaKey = '', string $clientKey = '', bool $override = false): JSONResponse {
        return $this->respond(function () use ($id, $status, $version, $areaKey, $clientKey, $override): array {
            $application = $this->useCases->applicationSummary($id);
            $this->access->require(RecruitmentAccessService::EDIT_APPLICATIONS, $application);
            if ($override) $this->access->require(RecruitmentAccessService::OVERRIDE_STATUS_TRANSITIONS, $application);
            return $this->useCases->transitionStatus(
                $id,
                $status,
                $version,
                $this->access->currentUid(),
                $areaKey,
                $this->access->organization()->areaKeys(),
                $clientKey,
                $override,
            );
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function mailConfiguration(): JSONResponse {
        return $this->respond(function (): array {
            $this->access->require(RecruitmentAccessService::MANAGE_MAIL_TEMPLATES);
            return $this->mailService()->configuration();
        });
    }

    #[NoAdminRequired]
    public function createMailTemplate(string $name, string $subject, string $body, string $bodyFormat = 'plain'): JSONResponse {
        return $this->respond(function () use ($name, $subject, $body, $bodyFormat): array {
            $this->access->require(RecruitmentAccessService::MANAGE_MAIL_TEMPLATES);
            return $this->mailService()->createTemplate($name, $subject, $body, $bodyFormat, $this->access->currentUid());
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function reviseMailTemplate(int $id, string $name, string $subject, string $body, bool $active, int $version, string $bodyFormat = 'plain'): JSONResponse {
        return $this->respond(function () use ($id, $name, $subject, $body, $bodyFormat, $active, $version): array {
            $this->access->require(RecruitmentAccessService::MANAGE_MAIL_TEMPLATES);
            return $this->mailService()->reviseTemplate($id, $name, $subject, $body, $bodyFormat, $active, $version, $this->access->currentUid());
        });
    }

    #[NoAdminRequired]
    public function saveStatusMailRule(string $fromStatus, string $toStatus, int $templateId, bool $enabled, string $defaultTiming, int $version): JSONResponse {
        return $this->respond(function () use ($fromStatus, $toStatus, $templateId, $enabled, $defaultTiming, $version): array {
            $this->access->require(RecruitmentAccessService::MANAGE_MAIL_TEMPLATES);
            return $this->mailService()->configureRule($fromStatus, $toStatus, $templateId, $enabled, $defaultTiming, $version, $this->access->currentUid());
        });
    }

    #[NoAdminRequired]
    public function createMailTextBlock(string $label, string $insertText): JSONResponse {
        return $this->respond(function () use ($label, $insertText): array {
            $this->access->require(RecruitmentAccessService::MANAGE_MAIL_TEMPLATES);
            return $this->mailService()->createTextBlock($label, $insertText, $this->access->currentUid());
        }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function applicationMailDrafts(int $id): JSONResponse {
        return $this->respond(function () use ($id): array {
            $application = $this->useCases->applicationSummary($id);
            $this->access->require(RecruitmentAccessService::COMMUNICATE, $application);
            return ['drafts' => $this->mailService()->drafts($id)];
        });
    }

    #[NoAdminRequired]
    public function saveMailDraft(int $id, string $subject, string $body, int $version, string $bodyFormat = 'plain'): JSONResponse {
        return $this->respond(function () use ($id, $subject, $body, $bodyFormat, $version): array {
            $draft = $this->mailService()->draft($id); $application = $this->useCases->applicationSummary((int)$draft['applicationId']);
            $this->access->require(RecruitmentAccessService::COMMUNICATE, $application);
            return $this->mailService()->saveDraft($id, $subject, $body, $bodyFormat, $version);
        });
    }

    #[NoAdminRequired]
    public function approveMailDraft(int $id, string $subject, string $body, string $recipient, string $timing, ?string $scheduledAt, int $version, string $jobKey, string $bodyFormat = 'plain'): JSONResponse {
        return $this->respond(function () use ($id, $subject, $body, $bodyFormat, $recipient, $timing, $scheduledAt, $version, $jobKey): array {
            $draft = $this->mailService()->draft($id); $application = $this->useCases->applicationSummary((int)$draft['applicationId']);
            $this->access->require(RecruitmentAccessService::COMMUNICATE, $application);
            return $this->mailService()->approveDraft($id, $subject, $body, $bodyFormat, $recipient, $timing, $scheduledAt, $version, $this->access->currentUid(), $jobKey);
        });
    }

    #[NoAdminRequired]
    public function cancelMailDraft(int $id, int $version): JSONResponse {
        return $this->respond(function () use ($id, $version): array {
            $draft = $this->mailService()->draft($id); $application = $this->useCases->applicationSummary((int)$draft['applicationId']);
            $this->access->require(RecruitmentAccessService::COMMUNICATE, $application);
            return $this->mailService()->cancelDraft($id, $version);
        });
    }

    #[NoAdminRequired]
    public function saveMailSettings(bool $testMode, string $testRecipient, int $revision): JSONResponse {
        return $this->respond(function () use ($testMode, $testRecipient, $revision): array {
            if (!$this->access->isNextcloudAdmin()) throw new AccessDeniedException();
            return $this->mailService()->saveSettings($testMode, $testRecipient, $revision);
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
    public function savePayrollData(int $id, array $data, int $version): JSONResponse {
        return $this->respond(function () use ($id, $data, $version): array {
            $application = $this->useCases->applicationSummary($id);
            $this->access->require(RecruitmentAccessService::EDIT_PAYROLL_DATA, $application);
            return $this->useCases->savePayrollData($id, $data, $version, $this->access->currentUid());
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

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function candidatePool(): JSONResponse {
        return $this->respond(function (): array { $this->access->require(RecruitmentAccessService::MANAGE_CANDIDATE_POOL); return ['entries' => $this->poolStore()->candidatePoolEntries(), 'settings' => $this->poolSettings()->settings()]; });
    }

    #[NoAdminRequired]
    public function requestCandidatePool(int $id): JSONResponse {
        return $this->respond(function () use ($id): array { $this->access->require(RecruitmentAccessService::MANAGE_CANDIDATE_POOL); return $this->poolService()->request($this->poolStore(), $id, $this->poolSettings()->settings(), $this->access->currentUid(), new DateTimeImmutable('now')); }, Http::STATUS_CREATED);
    }

    #[NoAdminRequired]
    public function grantCandidatePoolConsent(int $id, string $evidenceType, string $evidenceReference, string $noticeVersion, array $areaKeys = []): JSONResponse {
        return $this->respond(function () use ($id, $evidenceType, $evidenceReference, $noticeVersion, $areaKeys): array { $this->access->require(RecruitmentAccessService::MANAGE_CANDIDATE_POOL); return $this->poolService()->grant($this->poolStore(), $id, $evidenceType, $evidenceReference, $noticeVersion, $areaKeys, $this->access->currentUid(), new DateTimeImmutable('now'), $this->poolSettings()->settings()); });
    }

    #[NoAdminRequired]
    public function withdrawCandidatePoolConsent(int $id): JSONResponse {
        return $this->respond(function () use ($id): array { $this->access->require(RecruitmentAccessService::MANAGE_CANDIDATE_POOL); return $this->poolService()->withdraw($this->poolStore(), $id, $this->access->currentUid(), new DateTimeImmutable('now')); });
    }

    #[NoAdminRequired]
    public function saveCandidatePoolSettings(bool $enabled, string $noticeVersion, int $consentMonths, int $reminderDays, int $revision): JSONResponse {
        return $this->respond(function () use ($enabled, $noticeVersion, $consentMonths, $reminderDays, $revision): array {
            $this->access->require(RecruitmentAccessService::MANAGE_CANDIDATE_POOL);
            return $this->poolSettings()->save($enabled, $noticeVersion, $consentMonths, $reminderDays, $revision);
        });
    }

    #[NoAdminRequired]
    public function saveResumeExtractionSettings(string $method, int $revision): JSONResponse {
        return $this->respond(function () use ($method, $revision): array {
            if (!$this->access->isNextcloudAdmin()) throw new AccessDeniedException();
            return $this->extractionSettings()->save($method, $revision);
        });
    }

    private function mailService(): StatusMailService {
        if ($this->statusMail === null) throw new \RuntimeException('Der Statusmail-Service ist nicht verfügbar.');
        return $this->statusMail;
    }

    private function poolStore(): CandidatePoolStore { return $this->candidatePoolStore ?? throw new \RuntimeException('Der Bewerberpool-Speicher ist nicht verfügbar.'); }
    private function poolService(): CandidatePoolService { return $this->candidatePool ?? throw new \RuntimeException('Der Bewerberpool-Service ist nicht verfügbar.'); }
    private function poolSettings(): CandidatePoolSettingsService { return $this->candidatePoolSettings ?? throw new \RuntimeException('Die Bewerberpool-Einstellungen sind nicht verfügbar.'); }
    private function extractionSettings(): ResumeExtractionSettingsService { return $this->resumeExtractionSettings ?? throw new \RuntimeException('Die Einstellungen zur Lebenslaufextraktion sind nicht verfügbar.'); }

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
        $this->logger->error('Filzmann-Recruitment-Anfrage fehlgeschlagen.', ['exceptionClass' => $error::class]);
        return new JSONResponse(
            ['message' => 'Die Anfrage konnte technisch nicht verarbeitet werden.'],
            Http::STATUS_INTERNAL_SERVER_ERROR,
        );
    }
}
