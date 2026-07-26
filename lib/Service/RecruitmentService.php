<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use DateTimeImmutable;
use OCA\Recruitment\Contract\RecruitmentStore;
use OCA\Recruitment\Exception\NotFoundException;
use OCA\Recruitment\Exception\ValidationException;

/**
 * Anwendungsfälle für Stellen, Personen und Bewerbungen.
 */
final class RecruitmentService {
    /**
     * @param list<string> $responsibleUsers
     * @param list<string> $responsibleGroups
     */
    public function createJob(
        RecruitmentStore $store,
        string $internalTitle,
        string $publicTitle,
        bool $active,
        array $responsibleUsers,
        array $responsibleGroups,
        string $assignmentKey,
    ): int {
        $internalTitle = trim($internalTitle);
        if ($internalTitle === '') {
            throw new ValidationException('Die interne Stellenbezeichnung ist erforderlich.');
        }

        return $store->createJob([
            'internalTitle' => $internalTitle,
            'publicTitle' => trim($publicTitle),
            'active' => $active,
            'responsibleUsers' => $this->cleanIdentifiers($responsibleUsers),
            'responsibleGroups' => $this->cleanIdentifiers($responsibleGroups),
            'assignmentKey' => trim($assignmentKey),
        ]);
    }

    public function createPerson(
        RecruitmentStore $store,
        string $givenName,
        string $familyName,
        string $email,
        string $phone,
    ): int {
        $givenName = trim($givenName);
        $familyName = trim($familyName);
        $email = trim($email);
        if ($givenName === '' || $familyName === '') {
            throw new ValidationException('Vor- und Nachname sind erforderlich.');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Die E-Mail-Adresse ist ungültig.');
        }

        return $store->createPerson([
            'givenName' => $givenName,
            'familyName' => $familyName,
            'email' => $email,
            'phone' => trim($phone),
        ]);
    }

    public function createApplication(
        RecruitmentStore $store,
        int $personId,
        int $jobId,
        string $source,
        string $receivedOn,
        string $assigneeUid,
    ): int {
        if (!$store->personExists($personId)) {
            throw new NotFoundException('Die Person wurde nicht gefunden.');
        }
        if (!$store->jobExists($jobId)) {
            throw new NotFoundException('Die Stelle wurde nicht gefunden.');
        }
        if (!in_array($source, ['manual', 'email_import', 'referral', 'other'], true)) {
            throw new ValidationException('Der Eingangskanal ist ungültig.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $receivedOn);
        if ($date === false || $date->format('Y-m-d') !== $receivedOn) {
            throw new ValidationException('Das Eingangsdatum ist ungültig.');
        }

        return $store->createApplication([
            'personId' => $personId,
            'jobId' => $jobId,
            'source' => $source,
            'receivedOn' => $receivedOn,
            'assigneeUid' => trim($assigneeUid),
        ]);
    }

    /** @return array<string,list<array<string,mixed>>> */
    public function overview(RecruitmentStore $store): array {
        return $store->overview();
    }

    /** @return array<string,mixed> */
    public function applicationDetail(RecruitmentStore $store, int $id): array {
        return $store->applicationDetail($id);
    }

    /** @param list<string> $values
     *  @return list<string>
     */
    private function cleanIdentifiers(array $values): array {
        $clean = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => trim((string)$value), $values),
            static fn (string $value): bool => $value !== '',
        )));
        return $clean;
    }
}
