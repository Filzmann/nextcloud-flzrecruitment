<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use DateTimeImmutable;
use OCA\Recruitment\Contract\MailAttachmentStorage;
use OCA\Recruitment\Contract\MailInboxStore;
use OCA\Recruitment\Contract\PdfTextExtractor;
use OCA\Recruitment\Exception\ValidationException;

/** Fachgrenze für unveränderlichen, wiederholbaren Mailimport und versionierte Zuordnung. */
final class MailInboxService {
    public const STATE_NEW = 'new';
    public const STATE_ASSIGNED = 'assigned';
    public const STATE_UNCLEAR = 'unclear';
    public const STATE_ERROR = 'error';
    public const STATE_IGNORED = 'ignored';
    public const MAX_ATTACHMENTS = 5;
    public const MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;
    public const MAX_TOTAL_ATTACHMENT_BYTES = 50 * 1024 * 1024;
    public const MAX_BODY_BYTES = 2 * 1024 * 1024;
    private const ASSIGNABLE_SUGGESTIONS = [
        'salutation' => 'salutation',
        'title' => 'title',
        'email' => 'privateEmail',
        'phone' => 'privatePhone',
        'availableFrom' => 'plannedStartDate',
        'location' => 'city',
    ];

    private HiringMasterDataService $hiringMasterData;

    public function __construct(
        private ApplicationMailFieldExtractor $extractor,
        private MailInboxStore $store,
        private MailAttachmentStorage $attachments,
        private PdfTextExtractor $pdfTextExtractor,
        ?HiringMasterDataService $hiringMasterData = null,
    ) { $this->hiringMasterData = $hiringMasterData ?? new HiringMasterDataService(); }

    /**
     * @param array<string,mixed> $mail
     * @return array{imported:bool,message:array<string,mixed>}
     */
    public function import(array $mail, string $actorUid): array {
        $normalized = $this->normalize($mail);
        $mailboxId = $this->store->ensureMailbox($normalized['mailbox']);
        $contentHash = $this->contentHash($normalized);
        $existing = $this->store->findMessageByIdentity($mailboxId, $normalized['externalMessageId'], $contentHash);
        if ($existing !== null) {
            return ['imported' => false, 'message' => $existing];
        }

        $resumeTexts = [];
        foreach ($normalized['attachments'] as $attachment) {
            $resumeText = $attachment['extractedText'];
            if ($resumeText === '') $resumeText = $this->pdfTextExtractor->extract($attachment['content']);
            if ($resumeText !== '') $resumeTexts[] = $resumeText;
        }
        $resumeText = implode("\n", $resumeTexts);
        $suggestions = $this->extractor->extract($normalized['senderAddress'], $normalized['bodyText'], $resumeText, $normalized['senderName']);
        $messageId = $this->store->createInboxMessage([
            'mailboxId' => $mailboxId,
            'externalMessageId' => $normalized['externalMessageId'],
            'contentHash' => $contentHash,
            'state' => self::STATE_NEW,
            'senderAddress' => $normalized['senderAddress'],
            'recipients' => $normalized['recipients'],
            'subject' => $normalized['subject'],
            'receivedAt' => $normalized['receivedAt'],
            'bodyText' => $normalized['bodyText'],
            'fieldSuggestions' => $suggestions,
            'actorUid' => $actorUid,
        ]);

        try {
            foreach ($normalized['attachments'] as $attachment) {
                $path = $this->attachments->store($messageId, $attachment['contentHash'], $attachment['content']);
                $this->store->addInboxAttachment($messageId, [
                    'originalName' => $attachment['originalName'],
                    'storedName' => $attachment['contentHash'] . '.pdf',
                    'mimeType' => 'application/pdf',
                    'sizeBytes' => strlen($attachment['content']),
                    'contentHash' => $attachment['contentHash'],
                    'storagePath' => $path,
                ]);
            }
        } catch (\Throwable $error) {
            $this->store->markInboxImportError($messageId, $actorUid);
            throw $error;
        }

        return ['imported' => true, 'message' => $this->store->inboxMessage($messageId)];
    }

    /** @return list<array<string,mixed>> */
    public function messages(): array { return $this->store->inboxMessages(); }
    /** @return list<array<string,mixed>> */
    public function messagesForApplication(int $applicationId): array {
        if (!$this->store->inboxApplicationExists($applicationId)) {
            throw new ValidationException('Die ausgewählte Bewerbung existiert nicht.');
        }
        return $this->store->inboxMessagesForApplication($applicationId);
    }
    /** @return array<string,mixed> */
    public function message(int $messageId): array { return $this->store->inboxMessage($messageId); }

    /** @return array<string,mixed> */
    public function assign(
        int $messageId,
        int $applicationId,
        int $expectedVersion,
        string $actorUid,
        array $acceptedSuggestions = [],
    ): array {
        if (!$this->store->inboxApplicationExists($applicationId)) {
            throw new ValidationException('Die ausgewählte Bewerbung existiert nicht.');
        }
        $message = $this->store->inboxMessage($messageId);
        if (!in_array((string)$message['state'], [self::STATE_NEW, self::STATE_UNCLEAR, self::STATE_ASSIGNED], true)) {
            throw new ValidationException('Diese Nachricht kann in ihrem aktuellen Zustand nicht zugeordnet werden.');
        }
        $hiringDefaults = $this->acceptedHiringDefaults(
            (array)($message['fieldSuggestions'] ?? []),
            $acceptedSuggestions,
        );
        return $this->store->assignInboxMessage(
            $messageId,
            $applicationId,
            $expectedVersion,
            $actorUid,
            $hiringDefaults,
        );
    }

