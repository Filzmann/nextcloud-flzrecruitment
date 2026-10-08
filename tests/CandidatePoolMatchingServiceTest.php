<?php

declare(strict_types=1);

use OCA\FlzRecruitment\Service\CandidatePoolMatchingService;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;

TestRunner::test('matching creates transparent idempotent suggestions from category and hours only', static function (): void {
    $entry = ['id' => 1, 'status' => 'active', 'professionCategory' => 'assistance', 'desiredWeeklyHours' => 20.0, 'desiredWeeklyHoursMax' => 30.0, 'areaKeys' => [], 'version' => 1];
    $jobs = [
        ['id' => 9, 'active' => true, 'professionCategory' => 'assistance', 'weeklyHoursMin' => 25.0, 'weeklyHoursMax' => 35.0],
        ['id' => 10, 'active' => true, 'professionCategory' => 'administration', 'weeklyHoursMin' => 20.0, 'weeklyHoursMax' => 30.0],
    ];
    $matching = new CandidatePoolMatchingService();
    assertSame([['jobId' => 9, 'reasons' => ['Berufsgruppe stimmt überein.', 'Gewünschter Stundenumfang überschneidet sich.']]], $matching->suggestionsFor($entry, $jobs));
});
