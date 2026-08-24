<?php

declare(strict_types=1);

use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;
use function RecruitmentTests\assertTrue;

TestRunner::test('document review reads an immutable PDF and appends anchored or free comments idempotently', static function (): void {
    $root = dirname(__DIR__);
    foreach (['lib/Contract/DocumentReviewStore.php', 'lib/Service/DocumentReviewService.php'] as $file) {
        assertTrue(is_file($root . '/' . $file), "Document review component is missing: {$file}");
    }

    $store = new class implements \OCA\Recruitment\Contract\DocumentReviewStore {
        public array $comments = [];
        public function attachmentContext(int $id): array { return ['id' => $id, 'storagePath' => 'mail-inbox/message-1/' . str_repeat('a', 64) . '.pdf', 'contentHash' => hash('sha256', "%PDF-1.4\noriginal"), 'originalName' => 'Lebenslauf.pdf', 'mimeType' => 'application/pdf', 'applicationId' => 7]; }
        public function documentComments(int $attachmentId): array { return array_values(array_filter($this->comments, static fn(array $item): bool => $item['attachmentId'] === $attachmentId)); }
        public function findDocumentCommentByClientKey(int $attachmentId, string $clientKey): ?array { foreach ($this->comments as $item) if ($item['attachmentId'] === $attachmentId && $item['clientKey'] === $clientKey) return $item; return null; }
        public function createDocumentComment(array $comment): int { $id = count($this->comments) + 1; $this->comments[$id] = ['id' => $id, 'version' => 1] + $comment; return $id; }
        public function documentComment(int $id): array { return $this->comments[$id]; }
    };
    $storage = new class implements \OCA\Recruitment\Contract\MailAttachmentStorage {
        public function store(int $messageId, string $contentHash, string $content): string { return ''; }
        public function read(string $storagePath): string { return "%PDF-1.4\noriginal"; }
    };
    $service = new \OCA\Recruitment\Service\DocumentReviewService($store, $storage);

    $document = $service->document(3);
    assertSame("%PDF-1.4\noriginal", $document['content']);
    assertSame('Lebenslauf.pdf', $document['originalName']);

    $anchored = $service->addComment(3, 'anchored', 'Relevante Erfahrung.', 2, 'left', 'request-0001', 'hr-user');
    $duplicate = $service->addComment(3, 'anchored', 'Relevante Erfahrung.', 2, 'left', 'request-0001', 'hr-user');
    $free = $service->addComment(3, 'free', 'Gesamteindruck notiert.', null, '', 'request-0002', 'hr-user');
    assertSame($anchored['id'], $duplicate['id']);
    assertSame('left', $anchored['anchorColumn']);
    assertSame(null, $free['pageNumber']);
    assertSame(2, count($store->comments));
    assertSame(2, count($service->comments(3)));
});

TestRunner::test('document review rejects invalid anchors and empty text without persistence', static function (): void {
    $root = dirname(__DIR__);
    if (!is_file($root . '/lib/Service/DocumentReviewService.php')) return;
    $store = new class implements \OCA\Recruitment\Contract\DocumentReviewStore {
        public array $comments = [];
        public function attachmentContext(int $id): array { return ['id' => $id, 'storagePath' => 'mail-inbox/message-1/' . str_repeat('a', 64) . '.pdf', 'contentHash' => hash('sha256', '%PDF-1.4'), 'originalName' => 'Dokument.pdf', 'mimeType' => 'application/pdf', 'applicationId' => 7]; }
        public function documentComments(int $attachmentId): array { return []; }
        public function findDocumentCommentByClientKey(int $attachmentId, string $clientKey): ?array { return null; }
        public function createDocumentComment(array $comment): int { $this->comments[] = $comment; return count($this->comments); }
        public function documentComment(int $id): array { return $this->comments[$id - 1]; }
    };
    $storage = new class implements \OCA\Recruitment\Contract\MailAttachmentStorage {
        public function store(int $messageId, string $contentHash, string $content): string { return ''; }
        public function read(string $storagePath): string { return '%PDF-1.4'; }
    };
    $service = new \OCA\Recruitment\Service\DocumentReviewService($store, $storage);
    assertThrows(static fn() => $service->addComment(3, 'anchored', 'Text', 0, 'left', 'request-0003', 'hr-user'), \OCA\Recruitment\Exception\ValidationException::class);
    assertThrows(static fn() => $service->addComment(3, 'anchored', 'Text', 2, 'middle', 'request-0004', 'hr-user'), \OCA\Recruitment\Exception\ValidationException::class);
    assertThrows(static fn() => $service->addComment(3, 'free', ' ', null, '', 'request-0005', 'hr-user'), \OCA\Recruitment\Exception\ValidationException::class);
    assertSame([], $store->comments);
});