    /**
     * @param array<string,mixed> $availableSuggestions
     * @param array<string,mixed> $acceptedSuggestions
     * @return array<string,string|float|null>
     */
    private function acceptedHiringDefaults(array $availableSuggestions, array $acceptedSuggestions): array {
        $confirmed = [];
        foreach ($acceptedSuggestions as $key => $value) {
            if (!is_string($key)
                || !isset(self::ASSIGNABLE_SUGGESTIONS[$key])
                || !isset($availableSuggestions[$key]['value'])
                || !is_scalar($value)) {
                throw new ValidationException('Ein bestätigter Mailvorschlag ist ungültig.');
            }
            $normalized = trim((string)$value);
            if ($normalized === '' || strlen($normalized) > 255) {
                throw new ValidationException('Ein bestätigter Mailvorschlag ist ungültig.');
            }
            $confirmed[$key] = ['value' => $normalized];
        }

        $defaults = $this->hiringMasterData->mailDefaults($confirmed);
        foreach ($confirmed as $key => $_suggestion) {
            if (!array_key_exists(self::ASSIGNABLE_SUGGESTIONS[$key], $defaults)) {
                throw new ValidationException('Ein bestätigter Mailvorschlag hat ein ungültiges Format.');
            }
        }
        return $defaults;
    }

    /** @return array<string,mixed> */
    public function ignore(int $messageId, int $expectedVersion, string $actorUid): array {
        $message = $this->store->inboxMessage($messageId);
        if (!in_array((string)$message['state'], [self::STATE_NEW, self::STATE_UNCLEAR, self::STATE_ERROR], true)) {
            throw new ValidationException('Diese Nachricht kann in ihrem aktuellen Zustand nicht ignoriert werden.');
        }
        return $this->store->ignoreInboxMessage($messageId, $expectedVersion, $actorUid);
    }

    /** @param array<string,mixed> $mail
     *  @return array<string,mixed>
     */
    private function normalize(array $mail): array {
        $mailbox = is_array($mail['mailbox'] ?? null) ? $mail['mailbox'] : [];
        $technicalKey = trim((string)($mailbox['technicalKey'] ?? ''));
        $mailboxAddress = strtolower(trim((string)($mailbox['address'] ?? '')));
        $senderAddress = strtolower(trim((string)($mail['senderAddress'] ?? '')));
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $technicalKey) !== 1
            || filter_var($mailboxAddress, FILTER_VALIDATE_EMAIL) === false
            || filter_var($senderAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Postfach und Absender müssen gültig angegeben sein.');
        }
        $body = str_replace(["\r\n", "\r"], "\n", (string)($mail['bodyText'] ?? ''));
        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new ValidationException('Der Mailtext überschreitet die zulässige Größe.');
        }
        try {
            $receivedAt = new DateTimeImmutable((string)($mail['receivedAt'] ?? ''));
        } catch (\Throwable) {
            throw new ValidationException('Das Empfangsdatum ist ungültig.');
        }
        $recipients = [];
        foreach (is_array($mail['recipients'] ?? null) ? $mail['recipients'] : [] as $recipient) {
            $recipient = strtolower(trim((string)$recipient));
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw new ValidationException('Eine Empfängeradresse ist ungültig.');
            }
            $recipients[$recipient] = true;
        }
        $attachments = is_array($mail['attachments'] ?? null) ? array_values($mail['attachments']) : [];
        if (count($attachments) > self::MAX_ATTACHMENTS) {
            throw new ValidationException('Eine Bewerbung darf höchstens fünf Anhänge enthalten.');
        }
        $validatedAttachments = [];
        $total = 0;
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) throw new ValidationException('Ein Anhang ist ungültig.');
            $content = (string)($attachment['content'] ?? '');
            $size = strlen($content);
            $total += $size;
            if ((string)($attachment['mimeType'] ?? '') !== 'application/pdf'
                || !str_starts_with($content, '%PDF-')
                || $size > self::MAX_ATTACHMENT_BYTES
                || $total > self::MAX_TOTAL_ATTACHMENT_BYTES) {
                throw new ValidationException('Es sind ausschließlich PDF-Anhänge innerhalb der Größenbegrenzung zulässig.');
            }
            $validatedAttachments[] = [
                'originalName' => substr(trim((string)($attachment['originalName'] ?? 'Anhang.pdf')), 0, 255),
                'content' => $content,
                'contentHash' => hash('sha256', $content),
                'extractedText' => substr(trim((string)($attachment['extractedText'] ?? '')), 0, self::MAX_BODY_BYTES),
            ];
        }
        $externalMessageId = trim((string)($mail['externalMessageId'] ?? ''));
        return [
            'mailbox' => ['technicalKey' => $technicalKey, 'label' => trim((string)($mailbox['label'] ?? $technicalKey)), 'address' => $mailboxAddress],
            'externalMessageId' => $externalMessageId === '' ? null : substr($externalMessageId, 0, 255),
            'senderAddress' => $senderAddress,
            'senderName' => substr(trim((string)($mail['senderName'] ?? '')), 0, 255),
            'recipients' => array_keys($recipients),
            'subject' => substr(trim((string)($mail['subject'] ?? '')), 0, 998),
            'receivedAt' => $receivedAt,
            'bodyText' => $body,
            'attachments' => $validatedAttachments,
        ];
    }

    /** @param array<string,mixed> $mail */
    private function contentHash(array $mail): string {
        $identity = [
            'senderAddress' => $mail['senderAddress'],
            'recipients' => $mail['recipients'],
            'subject' => $mail['subject'],
            'receivedAt' => $mail['receivedAt']->format(DATE_ATOM),
            'bodyText' => $mail['bodyText'],
            'attachments' => array_column($mail['attachments'], 'contentHash'),
        ];
        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
