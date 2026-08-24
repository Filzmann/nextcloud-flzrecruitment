<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\Recruitment\Contract\HiringDataStore;
use OCA\Recruitment\Exception\ValidationException;

/** Orchestriert versionsgeschützte Einstellungsdaten und die datensparsame Lohnsicht. */
final class HiringWorkflowService {
    public function __construct(private HiringMasterDataService $masterData) {}

    /** @param array<string,mixed> $data
     *  @return array{data: array<string,mixed>, version: int}
     */
    public function save(HiringDataStore $store, int $applicationId, array $data, int $expectedVersion, string $actorUid): array {
        $context = $store->hiringContext($applicationId);
        $validated = array_replace($this->masterData->normalizeStored($store->hiringData($applicationId)['data']), $this->masterData->validatePersonnelInput($data));
        return $store->saveHiringData(
            $applicationId,
            $validated,
            $expectedVersion,
            $actorUid,
        );
    }

    public function savePayroll(HiringDataStore $store, int $applicationId, array $data, int $expectedVersion, string $actorUid): array {
        $context = $store->hiringContext($applicationId);
        if (!$this->masterData->isPayrollEligible($context['application'])) throw new ValidationException('LoBu-Stammdaten sind erst ab der Einstellungsfreigabe bearbeitbar.');
        $validated = array_replace($this->masterData->normalizeStored($store->hiringData($applicationId)['data']), $this->masterData->validatePayrollInput($data));
        return $store->saveHiringData($applicationId, $validated, $expectedVersion, $actorUid);
    }

    /** @return array{data: array<string,mixed>, version: int} */
    public function detail(HiringDataStore $store, int $applicationId): array {
        $store->findApplication($applicationId);
        $stored = $store->hiringData($applicationId);
        return ['data' => $this->masterData->personnelProjection($stored['data']), 'version' => $stored['version']];
    }

    /** @return list<array<string,mixed>> */
    public function payrollList(HiringDataStore $store): array {
        $result = [];
        foreach ($store->releasedHiringApplications() as $application) {
            $detail = $store->hiringContext((int)$application['id']);
            if (!$this->masterData->isPayrollEligible($detail['application'])) continue;
            $stored = $store->hiringData((int)$application['id']);
            $projection = $this->masterData->payrollProjection(
                $detail['application'],
                $detail['person'],
                $detail['job'],
                $stored['data'],
            );
            $projection['hiringDataVersion'] = $stored['version'];
            $result[] = $projection;
        }
        return $result;
    }

    /** @return array<string,mixed> */
    public function setFirstGuideAccess(
        HiringDataStore $store,
        int $applicationId,
        bool $enabled,
        int $expectedVersion,
        string $actorUid,
    ): array {
        $application = $store->findApplication($applicationId);
        if (!in_array((string)$application['status'], ['approved_for_hire', 'hired'], true)) {
            throw new ValidationException('Die Erstbegleitungsfreigabe ist nur für freigegebene Einstellungen zulässig.');
        }
        return $store->setFirstGuideAccess($applicationId, $enabled, $expectedVersion, $actorUid);
    }
}
