<?php

declare(strict_types=1);

namespace OCP {
    interface IRequest {}
}

namespace OCP\AppFramework {
    class Controller {
        public function __construct(string $appName, \OCP\IRequest $request) {}
    }
    final class Http {
        public const STATUS_OK = 200;
        public const STATUS_CREATED = 201;
        public const STATUS_FORBIDDEN = 403;
        public const STATUS_NOT_FOUND = 404;
        public const STATUS_CONFLICT = 409;
        public const STATUS_UNPROCESSABLE_ENTITY = 422;
        public const STATUS_INTERNAL_SERVER_ERROR = 500;
    }
}

namespace OCP\AppFramework\Http {
    final class JSONResponse {
        public function __construct(private array $data = [], private int $status = 200) {}
        public function getData(): array { return $this->data; }
        public function getStatus(): int { return $this->status; }
    }
    final class DataDisplayResponse {
        public function __construct(private string $data = '', private int $status = 200, private array $headers = []) {}
        public function getData(): string { return $this->data; }
        public function getStatus(): int { return $this->status; }
        public function getHeaders(): array { return $this->headers; }
    }
}

namespace OCP\AppFramework\Http\Attribute {
    #[\Attribute(\Attribute::TARGET_METHOD)] final class NoAdminRequired {}
    #[\Attribute(\Attribute::TARGET_METHOD)] final class NoCSRFRequired {}
}

namespace Psr\Log {
    interface LoggerInterface {
        public function error(string $message, array $context = []): void;
    }
}

namespace OCA\Recruitment\AppInfo {
    final class Application { public const APP_ID = 'adrecruitment'; }
}

namespace OCA\Recruitment\Service {
    final class RecruitmentPermissionPolicy {
        public const DELEGATABLE_CAPABILITIES = ['view', 'interview'];
    }

    final class RecruitmentAccessService {
        public const VIEW = 'view';
        public const MANAGE_CATALOG = 'manage_catalog';
        public const EDIT_APPLICATIONS = 'edit_applications';
        public const INTERVIEW = 'interview';
        public const EDIT_HIRING_DATA = 'edit_hiring_data';
        public const EDIT_PAYROLL_DATA = 'edit_payroll_data';
        public const VIEW_HIRING_DATA = 'view_hiring_data';
        public const MANAGE_DOCUMENTS = 'manage_documents';
        public const COMMUNICATE = 'communicate';
        public const MANAGE_FIRST_GUIDE_ACCESS = 'manage_first_guide_access';
        public const MANAGE_BASIS_QUALIFICATION = 'manage_basis_qualification';
        public const MANAGE_MAIL_TEMPLATES = 'manage_mail_templates';
        public const MANAGE_DELEGATIONS = 'manage_delegations';
        public const MANAGE_CANDIDATE_POOL = 'manage_candidate_pool';
        public const OVERRIDE_STATUS_TRANSITIONS = 'override_status_transitions';
        /** @var list<string> */
        public array $allowed = [];
        /** @var list<string> */
        public array $required = [];
        public bool $isAdmin = false;
        public bool $manageInbox = false;
        public function require(string $capability, ?array $application = null): void {
            $this->required[] = $capability;
            if (!in_array($capability, $this->allowed, true)) {
                throw new \OCA\Recruitment\Exception\AccessDeniedException('Keine Berechtigung.');
            }
        }
        public function requireAnyAccess(): void {
            $this->required[] = 'any';
            if ($this->allowed === []) throw new \OCA\Recruitment\Exception\AccessDeniedException('Keine Berechtigung.');
        }
        public function requireSomewhere(string $capability): void { $this->require($capability); }
        public function canSomewhere(string $capability): bool { return in_array($capability, $this->allowed, true); }
        public function requireManageUnassignedInbox(): void {
            $this->required[] = 'manage_unassigned_inbox';
            if (!$this->manageInbox) throw new \OCA\Recruitment\Exception\AccessDeniedException('Keine Berechtigung.');
        }
        public function capabilities(): array {
            return [
                self::VIEW => in_array(self::VIEW, $this->allowed, true),
                self::MANAGE_CATALOG => in_array(self::MANAGE_CATALOG, $this->allowed, true),
                self::EDIT_APPLICATIONS => in_array(self::EDIT_APPLICATIONS, $this->allowed, true),
                self::INTERVIEW => in_array(self::INTERVIEW, $this->allowed, true),
                self::EDIT_HIRING_DATA => in_array(self::EDIT_HIRING_DATA, $this->allowed, true),
                self::EDIT_PAYROLL_DATA => in_array(self::EDIT_PAYROLL_DATA, $this->allowed, true),
                self::VIEW_HIRING_DATA => in_array(self::VIEW_HIRING_DATA, $this->allowed, true),
                self::MANAGE_DOCUMENTS => false,
                self::COMMUNICATE => false,
                self::MANAGE_FIRST_GUIDE_ACCESS => in_array(self::MANAGE_FIRST_GUIDE_ACCESS, $this->allowed, true),
                self::MANAGE_BASIS_QUALIFICATION => in_array(self::MANAGE_BASIS_QUALIFICATION, $this->allowed, true),
                self::MANAGE_MAIL_TEMPLATES => in_array(self::MANAGE_MAIL_TEMPLATES, $this->allowed, true),
                self::MANAGE_DELEGATIONS => in_array(self::MANAGE_DELEGATIONS, $this->allowed, true),
                self::MANAGE_CANDIDATE_POOL => in_array(self::MANAGE_CANDIDATE_POOL, $this->allowed, true),
                self::OVERRIDE_STATUS_TRANSITIONS => in_array(self::OVERRIDE_STATUS_TRANSITIONS, $this->allowed, true),
                'manage_unassigned_inbox' => $this->manageInbox,
            ];
        }
        public function filterOverview(array $overview): array { return $overview; }
        public function filterHiringData(array $items): array { return $items; }
        public function organization(): object { return new class {
            public function toArray(): array { return ['areas' => []]; }
            public function areaKeys(): array { return []; }
        }; }
        public function permissionSettings(): array { return []; }
        public function isNextcloudAdmin(): bool { return $this->isAdmin; }
        public function currentUid(): string { return 'editor-user'; }
    }

