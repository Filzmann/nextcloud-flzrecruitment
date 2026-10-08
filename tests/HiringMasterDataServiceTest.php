<?php

declare(strict_types=1);

use OCA\FlzRecruitment\Exception\ValidationException;
use OCA\FlzRecruitment\Service\HiringMasterDataService;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;

TestRunner::test('contract master data is normalized through an explicit sensitive-field allowlist', static function (): void {
    $service = new HiringMasterDataService();
    $data = $service->validate([
        'birthName' => '  Beispiel  ',
        'birthDate' => '1990-02-28',
        'street' => ' Musterweg ',
        'houseNumber' => ' 12 a ',
        'postalCode' => ' 12345 ',
        'city' => ' Beispielstadt ',
        'iban' => 'de89 3704 0044 0532 0130 00',
        'healthInsurance' => ' Beispielkasse ',
        'plannedStartDate' => '2026-09-01',
        'contractType' => 'social_insurance',
        'contractTerm' => 'permanent',
        'workingTimeModel' => 'fixed',
        'payGrade' => '5',
        'payStep' => '2',
        'weeklyHours' => '38.5',
        'salaryAmount' => '4200.00',
        'salaryCurrency' => 'eur',
    ]);

    assertSame('Beispiel', $data['birthName']);
    assertSame('DE89370400440532013000', $data['iban']);
    assertSame(38.5, $data['weeklyHours']);
    assertSame(4200.0, $data['salaryAmount']);
    assertSame('EUR', $data['salaryCurrency']);
    assertSame(false, array_key_exists('maritalStatus', $data));
    assertSame(HiringMasterDataService::FIELDS, array_keys($service->emptyData()));
});

TestRunner::test('contract master data rejects undeclared and malformed sensitive values', static function (): void {
    $service = new HiringMasterDataService();

    assertThrows(static fn () => $service->validate(['freeFormSecret' => 'not allowed']), ValidationException::class);
    assertThrows(static fn () => $service->validate(['birthDate' => '31.02.1990']), ValidationException::class);
    assertThrows(static fn () => $service->validate(['iban' => 'DE123']), ValidationException::class);
    assertThrows(static fn () => $service->validate(['weeklyHours' => 200]), ValidationException::class);
    assertThrows(static fn () => $service->validate(['salaryCurrency' => 'EURO']), ValidationException::class);
    assertThrows(static fn () => $service->validate(['maritalStatus' => 'ledig']), ValidationException::class);
    assertThrows(static fn () => $service->validate(['contractType' => 'freelance']), ValidationException::class);
    assertThrows(static fn () => $service->validate(['salutation' => 'beliebig']), ValidationException::class);
    assertThrows(static fn () => $service->validate(['title' => 'Freitext']), ValidationException::class);
    assertThrows(static fn () => $service->validate(['healthInsuranceType' => 'unklar']), ValidationException::class);
    assertThrows(static fn () => $service->validate(['taxClass' => '7']), ValidationException::class);
    assertThrows(static fn () => $service->validate(['contractTerm' => 'without_reason']), ValidationException::class);
    assertThrows(static fn () => $service->validate(['workingTimeModel' => 'on_demand']), ValidationException::class);
    assertThrows(static fn () => $service->validate(['payGrade' => '7']), ValidationException::class);
    assertThrows(static fn () => $service->validate(['payStep' => '8']), ValidationException::class);
});

TestRunner::test('legacy family status and old contract label are hidden without blocking the contract area', static function (): void {
    $service = new HiringMasterDataService();
    $data = $service->normalizeStored([
        'maritalStatus' => 'nicht mehr erforderlich',
        'contractType' => 'Unbefristet',
        'city' => 'Beispielstadt',
    ]);

    assertSame(false, array_key_exists('maritalStatus', $data));
    assertSame('', $data['contractType']);
    assertSame('permanent', $data['contractTerm']);
    assertSame('Beispielstadt', $data['city']);
});

