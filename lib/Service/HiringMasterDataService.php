<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;

use DateTimeImmutable;
use OCA\FlzRecruitment\Exception\ValidationException;

/** Validiert und projiziert ausschließlich die für die Vertragsvorbereitung freigegebenen Stammdaten. */
final class HiringMasterDataService {
    public const LOBU_ONLY_FIELDS = ['iban', 'bic', 'accountHolder', 'healthInsurance', 'healthInsuranceType', 'socialSecurityNumber', 'taxId', 'taxClass'];
    public const JOB_DERIVED_FIELDS = ['contractTerm', 'workingTimeModel', 'workLocation', 'payGrade', 'weeklyHours', 'vacationDays'];
    public const PERSONNEL_FIELDS = ['salutation', 'title', 'birthName', 'birthDate', 'birthPlace', 'street', 'houseNumber', 'postalCode', 'city', 'country', 'nationality', 'privateEmail', 'privatePhone', 'plannedStartDate', 'contractType', 'positionTitle', 'payStep', 'contractEndDate'];
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
    public const SALUTATIONS = ['female', 'male', 'diverse', 'neutral'];
    public const TITLES = ['dr', 'prof', 'prof_dr'];
    public const HEALTH_INSURANCE_TYPES = ['statutory', 'private', 'other'];
    public const TAX_CLASSES = ['1', '2', '3', '4', '5', '6'];

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
        $this->assertChoice((string)$data['salutation'], self::SALUTATIONS, 'Die Anrede ist ungültig.');
        $this->assertChoice((string)$data['title'], self::TITLES, 'Der Titel ist ungültig.');
        $this->assertChoice((string)$data['healthInsuranceType'], self::HEALTH_INSURANCE_TYPES, 'Die Versicherungsart ist ungültig.');
        $this->assertChoice((string)$data['taxClass'], self::TAX_CLASSES, 'Die Steuerklasse ist ungültig.');
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
        $stored['salutation'] = ['Frau' => 'female', 'Herr' => 'male', 'Divers' => 'diverse', 'Keine Anrede' => 'neutral'][$stored['salutation'] ?? ''] ?? ($stored['salutation'] ?? '');
        $stored['title'] = ['Dr.' => 'dr', 'Prof.' => 'prof', 'Prof. Dr.' => 'prof_dr'][$stored['title'] ?? ''] ?? ($stored['title'] ?? '');
        $stored['healthInsuranceType'] = ['Gesetzlich' => 'statutory', 'Privat' => 'private', 'Sonstige' => 'other'][$stored['healthInsuranceType'] ?? ''] ?? ($stored['healthInsuranceType'] ?? '');
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

    /** @return array<string,string|float|null> */
    public function personnelProjection(array $stored): array {
        $data = $this->normalizeStored($stored);
        foreach ([...self::LOBU_ONLY_FIELDS, ...self::JOB_DERIVED_FIELDS, 'salaryAmount', 'salaryCurrency'] as $field) unset($data[$field]);
        return $data;
    }

    public function validatePersonnelInput(array $input): array {
        $this->assertAllowedInput($input, self::PERSONNEL_FIELDS, 'Die Personalreferenz darf dieses Stammdatenfeld nicht bearbeiten.');
        $normalized = $this->validate($input);
        return array_intersect_key($normalized, array_flip(array_keys($input)));
    }

    public function validatePayrollInput(array $input): array {
        $this->assertAllowedInput($input, self::LOBU_ONLY_FIELDS, 'Die LoBu darf dieses Stammdatenfeld nicht bearbeiten.');
        $normalized = $this->validate($input);
        return array_intersect_key($normalized, array_flip(array_keys($input)));
    }

    /** @param array<string,mixed> $suggestions
     *  @return array<string,string>
     */
    public function mailDefaults(array $suggestions): array {
        $value = static fn(string $field): string => trim((string)($suggestions[$field]['value'] ?? ''));
        $defaults = [];
        $salutations = ['frau' => 'female', 'herr' => 'male', 'divers' => 'diverse', 'keine anrede' => 'neutral'];
        $salutation = strtolower($value('salutation'));
        if (isset($salutations[$salutation])) $defaults['salutation'] = $salutations[$salutation];
        $titles = ['dr' => 'dr', 'prof' => 'prof', 'profdr' => 'prof_dr'];
        $title = strtolower(str_replace(['.', ' '], '', $value('title')));
        if (isset($titles[$title])) $defaults['title'] = $titles[$title];
        if (filter_var($value('email'), FILTER_VALIDATE_EMAIL) !== false) $defaults['privateEmail'] = strtolower($value('email'));
        if ($value('phone') !== '') $defaults['privatePhone'] = substr($value('phone'), 0, 100);
        if ($value('location') !== '') $defaults['city'] = substr($value('location'), 0, 255);
        $available = $value('availableFrom');
        $date = DateTimeImmutable::createFromFormat('!d.m.Y', $available) ?: DateTimeImmutable::createFromFormat('!Y-m-d', $available);
        if ($date !== false) $defaults['plannedStartDate'] = $date->format('Y-m-d');
        return $this->validatePersonnelInput($defaults);
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
            'hiringData' => $this->contractProjection($job, $hiringData),
        ];
        return $projection;
    }

    /** @param array<string,mixed> $application */
    public function isPayrollEligible(array $application): bool {
        return in_array((string)($application['status'] ?? ''), ['approved_for_hire', 'hired'], true);
    }

    /** @return array<string,string|float|null> */
    private function contractProjection(array $job, array $stored): array {
        $data = $this->normalizeStored($stored);
        unset($data['salaryAmount'], $data['salaryCurrency']);
        $data['workingTimeModel'] = (string)($job['professionCategory'] ?? '') === 'assistance' ? 'kapovaz' : 'fixed';
        foreach (['contractTerm', 'payGrade', 'workLocation', 'vacationDays'] as $field) {
            if (array_key_exists($field, $job)) $data[$field] = $job[$field];
        }
        if (array_key_exists('advertisedWeeklyHours', $job)) $data['weeklyHours'] = $job['advertisedWeeklyHours'];
        $data['workLocation'] = trim((string)($data['workLocation'] ?? '')) ?: 'Berlin';
        return $data;
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

    private function assertAllowedInput(array $input, array $allowed, string $message): void {
        if (array_diff(array_keys($input), $allowed) !== []) throw new ValidationException($message);
    }
}