    final class RecruitmentUseCaseService {
        /** @var list<array{0: string, 1: array}> */
        public array $calls = [];
        public ?\Throwable $failure = null;
        public function overview(): array {
            $this->record('overview', []);
            return ['applications' => [['id' => 7]]];
        }
        public function applicationDetail(int $id): array {
            $this->record('applicationDetail', [$id]);
            return [
                'application' => ['id' => $id, 'status' => 'received'],
                'allowedStatuses' => ['screening'],
            ];
        }
        public function applicationSummary(int $id): array { return ['id' => $id, 'status' => 'received', 'areaKey' => '']; }
        public function applicationForInterview(int $id): array { return ['id' => 7, 'status' => 'received', 'areaKey' => '']; }
        public function payrollList(): array { return []; }
        public function basisQualificationRuns(): array {
            $this->record('basisQualificationRuns', []);
            return [['id' => 3, 'label' => 'BQ 09/26']];
        }
        public function basisQualificationAssignments(int $applicationId): array {
            $this->record('basisQualificationAssignments', [$applicationId]);
            return [['id' => 4, 'applicationId' => $applicationId, 'result' => 'pending']];
        }
        public function hiringData(int $id): array {
            $this->record('hiringData', [$id]);
            return ['data' => ['city' => 'Beispielstadt'], 'version' => 2];
        }
        public function templateDetail(int $id): array {
            $this->record('templateDetail', [$id]);
            return ['id' => $id, 'revision' => 2];
        }
        public function __call(string $method, array $arguments): mixed {
            $this->record($method, $arguments);
            return match ($method) {
                'updateQuestion' => null,
                'saveInterviewDraft' => ['id' => $arguments[0], 'status' => 'draft'],
                'completeInterview' => ['id' => $arguments[0], 'status' => 'completed'],
                'transitionStatus' => ['id' => $arguments[0], 'status' => $arguments[1]],
                'saveHiringData' => ['data' => $arguments[1], 'version' => $arguments[2] + 1],
                'savePayrollData' => ['data' => $arguments[1], 'version' => $arguments[2] + 1],
                'setFirstGuideAccess' => ['id' => $arguments[0], 'firstGuideAccess' => $arguments[1]],
                'saveRepresentatives' => ['representatives' => $arguments[0], 'revision' => $arguments[1] + 1],
                'saveFirstGuideGroup' => ['firstGuideGroupId' => $arguments[0], 'revision' => $arguments[1] + 1],
                'assignBasisQualification' => ['id' => 4, 'applicationId' => $arguments[0], 'result' => 'pending'],
                'recordBasisQualificationResult' => ['id' => $arguments[0], 'result' => $arguments[1]],
                'setJobBasisQualificationRequired' => ['id' => $arguments[0], 'basisQualificationRequired' => $arguments[1]],
                default => 12,
            };
        }
        private function record(string $method, array $arguments): void {
            if ($this->failure !== null) throw $this->failure;
            $this->calls[] = [$method, $arguments];
        }
    }

    final class JobResponsibilityService {
        public array $calls = [];
        public function allGroups(): array {
            $this->calls[] = ['allGroups', []];
            return [['id' => 'ad-Stab-HR', 'label' => 'Stabsstelle HR', 'professionCategories' => ['assistance']]];
        }
        public function validate(string $professionCategory, array $groupIds, array $userIds): void {
            $this->calls[] = ['validate', [$professionCategory, $groupIds, $userIds]];
        }
        public function searchUsers(string $professionCategory, array $groupIds, string $query): array {
            $this->calls[] = ['searchUsers', [$professionCategory, $groupIds, $query]];
            return [['uid' => 'editor-user', 'displayName' => 'Editor User']];
        }
    }

