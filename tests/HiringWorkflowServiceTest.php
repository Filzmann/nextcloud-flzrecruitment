<?php

declare(strict_types=1);

use OCA\Recruitment\Contract\HiringDataStore;
use OCA\Recruitment\Exception\ConflictException;
use OCA\Recruitment\Service\HiringMasterDataService;
use OCA\Recruitment\Service\HiringWorkflowService;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;

final class MemoryHiringStore implements HiringDataStore {
    public array $applications = [
        1 => ['id' => 1, 'status' => 'approved_for_hire', 'areaKey' => 'west', 'version' => 4],
        2 => ['id' => 2, 'status' => 'screening', 'areaKey' => '', 'version' => 2],
        3 => ['id' => 3, 'status' => 'basis_qualification', 'areaKey' => '', 'version' => 3, 'basisQualification' => ['id' => 7, 'label' => 'BQ 09/26', 'result' => 'pending']],
        4 => ['id' => 4, 'status' => 'basis_qualification', 'areaKey' => '', 'version' => 3, 'basisQualification' => ['id' => 8, 'label' => 'BQ 10/26', 'result' => 'not_suitable']],
        5 => ['id' => 5, 'status' => 'approved_for_hire', 'areaKey' => 'west', 'version' => 1],
    ];
    public array $data = [];
    public int $dossierReads = 0;

    public function findApplication(int $id): array { return $this->applications[$id]; }
    public function applicationDetail(int $id): array {
        $this->dossierReads++;
        return [
            'application' => $this->applications[$id],
            'person' => ['givenName' => 'Alex', 'familyName' => 'Beispiel', 'email' => 'a@example.invalid', 'phone' => '1'],
            'job' => ['publicTitle' => 'Fachkraft'],
            'interviews' => [['confidential' => true]],
        ];
    }
    public function hiringContext(int $id): array {
        return [
            'application' => $this->applications[$id],
            'person' => ['givenName' => 'Alex', 'familyName' => 'Beispiel', 'email' => 'a@example.invalid', 'phone' => '1'],
            'job' => ['publicTitle' => $id === 5 ? 'Assistenz' : 'Fachkraft', 'professionCategory' => $id === 5 ? 'assistance' : 'nursing'],
        ];
    }
    public function hiringData(int $applicationId): array { return $this->data[$applicationId] ?? ['data' => [], 'version' => 0]; }
    public function saveHiringData(int $applicationId, array $data, int $expectedVersion, string $actorUid): array {
        $current = $this->hiringData($applicationId);
        if ($current['version'] !== $expectedVersion) throw new ConflictException('stale');
        return $this->data[$applicationId] = ['data' => $data, 'version' => $expectedVersion + 1];
    }
    public function releasedHiringApplications(): array { return [$this->applications[1], $this->applications[3], $this->applications[4]]; }
    public function setFirstGuideAccess(int $applicationId, bool $enabled, int $expectedVersion, string $actorUid): array {
        if ($this->applications[$applicationId]['version'] !== $expectedVersion) throw new ConflictException('stale');
        $this->applications[$applicationId]['firstGuideAccess'] = $enabled;
        $this->applications[$applicationId]['version']++;
        return $this->applications[$applicationId];
    }
}

TestRunner::test('hiring workflow validates before optimistic persistence', static function (): void {
    $store = new MemoryHiringStore();
    $workflow = new HiringWorkflowService(new HiringMasterDataService());

    $saved = $workflow->save($store, 1, ['city' => 'Berlin'], 0, 'hr-user');
    assertSame(1, $saved['version']);
    assertSame('Berlin', $saved['data']['city']);
    assertThrows(
        static fn () => $workflow->save($store, 1, ['city' => 'Berlin'], 0, 'hr-user'),
        ConflictException::class,
    );
});

TestRunner::test('PersRef cannot write LoBu-only or job-derived fields', static function (): void {
    $store = new MemoryHiringStore(); $workflow = new HiringWorkflowService(new HiringMasterDataService());
    assertThrows(static fn() => $workflow->save($store, 1, ['iban' => 'DE89370400440532013000'], 0, 'hr-user'), \OCA\Recruitment\Exception\ValidationException::class);
    assertThrows(static fn() => $workflow->save($store, 1, ['payGrade' => '5'], 0, 'hr-user'), \OCA\Recruitment\Exception\ValidationException::class);
    assertSame([], $store->data);
});

TestRunner::test('LoBu writes only sensitive fields after hire approval', static function (): void {
    $store = new MemoryHiringStore(); $workflow = new HiringWorkflowService(new HiringMasterDataService());
    $saved = $workflow->savePayroll($store, 1, ['iban' => 'DE89370400440532013000', 'healthInsurance' => 'Beispielkasse'], 0, 'payroll');
    assertSame('DE89370400440532013000', $saved['data']['iban']);
    assertThrows(static fn() => $workflow->savePayroll($store, 2, ['taxId' => '123'], 0, 'payroll'), \OCA\Recruitment\Exception\ValidationException::class);
    assertThrows(static fn() => $workflow->savePayroll($store, 1, ['city' => 'Manipuliert'], 1, 'payroll'), \OCA\Recruitment\Exception\ValidationException::class);
});

TestRunner::test('job-derived contract data cannot be overridden in applicant master data', static function (): void {
    $store = new MemoryHiringStore();
    $workflow = new HiringWorkflowService(new HiringMasterDataService());

    assertThrows(static fn () => $workflow->save($store, 5, ['workingTimeModel' => 'kapovaz'], 0, 'hr-user'), \OCA\Recruitment\Exception\ValidationException::class);
});

TestRunner::test('payroll list projects released hiring records without dossier fields', static function (): void {
    $store = new MemoryHiringStore();
    $store->data[1] = ['data' => ['healthInsurance' => 'Beispielkasse'], 'version' => 2];
    $store->data[3] = ['data' => ['healthInsurance' => 'Beispielkasse BQ'], 'version' => 1];
    $store->data[4] = ['data' => ['healthInsurance' => 'Nicht sichtbar'], 'version' => 1];
    $workflow = new HiringWorkflowService(new HiringMasterDataService());

    $list = $workflow->payrollList($store);
    assertSame(1, count($list));
    assertSame('Beispielkasse', $list[0]['hiringData']['healthInsurance']);
    assertSame(2, $list[0]['hiringDataVersion']);
    assertSame(false, array_key_exists('interviews', $list[0]));
    assertSame(0, $store->dossierReads);
});

TestRunner::test('first-guide access can be ended manually with optimistic locking', static function (): void {
    $store = new MemoryHiringStore();
    $workflow = new HiringWorkflowService(new HiringMasterDataService());
    $changed = $workflow->setFirstGuideAccess($store, 1, false, 4, 'hr-user');
    assertSame(false, $changed['firstGuideAccess']);
    assertThrows(
        static fn () => $workflow->setFirstGuideAccess($store, 1, true, 4, 'hr-user'),
        ConflictException::class,
    );
});
