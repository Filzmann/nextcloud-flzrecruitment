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
    private const HIRING_SUGGESTIONS = [
        'salutation' => 'salutation',
        'title' => 'title',
        'email' => 'privateEmail',
        'phone' => 'privatePhone',
        'availableFrom' => 'plannedStartDate',
        'location' => 'city',
    ];
    private const APPLICATION_SUGGESTIONS = [
        'previousExperience' => 'previousExperience',
        'germanLanguageLevel' => 'germanLanguageLevel',
        'desiredWeeklyHours' => 'desiredWeeklyHours',
    ];

    private HiringMasterDataService $hiringMasterData;
    private ApplicationFieldValueService $applicationFieldValues;
    private DesiredWeeklyHoursService $desiredWeeklyHours;
    private RecruitmentService $recruitment;

    public function __construct(
        private ApplicationMailFieldExtractor $extractor,
        private MailInboxStore $store,
        private MailAttachmentStorage $attachments,
        private PdfTextExtractor $pdfTextExtractor,
        ?HiringMasterDataService $hiringMasterData = null,
        ?ApplicationFieldValueService $applicationFieldValues = null,
        ?DesiredWeeklyHoursService $desiredWeeklyHours = null,
        ?RecruitmentService $recruitment = null,
    ) {
        $this->hiringMasterData = $hiringMasterData ?? new HiringMasterDataService();
        $this->applicationFieldValues = $applicationFieldValues ?? new ApplicationFieldValueService();
        $this->desiredWeeklyHours = $desiredWeeklyHours ?? new DesiredWeeklyHoursService();
        $this->recruitment = $recruitment ?? new RecruitmentService($this->desiredWeeklyHours);
    }

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
        [$hiringDefaults, $applicationDefaults] = $this->acceptedDefaults(
            (array)($message['fieldSuggestions'] ?? []),
            $acceptedSuggestions,
        );
        return $this->store->assignInboxMessage(
            $messageId,
            $applicationId,
            $expectedVersion,
            $actorUid,
            $hiringDefaults,
            $applicationDefaults,
        );
    }

    /** @return array{personId:int,applicationId:int,message:array<string,mixed>} */
    public function createAndAssignApplication(
        int $messageId,
        int $expectedVersion,
        int $jobId,
        string $givenName,
        string $familyName,
        string $email,
        string $phone,
        string $assigneeUid,
        array $acceptedSuggestions,
        string $actorUid,
    ): array {
        if (!$this->store->inboxJobExists($jobId)) {
            throw new ValidationException('Die ausgewählte Stelle ist nicht verfügbar.');
        }
        $message = $this->store->inboxMessage($messageId);
        if (!in_array((string)$message['state'], [self::STATE_NEW, self::STATE_UNCLEAR], true)) {
            throw new ValidationException('Aus dieser Nachricht kann keine neue Bewerbung angelegt werden.');
        }
        [$hiringDefaults, $applicationDefaults] = $this->acceptedDefaults(
            (array)($message['fieldSuggestions'] ?? []),
            $acceptedSuggestions,
        );
        $person = $this->recruitment->personData($givenName, $familyName, $email, $phone);
        try {
            $receivedAt = $message['receivedAt'] ?? null;
            if (!$receivedAt instanceof \DateTimeInterface
                && (!is_string($receivedAt) || trim($receivedAt) === '')) {
                throw new ValidationException('Das Empfangsdatum der Nachricht ist ungültig.');
            }
            $receivedOn = $receivedAt instanceof \DateTimeInterface
                ? $receivedAt->format('Y-m-d')
                : (new DateTimeImmutable((string)$receivedAt))->format('Y-m-d');
        } catch (\Throwable) {
            throw new ValidationException('Das Empfangsdatum der Nachricht ist ungültig.');
        }
        $application = $this->recruitment->applicationData(
            $jobId,
            'email_import',
            $receivedOn,
            $assigneeUid,
            isset($applicationDefaults['desiredWeeklyHours']) ? (float)$applicationDefaults['desiredWeeklyHours'] : null,
            isset($applicationDefaults['desiredWeeklyHoursMax']) ? (float)$applicationDefaults['desiredWeeklyHoursMax'] : null,
        );
        return $this->store->createAndAssignInboxApplication(
            $messageId,
            $expectedVersion,
            $actorUid,
            $person,
            $application,
            $hiringDefaults,
            $applicationDefaults,
        );
    }

    /**
     * @param array<string,mixed> $availableSuggestions
     * @param array<string,mixed> $acceptedSuggestions
     * @return array{0:array<string,string|float|null>,1:array<string,mixed>}
     */
    private function acceptedDefaults(array $availableSuggestions, array $acceptedSuggestions): array {
        $confirmedHiring = [];
        $applicationDefaults = [];
        foreach ($acceptedSuggestions as $key => $value) {
            if (!is_string($key)
                || !isset($availableSuggestions[$key]['value'])
                || !is_scalar($value)) {
                throw new ValidationException('Ein bestätigter Mailvorschlag ist ungültig.');
            }
            $normalized = trim((string)$value);
            if ($normalized === '') {
                throw new ValidationException('Ein bestätigter Mailvorschlag ist ungültig.');
            }
            if (isset(self::HIRING_SUGGESTIONS[$key])) {
                if (strlen($normalized) > 255) throw new ValidationException('Ein bestätigter Mailvorschlag ist ungültig.');
                $confirmedHiring[$key] = ['value' => $normalized];
                continue;
            }
            if (isset(self::APPLICATION_SUGGESTIONS[$key])) {
                $field = self::APPLICATION_SUGGESTIONS[$key];
                if ($field === 'desiredWeeklyHours') {
                    $applicationDefaults = [...$applicationDefaults, ...$this->desiredWeeklyHours->parseSuggestion($normalized)];
                    continue;
                }
                $applicationDefaults[$field] = $this->applicationFieldValues->normalize($field, $normalized);
                continue;
            }
            throw new ValidationException('Ein bestätigter Mailvorschlag ist ungültig.');
        }

        $hiringDefaults = $this->hiringMasterData->mailDefaults($confirmedHiring);
        foreach ($confirmedHiring as $key => $_suggestion) {
            if (!array_key_exists(self::HIRING_SUGGESTIONS[$key], $hiringDefaults)) {
                throw new ValidationException('Ein bestätigter Mailvorschlag hat ein ungültiges Format.');
            }
        }
        return [$hiringDefaults, $applicationDefaults];
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
