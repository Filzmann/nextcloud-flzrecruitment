<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\Recruitment\Exception\ConflictException;
use OCA\Recruitment\Exception\ValidationException;

/**
 * Fachregeln für Vorlagen-Snapshots, Entwürfe und Interviewabschluss.
 */
final class InterviewWorkflow {
    /** @param array<string,mixed> $template
     *  @return array<string,mixed>
     */
    public function snapshot(array $template): array {
        $encoded = json_encode($template, JSON_THROW_ON_ERROR);
        /** @var array<string,mixed> $snapshot */
        $snapshot = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        return $snapshot;
    }

    public function assertDraftWritable(string $status, int $currentVersion, int $expectedVersion): string {
        if ($status === 'completed') {
            throw new ConflictException('Ein abgeschlossenes Interview kann nicht still verändert werden.');
        }
        if ($status === 'aborted') {
            throw new ConflictException('Ein abgebrochenes Interview kann nicht verändert werden.');
        }
        if ($currentVersion !== $expectedVersion) {
            throw new ConflictException('Das Interview wurde zwischenzeitlich geändert.');
        }

        return $status === 'not_started' ? 'in_progress' : $status;
    }

    /**
     * @param array<string,mixed> $snapshot
     * @param array<string,mixed> $answers
     */
    public function complete(array $snapshot, array $answers): string {
        foreach ($snapshot['questions'] ?? [] as $question) {
            if (($question['required'] ?? false) !== true || ($question['active'] ?? true) !== true) {
                continue;
            }

            $key = (string)($question['id'] ?? '');
            $answer = $answers[$key] ?? null;
            $empty = $answer === null
                || $answer === ''
                || (is_string($answer) && trim($answer) === '')
                || (is_array($answer) && $answer === []);
            if ($empty) {
                throw new ValidationException('Bitte beantworten Sie alle Pflichtfragen.');
            }
        }

        return 'completed';
    }
}
