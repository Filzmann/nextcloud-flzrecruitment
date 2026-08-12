<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;
use function RecruitmentTests\assertTrue;

TestRunner::test('document selections link to applicant fields and preserve free comments idempotently', static function (): void {
    $root = dirname(__DIR__);
    foreach (['lib/Contract/DocumentFieldLinkStore.php', 'lib/Service/DocumentFieldLinkService.php'] as $file) {
        assertTrue(is_file($root . '/' . $file), "Document field-link component is missing: {$file}");
    }

    $store = new class implements \OCA\Recruitment\Contract\DocumentFieldLinkStore {
        public array $links = [];
        public array $fields = [
            'previousExperience' => ['value' => '', 'version' => 4],
            'germanLanguageLevel' => ['value' => 'B1', 'version' => 4],
            'birthDate' => ['value' => '', 'version' => 2],
            'birthPlace' => ['value' => '', 'version' => 2],
            'freeComment' => ['value' => 'Vorhandener Kommentar', 'version' => 4],
        ];
        public function attachmentContext(int $id): array { return ['id' => $id, 'applicationId' => 7]; }
        public function documentFieldState(int $applicationId): array { return ['applicationId' => $applicationId, 'fields' => $this->fields]; }
        public function documentFieldLinks(int $attachmentId): array { return array_values($this->links); }
        public function findDocumentFieldLinkByClientKey(int $attachmentId, string $clientKey): ?array {
            foreach ($this->links as $link) if ($link['attachmentId'] === $attachmentId && $link['clientKey'] === $clientKey) return $link;
            return null;
        }
        public function createDocumentFieldLinkAndApply(array $link, string $resultValue, int $expectedVersion): int {
            $field = $link['targetField'];
            if ($this->fields[$field]['version'] !== $expectedVersion) throw new \OCA\Recruitment\Exception\ConflictException();
            $this->fields[$field] = ['value' => $resultValue, 'version' => $expectedVersion + 1];
            $id = count($this->links) + 1;
            $this->links[$id] = ['id' => $id, 'resultValue' => $resultValue, 'createdAt' => '2026-08-02 12:00:00'] + $link;
            return $id;
        }
        public function documentFieldLink(int $id): array { return $this->links[$id]; }
    };
    $service = new \OCA\Recruitment\Service\DocumentFieldLinkService($store);
    $rectangles = [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.04]];

    $free = $service->linkSelection(3, 'freeComment', 'Ausgewählter Hinweis', 'Neuer Hinweis', 2, $rectangles, false, 4, 'selection-0001', 'hr-user');
    assertSame("Vorhandener Kommentar\nNeuer Hinweis", $free['resultValue']);
    $retry = $service->linkSelection(3, 'freeComment', 'Ausgewählter Hinweis', 'Neuer Hinweis', 2, $rectangles, false, 4, 'selection-0001', 'hr-user');
    assertSame($free['id'], $retry['id']);
    assertSame("Vorhandener Kommentar\nNeuer Hinweis", $store->fields['freeComment']['value']);

    assertThrows(
        static fn() => $service->linkSelection(3, 'germanLanguageLevel', 'Deutsch B2', 'B2', 1, $rectangles, false, 4, 'selection-0002', 'hr-user'),
        \OCA\Recruitment\Exception\ConflictException::class,
    );
    $replaced = $service->linkSelection(3, 'germanLanguageLevel', 'Deutsch B2', 'B2', 1, $rectangles, true, 4, 'selection-0003', 'hr-user');
    assertSame('B2', $replaced['resultValue']);
    assertSame(2, count($store->links));
});

TestRunner::test('document field links reject unassigned files, invalid targets, values and coordinates without mutation', static function (): void {
    $root = dirname(__DIR__);
    if (!is_file($root . '/lib/Service/DocumentFieldLinkService.php')) return;

    $store = new class implements \OCA\Recruitment\Contract\DocumentFieldLinkStore {
        public array $links = [];
        public ?int $applicationId = null;
        public function attachmentContext(int $id): array { return ['id' => $id, 'applicationId' => $this->applicationId]; }
        public function documentFieldState(int $applicationId): array { return ['applicationId' => $applicationId, 'fields' => [
            'previousExperience' => ['value' => '', 'version' => 2],
            'germanLanguageLevel' => ['value' => '', 'version' => 2],
            'birthDate' => ['value' => '', 'version' => 1],
            'birthPlace' => ['value' => '', 'version' => 1],
            'freeComment' => ['value' => '', 'version' => 2],
        ]]; }
        public function documentFieldLinks(int $attachmentId): array { return []; }
        public function findDocumentFieldLinkByClientKey(int $attachmentId, string $clientKey): ?array { return null; }
        public function createDocumentFieldLinkAndApply(array $link, string $resultValue, int $expectedVersion): int { $this->links[] = $link; return 1; }
        public function documentFieldLink(int $id): array { return $this->links[$id - 1]; }
    };
    $service = new \OCA\Recruitment\Service\DocumentFieldLinkService($store);
    $rectangles = [['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.04]];
    assertThrows(
        static fn() => $service->linkSelection(3, 'birthDate', '01.02.1990', '1990-02-01', 1, $rectangles, false, 1, 'selection-0004', 'hr-user'),
        \OCA\Recruitment\Exception\ValidationException::class,
    );
    $store->applicationId = 7;
    assertThrows(
        static fn() => $service->linkSelection(3, 'unknown', 'Text', 'Wert', 1, $rectangles, false, 2, 'selection-0005', 'hr-user'),
        \OCA\Recruitment\Exception\ValidationException::class,
    );
    assertThrows(
        static fn() => $service->linkSelection(3, 'germanLanguageLevel', 'Deutsch C3', 'C3', 1, $rectangles, false, 2, 'selection-0006', 'hr-user'),
        \OCA\Recruitment\Exception\ValidationException::class,
    );
    assertThrows(
        static fn() => $service->linkSelection(3, 'birthDate', '31.02.1990', '1990-02-31', 1, $rectangles, false, 1, 'selection-0007', 'hr-user'),
        \OCA\Recruitment\Exception\ValidationException::class,
    );
    assertThrows(
        static fn() => $service->linkSelection(3, 'previousExperience', 'Text', 'Wert', 1, [['x' => 0.9, 'y' => 0.2, 'width' => 0.2, 'height' => 0.1]], false, 2, 'selection-0008', 'hr-user'),
        \OCA\Recruitment\Exception\ValidationException::class,
    );
    assertThrows(
        static fn() => $service->linkSelection(3, 'previousExperience', '', 'Wert', 1, [], false, 2, 'selection-0009', 'hr-user'),
        \OCA\Recruitment\Exception\ValidationException::class,
    );
    assertThrows(
        static fn() => $service->linkSelection(3, 'previousExperience', 'Text', 'Wert', 1, $rectangles, false, 1, 'selection-0010', 'hr-user'),
        \OCA\Recruitment\Exception\ConflictException::class,
    );
    assertSame([], $store->links);
});
