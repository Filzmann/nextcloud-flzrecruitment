<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use DateTimeImmutable;
use OCA\Recruitment\Exception\ValidationException;

/** Hält Vorlagenauflösung und Versandfreigabe unabhängig vom Mailtransport. */
final class StatusMailWorkflow {
    public const TIMING_IMMEDIATE = 'immediate';
    public const TIMING_SCHEDULED = 'scheduled';
    public const TIMING_NEXT_MONDAY = 'next_monday';

    private const PLACEHOLDERS = ['given_name', 'family_name', 'job_title'];

    public function __construct(private ?StatusMailBodyService $bodies = null) {}

    /**
     * @param array{id?: int, revision?: int, subject?: string, body?: string} $templateRevision
     * @param array<string, scalar|null> $context
     * @return array<string, mixed>
     */
    public function renderDraft(array $templateRevision, array $context, string $recipient): array {
        $recipient = trim($recipient);
        if ($recipient !== '') $recipient = $this->email($recipient, 'Die Bewerberadresse ist ungültig.');
        $revision = (int)($templateRevision['revision'] ?? 0);
        if ($revision < 1) {
            throw new ValidationException('Die Vorlagenrevision ist ungültig.');
        }

        $bodyFormat = (string)($templateRevision['bodyFormat'] ?? 'plain');
        return [
            'status' => 'draft',
            'originalRecipient' => $recipient,
            'subject' => $this->render((string)($templateRevision['subject'] ?? ''), $context, true),
            'body' => ($this->bodies ?? new StatusMailBodyService())->editableHtml(
                $this->render((string)($templateRevision['body'] ?? ''), $context, false, $bodyFormat === 'html'),
                $bodyFormat,
            ),
            'bodyFormat' => 'html',
            'templateId' => (int)($templateRevision['id'] ?? 0),
            'templateRevision' => $revision,
        ];
    }

    /** @param array<string, mixed> $draft
     *  @return array<string, mixed>
     */
    public function approveDraft(
        array $draft,
        string $subject,
        string $body,
        string $intendedRecipient,
        string $timing,
        ?DateTimeImmutable $requestedAt,
        DateTimeImmutable $now,
        string $testRecipient = '',
        string $bodyFormat = 'plain',
    ): array {
        if (($draft['status'] ?? null) !== 'draft') {
            throw new ValidationException('Nur ein bearbeitbarer Entwurf kann zum Versand freigegeben werden.');
        }
        $subject = trim($subject);
        $body = ($this->bodies ?? new StatusMailBodyService())->editableHtml($body, $bodyFormat);
        if ($subject === '' || strlen($subject) > 998) {
            throw new ValidationException('Der Betreff muss zwischen 1 und 998 Zeichen lang sein.');
        }
        if ($body === '') {
            throw new ValidationException('Der Nachrichtentext darf nicht leer sein.');
        }

        $originalRecipient = trim((string)($draft['originalRecipient'] ?? ''));
        if ($originalRecipient !== '') $originalRecipient = $this->email($originalRecipient, 'Die Bewerberadresse ist ungültig.');
        $intendedRecipient = $this->email($intendedRecipient, 'Die freizugebende Empfängeradresse ist ungültig.');
        $testRecipient = trim($testRecipient);
        $deliveryRecipient = $testRecipient === ''
            ? $intendedRecipient
            : $this->email($testRecipient, 'Die Testempfängeradresse ist ungültig.');

        return [
            ...$draft,
            'status' => 'approved',
            'subject' => $subject,
            'body' => $body,
            'bodyFormat' => 'html',
            'originalRecipient' => $originalRecipient,
            'intendedRecipient' => $intendedRecipient,
            'deliveryRecipient' => $deliveryRecipient,
            'testMode' => $testRecipient !== '',
            'scheduledAt' => $this->scheduledAt($timing, $requestedAt, $now)->format(DATE_ATOM),
        ];
    }

    /** @param array<string, scalar|null> $context */
    private function render(string $template, array $context, bool $subject, bool $escapeHtmlValues = false): string {
        $rendered = preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function (array $match) use ($context, $escapeHtmlValues): string {
            $key = $match[1];
            if (!in_array($key, self::PLACEHOLDERS, true) || !array_key_exists($key, $context)) {
                throw new ValidationException('Die Mailvorlage enthält einen unbekannten oder nicht auflösbaren Platzhalter.');
            }
            $value = trim((string)$context[$key]);
            return $escapeHtmlValues
                ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                : $value;
        }, $template);
        if (!is_string($rendered)) {
            throw new ValidationException('Die Mailvorlage konnte nicht verarbeitet werden.');
        }
        $rendered = $subject ? trim($rendered) : trim(str_replace(["\r\n", "\r"], "\n", $rendered));
        if ($rendered === '' || ($subject && strlen($rendered) > 998)) {
            throw new ValidationException($subject ? 'Der Vorlagenbetreff ist ungültig.' : 'Der Vorlagentext darf nicht leer sein.');
        }
        return $rendered;
    }

    private function scheduledAt(string $timing, ?DateTimeImmutable $requestedAt, DateTimeImmutable $now): DateTimeImmutable {
        if ($timing === self::TIMING_IMMEDIATE) {
            return $now;
        }
        if ($timing === self::TIMING_NEXT_MONDAY) {
            return $now->modify('next monday')->setTime(9, 0);
        }
        if ($timing !== self::TIMING_SCHEDULED || $requestedAt === null) {
            throw new ValidationException('Der Versandzeitpunkt ist ungültig.');
        }
        if ($requestedAt < $now) {
            throw new ValidationException('Der Versandzeitpunkt darf nicht in der Vergangenheit liegen.');
        }
        return $requestedAt;
    }

    private function email(string $email, string $message): string {
        $email = trim($email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 320) {
            throw new ValidationException($message);
        }
        return $email;
    }
}
