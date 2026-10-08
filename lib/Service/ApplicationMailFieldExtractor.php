<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;

use OCA\FlzRecruitment\Exception\ValidationException;

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
    public function extract(string $senderEmail, string $body, string $resumeText = '', string $senderName = ''): array {
        $fields = [
            'salutation' => null,
            'title' => null,
            'name' => null,
            'email' => null,
            'phone' => null,
            'jobPreference' => null,
            'message' => null,
            'desiredWeeklyHours' => null,
            'availableFrom' => null,
            'previousExperience' => null,
            'germanLanguageLevel' => null,
            'location' => null,
        ];
        $sources = [];
        $seenLabels = [];
        foreach ([[$body, 'mail_body_labeled'], [$resumeText, 'resume_text_labeled']] as [$text, $source]) {
          $pendingField = null;
          foreach (preg_split('/\R/u', $text) ?: [] as $bodyLine) {
            $line = trim($bodyLine);
            if ($line === '') {
                continue;
            }

            $labeled = $this->labeledField($line);
            if ($labeled !== null) {
                if ($source === 'mail_body_labeled') $seenLabels[$labeled['key']] = true;
                $pendingField = $labeled['key'];
                if ($labeled['value'] !== '' && $fields[$pendingField] === null) {
                    $fields[$pendingField] = $labeled['value'];
                    $sources[$pendingField] = $source === 'resume_text_labeled' && ($labeled['keyword'] ?? false)
                        ? 'resume_text_keyword'
                        : $source;
                    $pendingField = null;
                }
                continue;
            }

            if ($pendingField !== null && $fields[$pendingField] === null) {
                $fields[$pendingField] = $line;
                $sources[$pendingField] = $source;
                $pendingField = null;
            }
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

        $jobPreference = $this->suggestion($fields['jobPreference'], $sources['jobPreference'] ?? 'mail_body_labeled');
        $jobKey = strtolower(trim((string)($fields['jobPreference'] ?? '')));
        $name = $this->suggestion($fields['name'], $sources['name'] ?? 'mail_body_labeled')
            ?? $this->derivedName($body, $senderEmail, $senderName);

        return [
            'structuredForm' => $this->isStructuredWebsiteForm($seenLabels),
            'salutation' => $this->suggestion($fields['salutation'], $sources['salutation'] ?? 'mail_body_labeled'),
            'title' => $this->suggestion($fields['title'], $sources['title'] ?? 'mail_body_labeled'),
            'name' => $name,
            'email' => ['value' => $email, 'source' => $emailSource],
            'phone' => $this->suggestion($fields['phone'], $sources['phone'] ?? 'mail_body_labeled'),
            'jobPreference' => $jobPreference,
            'jobCategory' => self::JOB_CATEGORIES[$jobKey] ?? null,
            'message' => $this->suggestion($fields['message'], $sources['message'] ?? 'mail_body_labeled'),
            'desiredWeeklyHours' => $this->suggestion($fields['desiredWeeklyHours'], $sources['desiredWeeklyHours'] ?? 'mail_body_labeled'),
            'availableFrom' => $this->suggestion($fields['availableFrom'], $sources['availableFrom'] ?? 'mail_body_labeled'),
            'previousExperience' => $this->suggestion($fields['previousExperience'], $sources['previousExperience'] ?? 'resume_text_labeled'),
            'germanLanguageLevel' => $this->suggestion($fields['germanLanguageLevel'], $sources['germanLanguageLevel'] ?? 'resume_text_labeled'),
            'location' => $this->suggestion($fields['location'], $sources['location'] ?? 'resume_text_labeled'),
        ];
    }

    /** @return array{key:string,value:string,keyword?:bool}|null */
    private function labeledField(string $line): ?array {
        $patterns = [
            'salutation' => '/^anrede\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'title' => '/^(?:titel|akademischer\s+titel)\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'name' => '/^(?:dein\s+)?name\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'email' => '/^e[\s-]?mail\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'phone' => '/^(?:telefonnummer|telefon)\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'jobPreference' => '/^ich\s+bewerbe\s+mich\s+als(?:\.\.\.|…)?\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'message' => '/^nachricht(?:\s*\([^)]*\))?\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'desiredWeeklyHours' => '/^(?:gewünschte\s+)?wochenstunden\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'availableFrom' => '/^(?:verfügbar|eintritt|beginn)(?:\s+ab)?\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'previousExperience' => '/^(?:berufserfahrung|erfahrung)\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'germanLanguageLevel' => '/^(?:deutschkenntnisse|deutschniveau)\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
            'location' => '/^(?:wohnort|ort)\s*\*?\s*(?:(?::|-)\s*(.*))?$/iu',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $line, $matches) === 1) {
                return ['key' => $key, 'value' => trim((string)($matches[1] ?? ''))];
            }
        }

        $keywordPatterns = [
            'name' => '/^(?:name|vor-\s*und\s+nachname)\s+(.+)$/iu',
            'phone' => '/^(?:telefon|telefonnummer|mobil|mobiltelefon)\s+(.+)$/iu',
            'desiredWeeklyHours' => '/^(?:gewünschte\s+)?(?:wochenstunden|wunschstunden)\s+(.+)$/iu',
            'availableFrom' => '/^(?:verfügbarkeit|verfügbar|eintritt|beginn)(?:\s+ab)?\s+(.+)$/iu',
            'germanLanguageLevel' => '/^deutsch(?:kenntnisse|niveau)?\s+(A1|A2|B1|B2|C1|C2|muttersprachlich)$/iu',
            'location' => '/^(?:wohnort|ort)\s+(.+)$/iu',
        ];
        foreach ($keywordPatterns as $key => $pattern) {
            if (preg_match($pattern, $line, $matches) === 1) {
                return ['key' => $key, 'value' => trim((string)$matches[1]), 'keyword' => true];
            }
        }

        return null;
    }

    /** @return array{value:string,source:string}|null */
    private function suggestion(?string $value, string $source = 'mail_body_labeled'): ?array {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        return ['value' => $value, 'source' => $source];
    }

    /** @return array{value:string,source:string}|null */
    private function derivedName(string $body, string $senderEmail, string $senderName): ?array {
        if (preg_match(
            '/\b(?:mein\s+name\s+ist|ich\s+heiße|ich\s+bin)\s+([\p{L}\p{M}\'’.-]+(?:\s+[\p{L}\p{M}\'’.-]+){1,3})(?=[,;.!?\n]|$)/iu',
            $body,
            $match,
        ) === 1) {
            $name = $this->plausibleName((string)$match[1]);
            if ($name !== null) return ['value' => $name, 'source' => 'mail_body_self_identification'];
        }

        $lines = preg_split('/\R/u', str_replace(["\r\n", "\r"], "\n", $body)) ?: [];
        foreach ($lines as $index => $line) {
            if (preg_match('/^(?:(?:mit\s+)?freundlichen|viele|beste|herzliche|liebe)\s+grüße[!,]?$/iu', trim($line)) !== 1) continue;
            for ($candidateIndex = $index + 1; $candidateIndex < count($lines); $candidateIndex++) {
                $candidate = trim($lines[$candidateIndex]);
                if ($candidate === '') continue;
                $name = $this->plausibleName($candidate);
                if ($name !== null) return ['value' => $name, 'source' => 'mail_body_signature'];
                break;
            }
        }

        $displayName = $this->plausibleName($senderName);
        if ($displayName !== null) return ['value' => $displayName, 'source' => 'mail_sender_name'];

        $localPart = explode('@', strtolower(trim($senderEmail)), 2)[0] ?? '';
        $localPart = explode('+', $localPart, 2)[0];
        if ($localPart === '' || preg_match('/[0-9]/', $localPart) === 1) return null;
        $tokens = preg_split('/[._-]+/', $localPart, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $roleTokens = ['admin', 'bewerbung', 'contact', 'hallo', 'info', 'jobs', 'karriere', 'kontakt', 'mail', 'noreply', 'office', 'personal', 'recruiting', 'support', 'team'];
        if (count($tokens) < 2 || count($tokens) > 4 || array_intersect($tokens, $roleTokens) !== []) return null;
        $name = $this->plausibleName(implode(' ', array_map(static fn(string $token): string => ucfirst($token), $tokens)));
        return $name === null ? null : ['value' => $name, 'source' => 'mail_sender_address'];
    }

    private function plausibleName(string $candidate): ?string {
        $candidate = trim($candidate, " \t\n\r\0\x0B,;.!?");
        if (strlen($candidate) > 120 || str_contains($candidate, '@') || preg_match('/[0-9]/', $candidate) === 1) return null;
        $tokens = preg_split('/\s+/u', $candidate, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokens) < 2 || count($tokens) > 4) return null;
        $particles = ['de', 'der', 'van', 'vom', 'von', 'zu', 'zur'];
        $roleTokens = ['bewerbung', 'contact', 'info', 'karriere', 'kontakt', 'personal', 'recruiting', 'support', 'team'];
        if (array_intersect(array_map('strtolower', $tokens), $roleTokens) !== []) return null;
        foreach ($tokens as $index => $token) {
            if ($index > 0 && $index < count($tokens) - 1 && in_array(strtolower($token), $particles, true)) continue;
            if (preg_match('/^\p{Lu}[\p{L}\p{M}\'’.-]{1,}$/u', $token) !== 1) return null;
        }
        return implode(' ', $tokens);
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