TestRunner::test('payroll projection exposes only released contract data and never dossier content', static function (): void {
    $service = new HiringMasterDataService();
    $projection = $service->payrollProjection(
        ['id' => 7, 'status' => 'approved_for_hire', 'areaKey' => 'west'],
        ['givenName' => 'Alex', 'familyName' => 'Beispiel', 'email' => 'alex@example.invalid', 'phone' => '0123'],
        ['publicTitle' => 'Fachkraft'],
        ['birthDate' => '1990-02-28', 'iban' => 'DE89370400440532013000'],
    );

    assertSame(7, $projection['applicationId']);
    assertSame('Alex', $projection['givenName']);
    assertSame('DE89370400440532013000', $projection['hiringData']['iban']);
    assertSame(false, array_key_exists('interviews', $projection));

    assertThrows(
        static fn () => $service->payrollProjection(
            ['id' => 8, 'status' => 'screening'], [], [], [],
        ),
        ValidationException::class,
    );
});

TestRunner::test('payroll projection stays unavailable during BQ', static function (): void {
    $service = new HiringMasterDataService();
    $application = [
        'id' => 9,
        'status' => 'basis_qualification',
        'basisQualification' => ['id' => 5, 'label' => 'BQ 09/26', 'result' => 'pending'],
    ];
    assertThrows(
        static fn () => $service->payrollProjection($application, [], [], ['healthInsurance' => 'Beispielkasse']),
        ValidationException::class,
    );
});

TestRunner::test('PersRef view excludes LoBu-only and job-derived contract fields', static function (): void {
    $service = new HiringMasterDataService();
    $visible = $service->personnelProjection([
        'city' => 'Berlin', 'iban' => 'DE89370400440532013000', 'taxId' => '123',
        'healthInsurance' => 'Beispielkasse', 'socialSecurityNumber' => '12', 'payGrade' => '5',
    ]);
    assertSame('Berlin', $visible['city']);
    foreach (['iban', 'bic', 'accountHolder', 'healthInsurance', 'healthInsuranceType', 'socialSecurityNumber', 'taxId', 'taxClass', 'workingTimeModel', 'contractTerm', 'payGrade', 'weeklyHours', 'vacationDays', 'workLocation', 'salaryAmount', 'salaryCurrency'] as $field) {
        assertSame(false, array_key_exists($field, $visible), "Field must stay hidden from PersRef: {$field}");
    }
});

TestRunner::test('LoBu projection starts at hire approval and derives contractual terms from the job', static function (): void {
    $service = new HiringMasterDataService();
    $projection = $service->payrollProjection(
        ['id' => 7, 'status' => 'approved_for_hire'], ['givenName' => 'Alex'],
        ['publicTitle' => 'Assistenz', 'professionCategory' => 'assistance', 'contractTerm' => 'permanent', 'payGrade' => '5', 'advertisedWeeklyHours' => 30.0, 'vacationDays' => 30.0, 'workLocation' => 'Berlin'],
        ['iban' => 'DE89370400440532013000', 'salaryAmount' => 4000, 'salaryCurrency' => 'EUR'],
    );
    assertSame('kapovaz', $projection['hiringData']['workingTimeModel']);
    assertSame('permanent', $projection['hiringData']['contractTerm']);
    assertSame('5', $projection['hiringData']['payGrade']);
    assertSame(30.0, $projection['hiringData']['weeklyHours']);
    assertSame('Berlin', $projection['hiringData']['workLocation']);
    assertSame(false, array_key_exists('salaryAmount', $projection['hiringData']));
    assertSame(false, array_key_exists('salaryCurrency', $projection['hiringData']));
    assertThrows(static fn() => $service->payrollProjection(['id' => 8, 'status' => 'basis_qualification'], [], [], []), ValidationException::class);
});

TestRunner::test('mail suggestions become safe empty-field defaults for the contract area', static function (): void {
    $service = new HiringMasterDataService();
    $defaults = $service->mailDefaults([
        'salutation' => ['value' => 'Frau'], 'title' => ['value' => 'Dr.'],
        'email' => ['value' => 'alex@example.invalid'], 'phone' => ['value' => '+49 30 123'],
        'availableFrom' => ['value' => '01.10.2026'], 'location' => ['value' => 'Berlin'],
        'iban' => ['value' => 'DE89370400440532013000'],
    ]);
    assertSame([
        'salutation' => 'female', 'title' => 'dr', 'city' => 'Berlin',
        'privateEmail' => 'alex@example.invalid', 'privatePhone' => '+49 30 123',
        'plannedStartDate' => '2026-10-01',
    ], $defaults);
});
