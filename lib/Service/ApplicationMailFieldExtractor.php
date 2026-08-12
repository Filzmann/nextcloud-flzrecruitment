<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\Recruitment\Exception\ValidationException;

/**
 * Liest nachvollziehbare Feldvorschläge aus einem bereits empfangenen
 * Klartext-Mailbody. Der Dienst importiert oder speichert selbst nichts.
 */
final class ApplicationMailFieldExtractor {
    /** @var array<string,string> */
    private const JOB_CATEGORIES = [
        'assistenz' => 'assistance',
        'pflegefachkraft' => 'nursing_specialist',
        'sozialarbeiter:in' => 'social_work',
        'sozialarbeiterin' => 'social_work',
        'sozialarbeit' => 'social_work',
        'verwaltung' => 'administration',
    ];

    /**
     * @return array{
     *   structuredForm:bool,
     *   name:?array{value:string,source:string},
     *   email:array{value:string,source:string},
     *   phone:?array{value:string,source:string},
     *   jobPreference:?array{value:string,source:string},
     *   jobCategory:?string,
     *   message:?array{value:string,source:string}
     * }
     */
    public function extract(string $senderEmail, string $body): array {
        $fields = [
            'name' => null,
            'email' => null,
            'phone' => null,
            'jobPreference' => null,
            'message' => null,
        ];
        $seenLabels = [];
        $pendingField = null;

        foreach (preg_split('/\R/u', $body) ?: [] as $bodyLine) {
            $line = trim($bodyLine);
            if ($line === '') {
                continue;
            }

            $labeled = $this->labeledField($line);
            if ($labeled !== null) {
                $seenLabels[$labeled['key']] = true;
                $pendingField = $labeled['key'];
                if ($labeled['value'] !== '' && $fields[$pendingField] === null) {
                    $fields[$pendingField] = $labeled['value'];
                    $pendingField = null;
                }
                continue;
            }

            if ($pendingField !== null && $fields[$pendingField] === null) {
                $fields[$pendingField] = $line;
                $pendingField = null;
            }
        }

        $email = $this->validEmail((string)($fields['email'] ?? ''));
        $emailSource = 'mail_body_labeled';
        if ($email === null) {
            $detectedEmails = $this->detectedEmails($body);
            if (count($detectedEmails) === 1) {
                $email = $detectedEmails[0];
                $emailSource = 'mail_body_detected';
            }
        }
        if ($email === null) {
            $email = $this->validEmail($senderEmail);
            $emailSource = 'mail_sender';
        }
        if ($email === null) {
            throw new ValidationException('Die Bewerbung enthält keine gültige Kontakt-E-Mail-Adresse.');
        }

        $jobPreference = $this->suggestion($fields['jobPreference']);
        $jobKey = strtolower(trim((string)($fields['jobPreference'] ?? '')));

        return [
            'structuredForm' => $this->isStructuredWebsiteForm($seenLabels),
            'name' => $this->suggestion($fields['name']),
            'email' => ['value' => $email, 'source' => $emailSource],
            'phone' => $this->suggestion($fields['phone']),
            'jobPreference' => $jobPreference,
            'jobCategory' => self::JOB_CATEGORIES[$jobKey] ?? null,
            'message' => $this->suggestion($fields['message']),
        ];
    }

    /** @return array{key:string,value:string}|null */
    private function labeledField(string $line): ?array {
        $patterns = [
            'name' => '/^(?:dein\s+)?name\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'email' => '/^e[\s-]?mail\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'phone' => '/^(?:telefonnummer|telefon)\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'jobPreference' => '/^ich\s+bewerbe\s+mich\s+als(?:\.\.\.|…)?\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'message' => '/^nachricht(?:\s*\([^)]*\))?\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $line, $matches) === 1) {
                return ['key' => $key, 'value' => trim((string)($matches[1] ?? ''))];
            }
        }

        return null;
    }

    /** @return array{value:string,source:string}|null */
    private function suggestion(?string $value): ?array {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        return ['value' => $value, 'source' => 'mail_body_labeled'];
    }

    private function validEmail(string $value): ?string {
        $value = trim($value);
        return filter_var($value, FILTER_VALIDATE_EMAIL) === false ? null : $value;
    }

    /** @return list<string> */
    private function detectedEmails(string $body): array {
        preg_match_all(
            '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/iu',
            $body,
            $matches,
        );

        $emails = [];
        foreach ($matches[0] ?? [] as $candidate) {
            $email = $this->validEmail((string)$candidate);
            if ($email !== null) {
                $emails[strtolower($email)] = $email;
            }
        }
        return array_values($emails);
    }

    /** @param array<string,bool> $seenLabels */
    private function isStructuredWebsiteForm(array $seenLabels): bool {
        foreach (['name', 'email', 'phone', 'jobPreference'] as $requiredLabel) {
            if (!isset($seenLabels[$requiredLabel])) {
                return false;
            }
        }
        return true;
    }
}
