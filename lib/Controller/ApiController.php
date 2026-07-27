<?php

declare(strict_types=1);

namespace OCA\Recruitment\Controller;

use OCA\Recruitment\AppInfo\Application;
use OCA\Recruitment\Exception\AccessDeniedException;
use OCA\Recruitment\Exception\ConflictException;
use OCA\Recruitment\Exception\NotFoundException;
use OCA\Recruitment\Exception\ValidationException;
use OCA\Recruitment\Service\RecruitmentAccessService;
use OCA\Recruitment\Service\RecruitmentUseCaseService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
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
        private LoggerInterface $logger,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function bootstrap(): JSONResponse {
        return $this->respond(function (): array {
            $this->access->require(RecruitmentAccessService::VIEW);
            return [
                'data' => $this->useCases->overview(),
                'capabilities' => $this->access->capabilities(),
            ];
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function applicationDetail(int $id): JSONResponse {
        return $this->respond(function () use ($id): array {
            $this->access->require(RecruitmentAccessService::VIEW);
            return $this->useCases->applicationDetail($id);
        });
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function templateDetail(int $id): JSONResponse {
        return $this->respond(function () use ($id): array {
            $this->access->require(RecruitmentAccessService::VIEW);
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
    ): JSONResponse {
        return $this->respond(function () use (
            $internalTitle,
            $publicTitle,
            $active,
            $responsibleUsers,
            $responsibleGroups,
            $assignmentKey,
        ): array {
            $this->access->require(RecruitmentAccessService::MANAGE_CATALOG);
            return ['id' => $this->useCases->createJob(
                $internalTitle,
                $publicTitle,
                $active,
                $responsibleUsers,
                $responsibleGroups,
                $assignmentKey,
            )];
        }, Http::STATUS_CREATED);
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
    ): JSONResponse {
        return $this->respond(function () use ($personId, $jobId, $source, $receivedOn, $assigneeUid): array {
            $this->access->require(RecruitmentAccessService::EDIT_APPLICATIONS);
            return ['id' => $this->useCases->createApplication(
                $personId,
                $jobId,
                $source,
                $receivedOn,
                $assigneeUid,
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
            $this->access->require(RecruitmentAccessService::INTERVIEW);
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
            $this->access->require(RecruitmentAccessService::INTERVIEW);
            return $this->useCases->saveInterviewDraft($id, $answers, $version);
        });
    }

    #[NoAdminRequired]
    public function completeInterview(int $id, array $answers, int $version): JSONResponse {
        return $this->respond(function () use ($id, $answers, $version): array {
            $this->access->require(RecruitmentAccessService::INTERVIEW);
            return $this->useCases->completeInterview($id, $answers, $version);
        });
    }

    #[NoAdminRequired]
    public function transitionStatus(int $id, string $status, int $version): JSONResponse {
        return $this->respond(function () use ($id, $status, $version): array {
            $this->access->require(RecruitmentAccessService::EDIT_APPLICATIONS);
            return $this->useCases->transitionStatus(
                $id,
                $status,
                $version,
                $this->access->currentUid(),
            );
        });
    }

    private function respond(callable $operation, int $successStatus = Http::STATUS_OK): JSONResponse {
        try {
            return new JSONResponse($operation(), $successStatus);
        } catch (AccessDeniedException $error) {
            return new JSONResponse(['message' => $error->getMessage()], Http::STATUS_FORBIDDEN);
        } catch (NotFoundException $error) {
            return new JSONResponse(['message' => $error->getMessage()], Http::STATUS_NOT_FOUND);
        } catch (ConflictException $error) {
            return new JSONResponse(['message' => $error->getMessage()], Http::STATUS_CONFLICT);
        } catch (ValidationException $error) {
            return new JSONResponse(['message' => $error->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $error) {
            $this->logger->error('AD-Recruitment-Anfrage fehlgeschlagen.', ['exceptionClass' => $error::class]);
            return new JSONResponse(
                ['message' => 'Die Anfrage konnte technisch nicht verarbeitet werden.'],
                Http::STATUS_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