    final class MailInboxService {
        /** @var list<array{0:string,1:array}> */
        public array $calls = [];
        public function messages(): array { $this->calls[] = ['messages', []]; return [['id' => 21, 'applicationId' => null]]; }
        public function message(int $id): array { $this->calls[] = ['message', [$id]]; return ['id' => $id, 'applicationId' => null, 'version' => 1]; }
        public function messagesForApplication(int $id): array { $this->calls[] = ['messagesForApplication', [$id]]; return [['id' => 21, 'applicationId' => $id]]; }
        public function assign(int $id, int $applicationId, int $version, string $actorUid, array $acceptedSuggestions = []): array {
            $this->calls[] = ['assign', [$id, $applicationId, $version, $actorUid, $acceptedSuggestions]];
            return ['id' => $id, 'applicationId' => $applicationId, 'state' => 'assigned', 'version' => $version + 1];
        }
        public function createAndAssignApplication(
            int $id, int $version, int $jobId, string $givenName, string $familyName,
            string $email, string $phone, string $assigneeUid, array $acceptedSuggestions, string $actorUid,
        ): array {
            $arguments = [$id, $version, $jobId, $givenName, $familyName, $email, $phone, $assigneeUid, $acceptedSuggestions, $actorUid];
            $this->calls[] = ['createAndAssignApplication', $arguments];
            return ['personId' => 8, 'applicationId' => 9, 'message' => ['id' => $id, 'state' => 'assigned']];
        }
        public function ignore(int $id, int $version, string $actorUid): array {
            $this->calls[] = ['ignore', [$id, $version, $actorUid]];
            return ['id' => $id, 'state' => 'ignored', 'version' => $version + 1];
        }
    }

    final class DocumentReviewService {
        public ?int $applicationId = null;
        /** @var list<array{0:string,1:array}> */
        public array $calls = [];
        public function context(int $id): array { $this->calls[] = ['context', [$id]]; return ['id' => $id, 'applicationId' => $this->applicationId]; }
        public function document(int $id): array { $this->calls[] = ['document', [$id]]; return ['content' => '%PDF-1.4', 'originalName' => 'Dokument.pdf', 'mimeType' => 'application/pdf', 'contentHash' => 'hash']; }
        public function comments(int $id): array { $this->calls[] = ['comments', [$id]]; return [['id' => 1, 'attachmentId' => $id]]; }
        public function addComment(int $id, string $kind, string $body, ?int $pageNumber, string $anchorColumn, string $clientKey, string $actorUid): array {
            $this->calls[] = ['addComment', [$id, $kind, $body, $pageNumber, $anchorColumn, $clientKey, $actorUid]];
            return ['id' => 1, 'attachmentId' => $id, 'kind' => $kind, 'body' => $body];
        }
    }

    final class DocumentFieldLinkService {
        /** @var list<array{0:string,1:array}> */
        public array $calls = [];
        public function requiredCapability(string $targetField): string {
            $this->calls[] = ['requiredCapability', [$targetField]];
            return in_array($targetField, ['birthDate', 'birthPlace'], true)
                ? RecruitmentAccessService::EDIT_HIRING_DATA
                : RecruitmentAccessService::EDIT_APPLICATIONS;
        }
        public function fieldContext(int $id, string $targetField): array {
            $this->calls[] = ['fieldContext', [$id, $targetField]];
            return ['applicationId' => 7, 'targetField' => $targetField, 'value' => '', 'version' => 3];
        }
        public function linkSelection(
            int $id, string $targetField, string $selectedText, string $appliedValue,
            int $pageNumber, array $rectangles, bool $replaceExisting, int $expectedVersion,
            string $clientKey, string $actorUid,
        ): array {
            $this->calls[] = ['linkSelection', func_get_args()];
            return ['id' => 5, 'attachmentId' => $id, 'targetField' => $targetField, 'resultValue' => $appliedValue];
        }
    }

    final class ResumeExtractionSettingsService {
        public array $calls = [];
        public function settings(): array { return ['method' => 'rules', 'revision' => 0, 'options' => []]; }
        public function save(string $method, int $revision): array {
            $this->calls[] = ['save', [$method, $revision]];
            return ['method' => $method, 'revision' => $revision + 1, 'options' => []];
        }
    }

    final class CandidatePoolSettingsService {
        public array $calls = [];
        public function settings(): array { return ['enabled' => false, 'noticeVersion' => '', 'consentMonths' => 12, 'reminderDays' => 30, 'revision' => 0]; }
        public function save(bool $enabled, string $noticeVersion, int $consentMonths, int $reminderDays, int $revision): array {
            $this->calls[] = ['save', [$enabled, $noticeVersion, $consentMonths, $reminderDays, $revision]];
            return compact('enabled', 'noticeVersion', 'consentMonths', 'reminderDays') + ['revision' => $revision + 1];
        }
    }
}

