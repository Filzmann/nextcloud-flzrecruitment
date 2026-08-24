<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\Recruitment\Contract\CandidatePoolStore;

final class CandidatePoolMatchingService {
    /** @param array<string,mixed> $entry @param list<array<string,mixed>> $jobs @return list<array{jobId:int,reasons:list<string>}> */
    public function suggestionsFor(array $entry, array $jobs): array {
        if (($entry['status'] ?? '') !== 'active') return [];
        $result = [];
        foreach ($jobs as $job) {
            if (($job['active'] ?? false) !== true || ($job['professionCategory'] ?? '') !== ($entry['professionCategory'] ?? '')) continue;
            $reasons = ['Berufsgruppe stimmt überein.'];
            $entryMin = $entry['desiredWeeklyHours'] ?? null; $entryMax = $entry['desiredWeeklyHoursMax'] ?? $entryMin;
            $jobMin = $job['weeklyHoursMin'] ?? null; $jobMax = $job['weeklyHoursMax'] ?? $jobMin;
            if ($entryMin !== null && $jobMin !== null) {
                if ((float)$entryMin > (float)$jobMax || (float)$jobMin > (float)$entryMax) continue;
                $reasons[] = 'Gewünschter Stundenumfang überschneidet sich.';
            }
            $result[] = ['jobId' => (int)$job['id'], 'reasons' => $reasons];
        }
        return $result;
    }

    public function refresh(CandidatePoolStore $store): int {
        $created = 0; $jobs = $store->activeCandidatePoolJobs();
        foreach ($store->activeCandidatePoolEntries() as $entry) foreach ($this->suggestionsFor($entry, $jobs) as $suggestion) {
            if ($store->candidatePoolMatch((int)$entry['id'], $suggestion['jobId']) !== null) continue;
            $store->createCandidatePoolMatch(['entryId' => (int)$entry['id'], 'jobId' => $suggestion['jobId'], 'state' => 'suggested', 'reasons' => $suggestion['reasons']]);
            $created++;
        }
        return $created;
    }
}
