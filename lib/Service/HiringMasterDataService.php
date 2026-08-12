<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use DateTimeImmutable;
use OCA\Recruitment\Exception\ValidationException;

/** Validiert und projiziert ausschließlich die für die Vertragsvorbereitung freigegebenen Stammdaten. */
final class HiringMasterDataService {
    public const FIELDS = [
        'salutation', 'title', 'birthName', 'birthDate', 'birthPlace',
        'street', 'houseNumber', 'postalCode', 'city', 'country', 'nationality',
        'privateEmail', 'privatePhone',
        'iban', 'bic', 'accountHolder',
        'healthInsurance', 'healthInsuranceType', 'socialSecurityNumber',
        'taxId', 'taxClass',
        'plannedStartDate', 'contractType', 'contractTerm', 'workingTimeModel', 'positionTitle', 'workLocation',
        'payGrade', 'payStep',
        'weeklyHours', 'salaryAmount', 'salaryCurrency', 'vacationDays', 'contractEndDate',
    ];

    public const CONTRACT_TYPES = ['marginal', 'social_insurance', 'student', 'other'];
    public const CONTRACT_TERMS = ['permanent', 'fixed_term_reason'];
    public const WORKING_TIME_MODELS = ['fixed', 'kapovaz'];
    public const PAY_GRADES = ['3', '5', '8', '9a', '9b', '10', '11', '12', '13'];
    public const PAY_STEPS = ['1', '2', '3', '4', '5', '6'];

    private const DATE_FIELDS = ['birthDate', 'plannedStartDate', 'contractEndDate'];
    private const NUMBER_FIELDS = [
        'weeklyHours' => [0.0, 80.0],
        'salaryAmount' => [0.0, 1000000.0],
        'vacationDays' => [0.0, 366.0],
    ];

    /** @return array<string, string|float|null> */
    public function emptyData(): array {
        $data = [];
        foreach (self::FIELDS as $field) {
            $data[$field] = isset(self::NUMBER_FIELDS[$field]) ? null : '';
        }
        return $data;
    }