namespace {
    use OCA\Recruitment\Controller\ApiController;
    use OCA\Recruitment\Service\RecruitmentAccessService;
    use OCA\Recruitment\Service\RecruitmentUseCaseService;
    use OCA\Recruitment\Service\MailInboxService;
    use OCA\Recruitment\Service\DocumentReviewService;
    use OCA\Recruitment\Service\DocumentFieldLinkService;
    use OCA\Recruitment\Service\JobResponsibilityService;
    use OCP\AppFramework\Http;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    $request = new class implements \OCP\IRequest {};
    $access = new RecruitmentAccessService();
    $useCases = new RecruitmentUseCaseService();
    $inbox = new MailInboxService();
    $documentReview = new DocumentReviewService();
    $documentFieldLinks = new DocumentFieldLinkService();
    $jobResponsibilities = new JobResponsibilityService();
    $resumeExtraction = new \OCA\Recruitment\Service\ResumeExtractionSettingsService();
    $candidatePoolSettings = new \OCA\Recruitment\Service\CandidatePoolSettingsService();
    $logger = new class implements \Psr\Log\LoggerInterface {
        public array $errors = [];
        public function error(string $message, array $context = []): void { $this->errors[] = [$message, $context]; }
    };
    $controller = new ApiController(
        $request,
        $access,
        $useCases,
        $inbox,
        $documentReview,
        $documentFieldLinks,
        $jobResponsibilities,
        $logger,
        null,
        null,
        null,
        $candidatePoolSettings,
        $resumeExtraction,
    );

    $response = $controller->bootstrap();
    $assert($response->getStatus() === Http::STATUS_FORBIDDEN, 'Anonymous API access is not rejected.');
    $assert($useCases->calls === [], 'Denied access reaches application services.');
    $assert($access->required === ['any'], 'Bootstrap does not require app access.');

    $access->allowed = [
        RecruitmentAccessService::VIEW,
        RecruitmentAccessService::MANAGE_CATALOG,
        RecruitmentAccessService::EDIT_APPLICATIONS,
        RecruitmentAccessService::INTERVIEW,
        RecruitmentAccessService::EDIT_HIRING_DATA,
        RecruitmentAccessService::EDIT_PAYROLL_DATA,
        RecruitmentAccessService::VIEW_HIRING_DATA,
        RecruitmentAccessService::MANAGE_FIRST_GUIDE_ACCESS,
        RecruitmentAccessService::MANAGE_BASIS_QUALIFICATION,
    ];
    $access->required = [];
    $response = $controller->bootstrap();
    $assert($response->getStatus() === Http::STATUS_OK, 'Authorized bootstrap access fails.');
    $assert($response->getData()['data']['applications'][0]['id'] === 7, 'Bootstrap data is not forwarded.');
    $assert($response->getData()['capabilities']['view'] === true, 'Capabilities are not forwarded.');
    $assert($access->required === ['any'], 'Authorized bootstrap skips its access gate.');
    $assert($response->getData()['basisQualificationRuns'][0]['label'] === 'BQ 09/26', 'BQ runs are not forwarded for HR.');
    $assert($response->getData()['jobResponsibilityGroups'][0]['id'] === 'ad-Stab-HR', 'Relevant job groups are not forwarded for catalog managers.');
    $response = $controller->jobResponsibilityUsers('assistance', ['ad-Stab-HR'], 'edi');
    $assert($response->getData()['users'][0]['uid'] === 'editor-user', 'Scoped job user search is not forwarded.');
    $assert(end($jobResponsibilities->calls) === ['searchUsers', ['assistance', ['ad-Stab-HR'], 'edi']], 'Job user search loses its profession or group scope.');

    $access->required = [];
    $response = $controller->inbox();
    $assert($response->getStatus() === Http::STATUS_FORBIDDEN, 'Scoped users can read the unassigned inbox.');
    $assert($inbox->calls === [], 'Denied inbox access reaches the mail service.');
    $access->required = [];
    $response = $controller->createApplicationFromInbox(21, 1, 4, 'Ari', 'Beispiel', 'ari@example.invalid');
    $assert($response->getStatus() === Http::STATUS_FORBIDDEN, 'Application editors can create from the inbox without the inbox capability.');
    $assert($inbox->calls === [], 'Creation denied by the inbox gate reaches the mail service.');
    $assert($access->required === ['manage_unassigned_inbox'], 'Inbox creation does not check its global gate first.');
    $access->manageInbox = true;
    $access->required = [];
    $response = $controller->inbox();
    $assert($response->getData()['messages'][0]['id'] === 21, 'Authorized inbox data is not forwarded.');
    $assert($access->required === ['manage_unassigned_inbox'], 'Inbox does not require its global gate.');
    $response = $controller->assignInboxMessage(21, 7, 1, ['email' => 'korrigiert@example.invalid']);
    $assert($response->getData()['applicationId'] === 7, 'Inbox assignment is not forwarded.');
    $assert($inbox->calls[1] === ['assign', [21, 7, 1, 'editor-user', ['email' => 'korrigiert@example.invalid']]], 'Inbox assignment uses the wrong arguments.');

