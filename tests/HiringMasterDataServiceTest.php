<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use OCA\Recruitment\Exception\ValidationException;
use OCA\Recruitment\Service\HiringMasterDataService;
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

TestRunner::test('payroll projection is available during BQ until a negative outcome', static function (): void {
    $service = new HiringMasterDataService();
    $application = [
        'id' => 9,
        'status' => 'basis_qualification',
        'basisQualification' => ['id' => 5, 'label' => 'BQ 09/26', 'result' => 'pending'],
    ];
    $projection = $service->payrollProjection(
        $application,
        ['givenName' => 'Sam', 'familyName' => 'Beispiel'],
        ['publicTitle' => 'Assistenz'],
        ['healthInsurance' => 'Beispielkasse'],
    );

    assertSame('BQ 09/26', $projection['basisQualification']['label']);
    assertSame(false, array_key_exists('result', $projection['basisQualification']));
    assertSame('Beispielkasse', $projection['hiringData']['healthInsurance']);
    assertSame(false, array_key_exists('evaluationNote', $projection['basisQualification']));

    assertThrows(
        static fn () => $service->payrollProjection(
            array_replace($application, ['basisQualification' => ['id' => 5, 'result' => 'not_suitable']]),
            [],
            [],
            [],
        ),
        ValidationException::class,
    );
});