    /** @param array<string, mixed> $input
     *  @return array<string, string|float|null>
     */
    public function validate(array $input): array {
        $unknown = array_diff(array_keys($input), self::FIELDS);
        if ($unknown !== []) {
            throw new ValidationException('Die Einstellungsdaten enthalten nicht freigegebene Felder.');
        }

        $data = $this->emptyData();
        foreach ($input as $field => $value) {
            if (isset(self::NUMBER_FIELDS[$field])) {
                $data[$field] = $this->number($field, $value);
                continue;
            }
            if (!is_scalar($value) && $value !== null) {
                throw new ValidationException('Ein Stammdatenfeld hat ein ungültiges Format.');
            }
            $normalized = trim((string)($value ?? ''));
            if (strlen($normalized) > 255) {
                throw new ValidationException('Ein Stammdatenfeld ist zu lang.');
            }
            $data[$field] = $normalized;
        }

        foreach (self::DATE_FIELDS as $field) {
            if ((string)$data[$field] !== '') $this->assertDate((string)$data[$field]);
        }
        if ((string)$data['privateEmail'] !== '' && filter_var($data['privateEmail'], FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Die private E-Mail-Adresse ist ungültig.');
        }
        $this->assertChoice((string)$data['contractType'], self::CONTRACT_TYPES, 'Die Beschäftigungsform ist ungültig.');
        $this->assertChoice((string)$data['contractTerm'], self::CONTRACT_TERMS, 'Die Vertragsdauer ist ungültig.');
        $this->assertChoice((string)$data['workingTimeModel'], self::WORKING_TIME_MODELS, 'Das Arbeitszeitmodell ist ungültig.');
        $this->assertChoice((string)$data['payGrade'], self::PAY_GRADES, 'Die Entgeltgruppe ist ungültig.');
        $this->assertChoice((string)$data['payStep'], self::PAY_STEPS, 'Die Tarifstufe ist ungültig.');
        $data['iban'] = strtoupper((string)$data['iban']);
        $data['iban'] = str_replace(' ', '', $data['iban']);
        if ($data['iban'] !== '' && !$this->validIban((string)$data['iban'])) {
            throw new ValidationException('Die IBAN ist ungültig.');
        }
        $data['bic'] = strtoupper(str_replace(' ', '', (string)$data['bic']));
        if ($data['bic'] !== '' && preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', (string)$data['bic']) !== 1) {
            throw new ValidationException('Der BIC ist ungültig.');
        }
        $data['salaryCurrency'] = strtoupper((string)$data['salaryCurrency']);
        if ($data['salaryCurrency'] !== '' && preg_match('/^[A-Z]{3}$/', (string)$data['salaryCurrency']) !== 1) {
            throw new ValidationException('Die Gehaltswährung ist ungültig.');
        }
        return $data;
    }

    /**
     * Liest veröffentlichte Altstände datensparsam, ohne sie beim Lesen zu löschen.
     *
     * @param array<string,mixed> $stored
     * @return array<string,string|float|null>
     */
    public function normalizeStored(array $stored): array {
        unset($stored['maritalStatus']);
        $legacyContractType = trim((string)($stored['contractType'] ?? ''));
        if ($legacyContractType === 'Unbefristet') {
            $stored['contractType'] = '';
            $stored['contractTerm'] ??= 'permanent';
        } elseif ($legacyContractType === 'Befristet') {
            $stored['contractType'] = '';
            $stored['contractTerm'] ??= 'fixed_term_reason';
        }
        return $this->validate($stored);
    }

    /** @param array<string, mixed> $application
     *  @param array<string, mixed> $person
     *  @param array<string, mixed> $job
     *  @param array<string, mixed> $hiringData
     *  @return array<string, mixed>
     */
    public function payrollProjection(array $application, array $person, array $job, array $hiringData): array {
        if (!$this->isPayrollEligible($application)) {
            throw new ValidationException('Einstellungsstammdaten sind für Lohn in diesem Bewerbungsstand nicht verfügbar.');
        }
        $projection = [
            'applicationId' => (int)($application['id'] ?? 0),
            'status' => (string)$application['status'],
            'areaKey' => (string)($application['areaKey'] ?? ''),
            'givenName' => (string)($person['givenName'] ?? ''),
            'familyName' => (string)($person['familyName'] ?? ''),
            'email' => (string)($person['email'] ?? ''),
            'phone' => (string)($person['phone'] ?? ''),
            'position' => (string)($job['publicTitle'] ?? $job['internalTitle'] ?? ''),
            'hiringData' => $this->normalizeStored($hiringData),
        ];
        if ((string)($application['status'] ?? '') === 'basis_qualification') {
            $projection['basisQualification'] = [
                'id' => (int)($application['basisQualification']['id'] ?? 0),
                'label' => (string)($application['basisQualification']['label'] ?? ''),
            ];
        }
        return $projection;
    }

    /** @param array<string,mixed> $application */
    public function isPayrollEligible(array $application): bool {
        if (in_array((string)($application['status'] ?? ''), ['approved_for_hire', 'hired'], true)) return true;
        return (string)($application['status'] ?? '') === 'basis_qualification'
            && in_array((string)($application['basisQualification']['result'] ?? ''), ['pending', 'suitable'], true);
    }

    private function assertDate(string $value): void {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || $date->format('Y-m-d') !== $value
            || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new ValidationException('Ein Datum ist ungültig; erwartet wird JJJJ-MM-TT.');
        }
    }

    private function number(string $field, mixed $value): ?float {
        if ($value === '' || $value === null) return null;
        if (!is_numeric($value)) throw new ValidationException('Ein numerisches Stammdatenfeld ist ungültig.');
        $number = (float)$value;
        [$minimum, $maximum] = self::NUMBER_FIELDS[$field];
        if (!is_finite($number) || $number < $minimum || $number > $maximum) {
            throw new ValidationException('Ein numerisches Stammdatenfeld liegt außerhalb des zulässigen Bereichs.');
        }
        return $number;
    }

    private function validIban(string $iban): bool {
        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban) !== 1) return false;
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $remainder = 0;
        foreach (str_split($rearranged) as $character) {
            $digits = ctype_alpha($character) ? (string)(ord($character) - 55) : $character;
            foreach (str_split($digits) as $digit) $remainder = (($remainder * 10) + (int)$digit) % 97;
        }
        return $remainder === 1;
    }

    /** @param list<string> $allowed */
    private function assertChoice(string $value, array $allowed, string $message): void {
        if ($value !== '' && !in_array($value, $allowed, true)) {
            throw new ValidationException($message);
        }
    }
}
