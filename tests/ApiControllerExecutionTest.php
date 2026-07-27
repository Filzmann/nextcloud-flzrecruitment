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
    final class RecruitmentAccessService {
        public const VIEW = 'view';
        public const MANAGE_CATALOG = 'manage_catalog';
        public const EDIT_APPLICATIONS = 'edit_applications';
        public const INTERVIEW = 'interview';
        /** @var list<string> */
        public array $allowed = [];
        /** @var list<string> */
        public array $required = [];
        public function require(string $capability): void {
            $this->required[] = $capability;
            if (!in_array($capability, $this->allowed, true)) {
                throw new \OCA\Recruitment\Exception\AccessDeniedException('Keine Berechtigung.');
            }
        }
        public function capabilities(): array {
            return [
                self::VIEW => in_array(self::VIEW, $this->allowed, true),
                self::MANAGE_CATALOG => in_array(self::MANAGE_CATALOG, $this->allowed, true),
                self::EDIT_APPLICATIONS => in_array(self::EDIT_APPLICATIONS, $this->allowed, true),
                self::INTERVIEW => in_array(self::INTERVIEW, $this->allowed, true),
            ];
        }
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
                default => 12,
            };
        }
        private function record(string $method, array $arguments): void {
            if ($this->failure !== null) throw $this->failure;
            $this->calls[] = [$method, $arguments];
        }
    }
}

namespace {
    require __DIR__ . '/../lib/Exception/AccessDeniedException.php';
    require __DIR__ . '/../lib/Exception/ConflictException.php';
    require __DIR__ . '/../lib/Exception/NotFoundException.php';
    require __DIR__ . '/../lib/Exception/ValidationException.php';
    require __DIR__ . '/../lib/Controller/ApiController.php';

    use OCA\Recruitment\Controller\ApiController;
    use OCA\Recruitment\Service\RecruitmentAccessService;
    use OCA\Recruitment\Service\RecruitmentUseCaseService;
    use OCP\AppFramework\Http;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    $request = new class implements \OCP\IRequest {};
    $access = new RecruitmentAccessService();
    $useCases = new RecruitmentUseCaseService();
    $logger = new class implements \Psr\Log\LoggerInterface {
        public array $errors = [];
        public function error(string $message, array $context = []): void { $this->errors[] = [$message, $context]; }
    };
    $controller = new ApiController(
        $request,
        $access,
        $useCases,
        $logger,
    );

    $response = $controller->bootstrap();
    $assert($response->getStatus() === Http::STATUS_FORBIDDEN, 'Anonymous API access is not rejected.');
    $assert($useCases->calls === [], 'Denied access reaches application services.');
    $assert($access->required === [RecruitmentAccessService::VIEW], 'Bootstrap does not require view access.');

    $access->allowed = [
        RecruitmentAccessService::VIEW,
        RecruitmentAccessService::MANAGE_CATALOG,
        RecruitmentAccessService::EDIT_APPLICATIONS,
        RecruitmentAccessService::INTERVIEW,
    ];
    $access->required = [];
    $response = $controller->bootstrap();
    $assert($response->getStatus() === Http::STATUS_OK, 'Authorized bootstrap access fails.');
    $assert($response->getData()['data']['applications'][0]['id'] === 7, 'Bootstrap data is not forwarded.');
    $assert($response->getData()['capabilities']['view'] === true, 'Capabilities are not forwarded.');
    $assert($access->required === [RecruitmentAccessService::VIEW], 'Authorized bootstrap skips its capability gate.');

    $access->required = [];
    $response = $controller->applicationDetail(8);
    $assert($response->getData()['application']['id'] === 8, 'Application detail is not forwarded.');
    $assert($response->getData()['allowedStatuses'] === ['screening'], 'Allowed status transitions are missing.');
    $assert($access->required === [RecruitmentAccessService::VIEW], 'Application detail skips its capability gate.');

    $access->required = [];
    $response = $controller->templateDetail(9);
    $assert($response->getData() === ['id' => 9, 'revision' => 2], 'Template detail is not forwarded.');
    $assert($access->required === [RecruitmentAccessService::VIEW], 'Template detail skips its capability gate.');

    $writeCases = [
        [
            static fn (): \OCP\AppFramework\Http\JSONResponse => $controller->createJob(
                'Interner Titel',
                'Öffentlicher Titel',
                true,
                ['editor-user'],
                ['recruiting'],
                'engineering',
            ),
            RecruitmentAccessService::MANAGE_CATALOG,
            'createJob',
            ['Interner Titel', 'Öffentlicher Titel', true, ['editor-user'], ['recruiting'], 'engineering'],
            Http::STATUS_CREATED,
            ['id' => 12],
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
            ),
            RecruitmentAccessService::EDIT_APPLICATIONS,
            'createApplication',
            [4, 5, 'manual', '2026-07-27', 'editor-user'],
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
            [13, 'screening', 4, 'editor-user'],
            Http::STATUS_OK,
            ['id' => 13, 'status' => 'screening'],
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