    $access->allowed = array_values(array_diff($access->allowed, [RecruitmentAccessService::EDIT_APPLICATIONS]));
    $access->required = [];
    $inboxCallCount = count($inbox->calls);
    $response = $controller->createApplicationFromInbox(21, 1, 4, 'Ari', 'Beispiel', 'ari@example.invalid', '', '', []);
    $assert($response->getStatus() === Http::STATUS_FORBIDDEN, 'Inbox-only access can create a person and application.');
    $assert(count($inbox->calls) === $inboxCallCount, 'Denied inbox creation reaches the mail service.');
    $assert($access->required === ['manage_unassigned_inbox', RecruitmentAccessService::EDIT_APPLICATIONS], 'Inbox creation does not require both server-side capabilities.');

    $access->allowed[] = RecruitmentAccessService::EDIT_APPLICATIONS;
    $access->required = [];
    $response = $controller->createApplicationFromInbox(21, 1, 4, 'Ari', 'Beispiel', 'ari@example.invalid', '+49 30 123', '', ['email' => 'ari@example.invalid']);
    $assert($response->getStatus() === Http::STATUS_CREATED, 'Authorized inbox creation fails.');
    $assert($response->getData()['applicationId'] === 9, 'Created inbox application is not forwarded.');
    $assert(end($inbox->calls) === ['createAndAssignApplication', [21, 1, 4, 'Ari', 'Beispiel', 'ari@example.invalid', '+49 30 123', '', ['email' => 'ari@example.invalid'], 'editor-user']], 'Inbox creation loses validated request arguments.');

    $documentReview->calls = [];
    $response = $controller->attachmentDocument(31);
    $assert($response->getStatus() === Http::STATUS_OK, 'Authorized unassigned PDF cannot be displayed.');
    $assert($response->getData() === '%PDF-1.4', 'Inline PDF bytes are not forwarded unchanged.');
    $assert($documentReview->calls === [['context', [31]], ['document', [31]]], 'PDF bytes are read before or without the inbox gate.');

    $access->manageInbox = false;
    $documentReview->calls = [];
    $response = $controller->attachmentDocument(31);
    $assert($response->getStatus() === Http::STATUS_FORBIDDEN, 'Unassigned PDFs are visible without inbox access.');
    $assert($documentReview->calls === [['context', [31]]], 'A denied PDF request reads file content.');
    $response = $controller->attachmentFieldContext(31, 'previousExperience');
    $assert($response->getStatus() === Http::STATUS_UNPROCESSABLE_ENTITY, 'An unassigned PDF exposes applicant-field linking.');
    $assert($documentFieldLinks->calls === [], 'Unassigned PDF field linking reaches the domain service.');

