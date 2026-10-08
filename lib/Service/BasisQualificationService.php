<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;

use DateTimeImmutable;
use OCA\FlzRecruitment\Contract\BasisQualificationStore;
use OCA\FlzRecruitment\Exception\ValidationException;

/** Fachlogik für lokale BQ-Durchläufe, Zuordnungen und einfache Ergebnisse. */
final class BasisQualificationService {
    public const RESULTS = ['pending', 'suitable', 'not_suitable', 'cancelled', 'no_show'];
    private const FINAL_RESULTS = ['suitable', 'not_suitable', 'cancelled', 'no_show'];

    /** @return array<string,mixed> */
    public function setJobRequirement(
        BasisQualificationStore $store,
        int $jobId,
        bool $required,
        int $expectedVersion,
        string $actorUid,
    ): array {
        $job = $store->basisQualificationJob($jobId);
        $derivedRequirement = ($job['professionCategory'] ?? '') === 'assistance';
        if ($required !== $derivedRequirement) {
            throw new ValidationException('Die BQ-Pflicht ergibt sich aus der Berufsgruppe und kann nicht separat geändert werden.');
        }
        return $job;
    }

    public function createRun(
        BasisQualificationStore $store,
        string $startsOn,
        string $endsOn,
        string $actorUid,
    ): int {
        $start = $this->date($startsOn);
        $end = $this->date($endsOn);
        if ($end < $start) {
            throw new ValidationException('Das Ende der Basisqualifikation darf nicht vor ihrem Beginn liegen.');
        }

        return $store->createBasisQualificationRun([
            'label' => 'BQ ' . $start->format('m/y'),
            'startsOn' => $startsOn,
            'endsOn' => $endsOn,
            'actorUid' => trim($actorUid),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function runs(BasisQualificationStore $store): array {
        return $store->basisQualificationRuns();
    }

    /** @return array<string,mixed> */
    public function assign(
        BasisQualificationStore $store,
        int $applicationId,
        int $runId,
        int $expectedApplicationVersion,
        string $actorUid,
    ): array {
        $context = $store->basisQualificationContext($applicationId);
        if (($context['job']['basisQualificationRequired'] ?? false) !== true) {
            throw new ValidationException('Nur Assistenz-Stellen mit Basisqualifikation können einem BQ-Durchlauf zugeordnet werden.');
        }
        if ((string)($context['application']['status'] ?? '') !== 'decision_pending') {
            throw new ValidationException('Die BQ-Zuordnung ist nur aus dem Status Entscheidung ausstehend zulässig.');
        }
        $store->basisQualificationRun($runId);
        if ($store->activeBasisQualificationAssignment($applicationId) !== null) {
            throw new ValidationException('Die Bewerbung besitzt bereits eine aktive BQ-Zuordnung.');
        }

        return $store->assignBasisQualification(
            $applicationId,
            $runId,
            $expectedApplicationVersion,
            trim($actorUid),
        );
    }

    /** @return array<string,mixed> */
    public function recordResult(
        BasisQualificationStore $store,
        int $assignmentId,
        string $result,
        string $note,
        int $expectedVersion,
        string $actorUid,
    ): array {
        $store->basisQualificationAssignment($assignmentId);
        if (!in_array($result, self::FINAL_RESULTS, true)) {
            throw new ValidationException('Das Ergebnis der Basisqualifikation ist ungültig.');
        }
        $note = trim($note);
        if (strlen($note) > 2000) {
            throw new ValidationException('Die BQ-Anmerkung ist zu lang.');
        }

        return $store->recordBasisQualificationResult(
            $assignmentId,
            $result,
            $note,
            $expectedVersion,
            trim($actorUid),
        );
    }

    private function date(string $value): DateTimeImmutable {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || $date->format('Y-m-d') !== $value
            || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new ValidationException('Für die Basisqualifikation wird ein gültiges Datum im Format JJJJ-MM-TT benötigt.');
        }
        return $date;
    }
}
