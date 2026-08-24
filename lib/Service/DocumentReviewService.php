<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\Recruitment\Contract\DocumentReviewStore;
use OCA\Recruitment\Contract\MailAttachmentStorage;
use OCA\Recruitment\Exception\ValidationException;

/** Liest unveränderliche PDF-Originale und verwaltet getrennte append-only Review-Kommentare. */
final class DocumentReviewService {
    private const COMMENT_KINDS = ['anchored', 'free'];
    private const ANCHOR_COLUMNS = ['left', 'right', 'full'];

    public function __construct(
        private DocumentReviewStore $store,
        private MailAttachmentStorage $storage,
    ) {}

    /** @return array<string,mixed> */
    public function context(int $attachmentId): array {
        return $this->store->attachmentContext($attachmentId);
    }

    /** @return array{content:string,originalName:string,mimeType:string,contentHash:string} */
    public function document(int $attachmentId): array {
        $attachment = $this->store->attachmentContext($attachmentId);
        if ((string)$attachment['mimeType'] !== 'application/pdf') {
            throw new ValidationException('Nur PDF-Dokumente können angezeigt werden.');
        }
        $content = $this->storage->read((string)$attachment['storagePath']);
        if (!hash_equals((string)$attachment['contentHash'], hash('sha256', $content))) {
            throw new \RuntimeException('Die Integrität des Dokuments konnte nicht bestätigt werden.');
        }
        return [
            'content' => $content,
            'originalName' => (string)$attachment['originalName'],
            'mimeType' => 'application/pdf',
            'contentHash' => (string)$attachment['contentHash'],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function comments(int $attachmentId): array {
        $this->store->attachmentContext($attachmentId);
        return $this->store->documentComments($attachmentId);
    }

    /** @return array<string,mixed> */
    public function addComment(
        int $attachmentId,
        string $kind,
        string $body,
        ?int $pageNumber,
        string $anchorColumn,
        string $clientKey,
        string $actorUid,
    ): array {
        $this->store->attachmentContext($attachmentId);
        $kind = trim($kind);
        $body = trim($body);
        $anchorColumn = trim($anchorColumn);
        $clientKey = trim($clientKey);
        if (!in_array($kind, self::COMMENT_KINDS, true) || $body === '' || strlen($body) > 4000) {
            throw new ValidationException('Der Dokumentkommentar ist ungültig.');
        }
        if (preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $clientKey) !== 1) {
            throw new ValidationException('Der Kommentarauftrag besitzt keine gültige Kennung.');
        }
        if ($kind === 'anchored') {
            if ($pageNumber === null || $pageNumber < 1 || $pageNumber > 2000
                || !in_array($anchorColumn, self::ANCHOR_COLUMNS, true)) {
                throw new ValidationException('Die Fundstelle des Kommentars ist ungültig.');
            }
        } else {
            $pageNumber = null;
            $anchorColumn = '';
        }

        $existing = $this->store->findDocumentCommentByClientKey($attachmentId, $clientKey);
        if ($existing !== null) return $existing;
        $id = $this->store->createDocumentComment([
            'attachmentId' => $attachmentId,
            'kind' => $kind,
            'body' => $body,
            'pageNumber' => $pageNumber,
            'anchorColumn' => $anchorColumn,
            'actorUid' => $actorUid,
            'clientKey' => $clientKey,
        ]);
        return $this->store->documentComment($id);
    }
}
