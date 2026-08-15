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
        bool $basisQualificationRequired = false,
        string $professionCategory = '',
        string $contractTerm = '',
        string $payGrade = '',
        ?float $advertisedWeeklyHours = null,
        ?float $fullTimeWeeklyHours = null,
        ?float $vacationDays = null,
        string $workLocation = 'Berlin',
    ): int {
        $internalTitle = trim($internalTitle);
        if ($internalTitle === '') {
            throw new ValidationException('Die interne Stellenbezeichnung ist erforderlich.');
        }

        $professionCategory = trim($professionCategory);
        if ($professionCategory === '') {
            $professionCategory = $basisQualificationRequired ? 'assistance' : 'other';
        }
        if (!in_array($professionCategory, ['assistance', 'nursing', 'social_work', 'administration', 'other'], true)) {
            throw new ValidationException('Die Berufsgruppe der Stelle ist ungültig.');
        }
        if ($basisQualificationRequired && $professionCategory !== 'assistance') {
            throw new ValidationException('Eine Basisqualifikation ist nur für Assistenz-Stellen zulässig.');
        }
        $basisQualificationRequired = $professionCategory === 'assistance';
        if ($contractTerm !== '' && !in_array($contractTerm, ['permanent', 'fixed_term_reason'], true)) throw new ValidationException('Die Vertragsdauer der Stelle ist ungültig.');
        if ($payGrade !== '' && !in_array($payGrade, ['3', '5', '8', '9a', '9b', '10', '11', '12', '13'], true)) throw new ValidationException('Die Entgeltgruppe der Stelle ist ungültig.');
        foreach ([$advertisedWeeklyHours, $fullTimeWeeklyHours] as $hours) if ($hours !== null && (!is_finite($hours) || $hours <= 0 || $hours > 80)) throw new ValidationException('Die Wochenstunden der Stelle sind ungültig.');
        if ($vacationDays !== null && (!is_finite($vacationDays) || $vacationDays < 0 || $vacationDays > 366)) throw new ValidationException('Der Urlaubsanspruch der Stelle ist ungültig.');

        return $store->createJob([
            'internalTitle' => $internalTitle,
            'publicTitle' => trim($publicTitle),
            'active' => $active,
            'responsibleUsers' => $this->cleanIdentifiers($responsibleUsers),
            'responsibleGroups' => $this->cleanIdentifiers($responsibleGroups),
            'assignmentKey' => trim($assignmentKey),
            'basisQualificationRequired' => $basisQualificationRequired,
            'professionCategory' => $professionCategory,
            'contractTerm' => $contractTerm, 'payGrade' => $payGrade,
            'advertisedWeeklyHours' => $advertisedWeeklyHours, 'fullTimeWeeklyHours' => $fullTimeWeeklyHours,
            'vacationDays' => $vacationDays, 'workLocation' => trim($workLocation) ?: 'Berlin',
            'workingTimeModel' => $professionCategory === 'assistance' ? 'kapovaz' : 'fixed',
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
        ?float $desiredWeeklyHours = null,
        ?float $desiredWeeklyHoursMax = null,
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
        if ($desiredWeeklyHours !== null
            && (!is_finite($desiredWeeklyHours) || $desiredWeeklyHours <= 0 || $desiredWeeklyHours > 80)) {
            throw new ValidationException('Die gewünschten Wochenstunden müssen größer als 0 und höchstens 80 sein.');
        }
        if ($desiredWeeklyHoursMax !== null
            && (!is_finite($desiredWeeklyHoursMax) || $desiredWeeklyHoursMax <= 0 || $desiredWeeklyHoursMax > 80)) {
            throw new ValidationException('Die Obergrenze der gewünschten Wochenstunden muss größer als 0 und höchstens 80 sein.');
        }
        if ($desiredWeeklyHours === null && $desiredWeeklyHoursMax !== null) {
            throw new ValidationException('Für einen Wunschstundenbereich ist ein Von-Wert erforderlich.');
        }
        if ($desiredWeeklyHours !== null && $desiredWeeklyHoursMax !== null) {
            if ($desiredWeeklyHoursMax < $desiredWeeklyHours) {
                throw new ValidationException('Die Obergrenze der Wunschstunden darf nicht unter dem Von-Wert liegen.');
            }
            if ($desiredWeeklyHoursMax === $desiredWeeklyHours) {
                $desiredWeeklyHoursMax = null;
            }
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
            'desiredWeeklyHours' => $desiredWeeklyHours,
            'desiredWeeklyHoursMax' => $desiredWeeklyHoursMax,
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
