<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\Recruitment\Contract\ApplicationStatusStore;
use OCA\Recruitment\Exception\ValidationException;

/**
 * Einzige fachliche Instanz für zulässige Bewerbungsstatus-Übergänge.
 */
final class ApplicationStatusService {
    /** @var array<string,list<string>> */
    private const TRANSITIONS = [
        'received' => ['screening', 'withdrawn'],
        'screening' => ['questionnaire_pending', 'phone_planned', 'rejected', 'withdrawn'],
        'questionnaire_pending' => ['questionnaire_received', 'withdrawn'],
        'questionnaire_received' => ['phone_planned', 'live_planned', 'rejected', 'withdrawn'],
        'phone_planned' => ['phone_completed', 'withdrawn'],
        'phone_completed' => ['live_planned', 'decision_pending', 'rejected', 'withdrawn'],
        'live_planned' => ['decision_pending', 'rejected', 'withdrawn'],
        'decision_pending' => ['approved_for_hire', 'rejected', 'withdrawn'],
        'basis_qualification' => ['decision_pending', 'approved_for_hire', 'rejected', 'withdrawn'],
        'approved_for_hire' => ['hired', 'withdrawn'],
        'accepted' => ['approved_for_hire', 'withdrawn'],
        'rejected' => ['archived'],
        'withdrawn' => ['archived'],
        'hired' => ['archived'],
        'archived' => [],
    ];

    public function targetStatus(string $currentStatus, string $targetStatus): string {
        if (!array_key_exists($currentStatus, self::TRANSITIONS)) {
            throw new ValidationException('Der aktuelle Bewerbungsstatus ist unbekannt.');
        }

        if (!in_array($targetStatus, self::TRANSITIONS[$currentStatus], true)) {
            throw new ValidationException('Dieser Statusübergang ist nicht zulässig.');
        }

        return $targetStatus;
    }

    /** @return list<string> */
    public function allowedTargets(string $currentStatus): array {
        return self::TRANSITIONS[$currentStatus] ?? [];
    }

    /** @return list<string> */
    public function orderedStatuses(): array {
        return array_keys(self::TRANSITIONS);
    }

    /** @param list<string> $validAreaKeys */
    public function approvalArea(string $targetStatus, string $areaKey, array $validAreaKeys): ?string {
        if ($targetStatus !== 'approved_for_hire') {
            return null;
        }
        $areaKey = trim($areaKey);
        if ($areaKey === '' || !in_array($areaKey, $validAreaKeys, true)) {
            throw new ValidationException('Für die Einstellungsfreigabe ist ein gültiger Bürobereich erforderlich.');
        }
        return $areaKey;
    }

    /** @return array<string,mixed> */
    public function transition(
        ApplicationStatusStore $store,
        int $applicationId,
        string $targetStatus,
        int $expectedVersion,
        string $actorUid,
        string $areaKey = '',
        array $validAreaKeys = [],
    ): array {
        $application = $store->findApplication($applicationId);
        $currentStatus = (string)$application['status'];
        $this->targetStatus($currentStatus, $targetStatus);
        if ($targetStatus === 'approved_for_hire'
            && is_array($application['basisQualification'] ?? null)
            && (string)($application['basisQualification']['result'] ?? '') !== 'suitable') {
            throw new ValidationException('Die Einstellungsfreigabe nach einer Basisqualifikation erfordert das Ergebnis Geeignet.');
        }
        $approvedArea = $this->approvalArea($targetStatus, $areaKey, $validAreaKeys);

        return $store->transitionStatus(
            $applicationId,
            $currentStatus,
            $targetStatus,
            $expectedVersion,
            $actorUid,
            $approvedArea,
            $approvedArea !== null,
        );
    }
}