    $documentReview->applicationId = 7;
    $documentReview->calls = [];
    $response = $controller->attachmentDocument(31);
    $assert($response->getStatus() === Http::STATUS_OK, 'Scoped dossier PDF cannot be displayed.');
    $response = $controller->createAttachmentComment(31, 'free', 'Hinweis', null, '', 'request-0001');
    $assert($response->getStatus() === Http::STATUS_FORBIDDEN, 'A reader can write document comments.');
    $assert(!array_filter($documentReview->calls, static fn(array $call): bool => $call[0] === 'addComment'), 'Denied comment write reaches persistence.');
    $response = $controller->attachmentFieldContext(31, 'previousExperience');
    $assert($response->getStatus() === Http::STATUS_FORBIDDEN, 'A dossier reader can prepare PDF field writes.');
    $assert(!array_filter($documentFieldLinks->calls, static fn(array $call): bool => $call[0] === 'fieldContext'), 'Denied PDF field context reaches the field-link service.');
    $access->allowed[] = RecruitmentAccessService::MANAGE_DOCUMENTS;
    $response = $controller->createAttachmentComment(31, 'anchored', 'Fundstelle', 2, 'right', 'request-0002');
    $assert($response->getStatus() === Http::STATUS_CREATED, 'Authorized document comment cannot be created.');
    $assert(end($documentReview->calls) === ['addComment', [31, 'anchored', 'Fundstelle', 2, 'right', 'request-0002', 'editor-user']], 'Document comment arguments are not forwarded.');
    $response = $controller->attachmentFieldContext(31, 'previousExperience');
    $assert($response->getStatus() === Http::STATUS_OK, 'Authorized PDF field context cannot be read.');
    $rectangles = [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.04]];
    $response = $controller->createAttachmentFieldLink(31, 'previousExperience', 'Assistenz', 'Zwei Jahre Assistenz', 2, $rectangles, false, 3, 'selection-0001');
    $assert($response->getStatus() === Http::STATUS_CREATED, 'Authorized PDF selection cannot be linked.');
    $assert(end($documentFieldLinks->calls)[0] === 'linkSelection', 'PDF field-link write is not forwarded.');

    $access->allowed[] = RecruitmentAccessService::MANAGE_DELEGATIONS;
    $access->required = [];
    $response = $controller->bootstrap();
    $assert($response->getStatus() === Http::STATUS_OK, 'Permission administration bootstrap fails.');
    $assert($response->getData()['delegatableCapabilities'] === ['view', 'interview'], 'Delegatable capabilities are missing.');
    $assert(array_key_exists('permissionSettings', $response->getData()), 'Permission settings are not forwarded.');

    $access->required = [];
    $response = $controller->applicationDetail(8);
    $assert($response->getData()['application']['id'] === 8, 'Application detail is not forwarded.');
    $assert($response->getData()['allowedStatuses'] === ['screening'], 'Allowed status transitions are missing.');
    $assert($access->required === [RecruitmentAccessService::VIEW], 'Application detail skips its capability gate.');

    $access->required = [];
    $useCases->calls = [];
    $response = $controller->hiringData(8);
    $assert($response->getData()['version'] === 2, 'Hiring data is not forwarded.');
    $assert($access->required === [RecruitmentAccessService::VIEW_HIRING_DATA], 'Hiring data skips its scoped read gate.');
    $assert($useCases->calls === [['hiringData', [8]]], 'Hiring data reads the wrong application.');

    $access->required = [];
    $response = $controller->templateDetail(9);
    $assert($response->getData() === ['id' => 9, 'revision' => 2], 'Template detail is not forwarded.');
    $assert($access->required === [], 'Template detail uses an unexpected object gate.');

    $writeCases = [
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->createJob(
                'Interner Titel',
                'Öffentlicher Titel',
                true,
                ['editor-user'],
                ['recruiting'],
                'engineering',
                true,
                'assistance',
            ),
            RecruitmentAccessService::MANAGE_CATALOG,
            'createJob',
            ['Interner Titel', 'Öffentlicher Titel', true, ['editor-user'], ['recruiting'], 'engineering', true, 'assistance', '', '', null, null, null, 'Berlin'],
            Http::STATUS_CREATED,
            ['id' => 12],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->setJobBasisQualificationRequired(6, true, 2),
            RecruitmentAccessService::MANAGE_BASIS_QUALIFICATION,
            'setJobBasisQualificationRequired',
            [6, true, 2, 'editor-user'],
            Http::STATUS_OK,
            ['id' => 6, 'basisQualificationRequired' => true],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->createBasisQualificationRun(
                '2026-09-07',
                '2026-09-18',
            ),
            RecruitmentAccessService::MANAGE_BASIS_QUALIFICATION,
            'createBasisQualificationRun',
            ['2026-09-07', '2026-09-18', 'editor-user'],
            Http::STATUS_CREATED,
            ['id' => 12],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->assignBasisQualification(13, 3, 4),
            RecruitmentAccessService::MANAGE_BASIS_QUALIFICATION,
            'assignBasisQualification',
            [13, 3, 4, 'editor-user'],
            Http::STATUS_CREATED,
            ['id' => 4, 'applicationId' => 13, 'result' => 'pending'],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->recordBasisQualificationResult(
                4,
                'not_suitable',
                'Einfache Bewertung',
                1,
            ),
            RecruitmentAccessService::MANAGE_BASIS_QUALIFICATION,
            'recordBasisQualificationResult',
            [4, 'not_suitable', 'Einfache Bewertung', 1, 'editor-user'],
            Http::STATUS_OK,
            ['id' => 4, 'result' => 'not_suitable'],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->createPerson(
                'Alex',
                'Beispiel',
                'alex@example.invalid',
                '',
            ),
            RecruitmentAccessService::EDIT_APPLICATIONS,
            'createPerson',
            ['Alex', 'Beispiel', 'alex@example.invalid', ''],
            Http::STATUS_CREATED,
            ['id' => 12],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->createApplication(
                4,
                5,
                'manual',
                '2026-07-27',
                'editor-user',
                20.5,
                30.0,
            ),
            RecruitmentAccessService::EDIT_APPLICATIONS,
            'createApplication',
            [4, 5, 'manual', '2026-07-27', 'editor-user', 20.5, 30.0],
            Http::STATUS_CREATED,
            ['id' => 12],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->createTemplate(
                'Erstgespräch',
                'phone',
                'Telefonisches Erstgespräch',
                'interviewer',
            ),
            RecruitmentAccessService::MANAGE_CATALOG,
            'createTemplate',
            ['Erstgespräch', 'phone', 'Telefonisches Erstgespräch', 'interviewer'],
            Http::STATUS_CREATED,
            ['id' => 12],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->createQuestion(
                6,
                'Warum möchten Sie wechseln?',
                'Kurz begründen',
                'textarea',
                true,
                10,
                [],
                'internal',
            ),
            RecruitmentAccessService::MANAGE_CATALOG,
            'createQuestion',
            [6, 'Warum möchten Sie wechseln?', 'Kurz begründen', 'textarea', true, 10, [], 'internal'],
            Http::STATUS_CREATED,
            ['id' => 12],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->updateQuestion(
                7,
                'Aktualisierte Frage',
                '',
                'text',
                false,
                20,
                [],
                'internal',
                true,
            ),
            RecruitmentAccessService::MANAGE_CATALOG,
            'updateQuestion',
            [7, 'Aktualisierte Frage', '', 'text', false, 20, [], 'internal', true],
            Http::STATUS_OK,
            ['updated' => true],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->createBubble(
                8,
                'Positiv',
                'Überzeugende Antwort',
                10,
                true,
            ),
            RecruitmentAccessService::MANAGE_CATALOG,
            'createBubble',
            [8, 'Positiv', 'Überzeugende Antwort', 10, true],
            Http::STATUS_CREATED,
            ['id' => 12],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->createInterview(9, 10),
            RecruitmentAccessService::INTERVIEW,
            'createInterview',
            [9, 10, 'editor-user'],
            Http::STATUS_CREATED,
            ['id' => 12],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->saveInterviewDraft(
                11,
                ['1' => 'Antwort'],
                2,
            ),
            RecruitmentAccessService::INTERVIEW,
            'saveInterviewDraft',
            [11, ['1' => 'Antwort'], 2],
            Http::STATUS_OK,
            ['id' => 11, 'status' => 'draft'],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->completeInterview(
                12,
                ['1' => 'Antwort'],
                3,
            ),
            RecruitmentAccessService::INTERVIEW,
            'completeInterview',
            [12, ['1' => 'Antwort'], 3],
            Http::STATUS_OK,
            ['id' => 12, 'status' => 'completed'],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->transitionStatus(
                13,
                'screening',
                4,
            ),
            RecruitmentAccessService::EDIT_APPLICATIONS,
            'transitionStatus',
            [13, 'screening', 4, 'editor-user', '', [], '', false],
            Http::STATUS_OK,
            ['id' => 13, 'status' => 'screening'],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->saveHiringData(
                13,
                ['city' => 'Beispielstadt'],
                2,
            ),
            RecruitmentAccessService::EDIT_HIRING_DATA,
            'saveHiringData',
            [13, ['city' => 'Beispielstadt'], 2, 'editor-user'],
            Http::STATUS_OK,
            ['data' => ['city' => 'Beispielstadt'], 'version' => 3],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->savePayrollData(13, ['taxId' => '123'], 2),
            RecruitmentAccessService::EDIT_PAYROLL_DATA,
            'savePayrollData',
            [13, ['taxId' => '123'], 2, 'editor-user'],
            Http::STATUS_OK,
            ['data' => ['taxId' => '123'], 'version' => 3],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->setFirstGuideAccess(13, false, 5),
            RecruitmentAccessService::MANAGE_FIRST_GUIDE_ACCESS,
            'setFirstGuideAccess',
            [13, false, 5, 'editor-user'],
            Http::STATUS_OK,
            ['id' => 13, 'firstGuideAccess' => false],
        ],
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->saveRepresentatives([], 3),
            RecruitmentAccessService::MANAGE_DELEGATIONS,
            'saveRepresentatives',
            [[], 3, 'editor-user', []],
            Http::STATUS_OK,
            ['representatives' => [], 'revision' => 4],
        ],
    ];

    foreach ($writeCases as [$invoke, $capability, $method, $arguments, $statusCode, $data]) {
        $access->required = [];
        $useCases->calls = [];
        $response = $invoke();
        $assert($response->getStatus() === $statusCode, $method . ' returns the wrong HTTP status.');
        $assert($response->getData() === $data, $method . ' does not forward the response contract.');
        $assert($access->required === [$capability], $method . ' checks the wrong capability.');
        $assert($useCases->calls === [[$method, $arguments]], $method . ' does not forward its arguments exactly.');
    }

    $access->required = [];
    $useCases->calls = [];
    $response = $controller->transitionStatus(13, 'decision_pending', 4, '', '', true);
    $assert($response->getStatus() === Http::STATUS_FORBIDDEN, 'A delegated application editor can override the status process.');
    $assert($useCases->calls === [], 'Denied status override reaches the use case or mutates data.');
    $assert($access->required === [RecruitmentAccessService::EDIT_APPLICATIONS, RecruitmentAccessService::OVERRIDE_STATUS_TRANSITIONS], 'Status override skips its dedicated server-side gate.');

    $access->allowed[] = RecruitmentAccessService::OVERRIDE_STATUS_TRANSITIONS;
    $access->required = [];
    $response = $controller->transitionStatus(13, 'decision_pending', 4, '', '', true);
    $assert($response->getStatus() === Http::STATUS_OK, 'HR cannot perform a confirmed exceptional status transition.');
    $assert($access->required === [RecruitmentAccessService::EDIT_APPLICATIONS, RecruitmentAccessService::OVERRIDE_STATUS_TRANSITIONS], 'Authorized status override uses the wrong gates.');
    $assert($useCases->calls === [['transitionStatus', [13, 'decision_pending', 4, 'editor-user', '', [], '', true]]], 'Authorized status override loses its explicit override marker.');

    $access->required = [];
    $useCases->calls = [];
    $response = $controller->basisQualificationAssignments(13);
    $assert($response->getData()[0]['applicationId'] === 13, 'BQ assignments are not forwarded.');
    $assert($access->required === [RecruitmentAccessService::MANAGE_BASIS_QUALIFICATION], 'BQ detail skips the HR-only gate.');

    $access->allowed = array_values(array_filter(
        $access->allowed,
        static fn(string $capability): bool => $capability !== RecruitmentAccessService::MANAGE_BASIS_QUALIFICATION,
    ));
    $access->required = [];
    $useCases->calls = [];
    $response = $controller->assignBasisQualification(999, 3, 1);
    $assert($response->getStatus() === Http::STATUS_FORBIDDEN, 'A non-HR user can assign a foreign application to BQ.');
    $assert($useCases->calls === [], 'Denied BQ assignment reaches the use case or mutates data.');

    $access->required = [];
    $response = $controller->saveCandidatePoolSettings(true, '2026-08', 12, 30, 0);
    $assert($response->getStatus() === Http::STATUS_FORBIDDEN, 'A user without candidate-pool management can change its settings.');
    $assert($candidatePoolSettings->calls === [], 'Denied candidate-pool settings reach persistence.');
    $assert($access->required === [RecruitmentAccessService::MANAGE_CANDIDATE_POOL], 'Candidate-pool settings skip their dedicated capability gate.');

    $access->allowed[] = RecruitmentAccessService::MANAGE_CANDIDATE_POOL;
    $access->required = [];
    $response = $controller->saveCandidatePoolSettings(true, '2026-08', 12, 30, 0);
    $assert($response->getStatus() === Http::STATUS_OK, 'HR cannot save candidate-pool settings.');
    $assert($response->getData()['revision'] === 1, 'Candidate-pool settings lose their updated revision.');
    $assert($candidatePoolSettings->calls === [['save', [true, '2026-08', 12, 30, 0]]], 'Candidate-pool settings use the wrong arguments.');

    $useCases->calls = [];
    $response = $controller->saveFirstGuideGroup('first-guides', 4);
    $assert($response->getStatus() === Http::STATUS_FORBIDDEN, 'Non-admins can change the structural first-guide group.');
    $assert($useCases->calls === [], 'Denied structural group change reaches the use case.');

    $access->isAdmin = true;
    $useCases->calls = [];
    $response = $controller->saveFirstGuideGroup('first-guides', 4);
    $assert($response->getData() === ['firstGuideGroupId' => 'first-guides', 'revision' => 5], 'First-guide group is not saved for Nextcloud admins.');
    $assert($useCases->calls === [['saveFirstGuideGroup', ['first-guides', 4, 'editor-user']]], 'First-guide group arguments are not forwarded.');
    $access->isAdmin = false;

    $response = $controller->saveResumeExtractionSettings('rules', 0);
    $assert($response->getStatus() === Http::STATUS_FORBIDDEN, 'Non-admins can change resume extraction settings.');
    $assert($resumeExtraction->calls === [], 'Denied resume extraction settings reach persistence.');
    $access->isAdmin = true;
    $response = $controller->saveResumeExtractionSettings('rules', 0);
    $assert($response->getData()['revision'] === 1, 'Resume extraction settings are not saved for Nextcloud admins.');
    $assert($resumeExtraction->calls === [['save', ['rules', 0]]], 'Resume extraction settings use the wrong arguments.');
    $access->isAdmin = false;

    foreach ([
        [new \OCA\Recruitment\Exception\AccessDeniedException('Verboten.'), Http::STATUS_FORBIDDEN],
        [new \OCA\Recruitment\Exception\NotFoundException('Nicht gefunden.'), Http::STATUS_NOT_FOUND],
        [new \OCA\Recruitment\Exception\ConflictException('Konflikt.'), Http::STATUS_CONFLICT],
        [new \OCA\Recruitment\Exception\ValidationException('Ungültig.'), Http::STATUS_UNPROCESSABLE_ENTITY],
    ] as [$error, $statusCode]) {
        $useCases->failure = $error;
        $response = $controller->bootstrap();
        $assert($response->getStatus() === $statusCode, $error::class . ' receives the wrong status.');
        $assert($response->getData() === ['message' => $error->getMessage()], $error::class . ' loses its safe message.');
    }

    $useCases->failure = new \RuntimeException('Internes Detail');
    $response = $controller->bootstrap();
    $assert($response->getStatus() === Http::STATUS_INTERNAL_SERVER_ERROR, 'Unexpected failures receive the wrong status.');
    $assert($response->getData() === ['message' => 'Die Anfrage konnte technisch nicht verarbeitet werden.'], 'Internal details leak to the response.');
    $assert($logger->errors[0][0] === 'AD-Recruitment-Anfrage fehlgeschlagen.', 'Unexpected failures are not logged safely.');
    $assert($logger->errors[0][1] === ['exceptionClass' => RuntimeException::class], 'The log contains more than the exception class.');

    echo "AD Recruitment API controller execution tests passed\n";
}
