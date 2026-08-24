<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\Recruitment\Exception\ValidationException;

/** Kanonische Normalisierung der aus mehreren Quellen befüllbaren Bewerbungsfelder. */
final class ApplicationFieldValueService {
    public const FIELDS = ['previousExperience', 'germanLanguageLevel'];
    public const GERMAN_LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'native', 'not_assessed'];

    public function normalize(string $field, mixed $value): string {
        if (!in_array($field, self::FIELDS, true) || !is_scalar($value)) {
            throw new ValidationException('Das Bewerbungsfeld ist ungültig.');
        }
        $normalized = trim((string)$value);
        if ($field === 'previousExperience') {
            if ($normalized === '' || strlen($normalized) > 8000) {
                throw new ValidationException('Die Vorerfahrung ist ungültig.');
            }
            return $normalized;
        }

        $normalized = match (strtolower($normalized)) {
            'muttersprachlich', 'native' => 'native',
            'nicht bewertet', 'not_assessed' => 'not_assessed',
            default => strtoupper($normalized),
        };
        if (!in_array($normalized, self::GERMAN_LEVELS, true)) {
            throw new ValidationException('Das Deutschniveau ist ungültig.');
        }
        return $normalized;
    }
}
