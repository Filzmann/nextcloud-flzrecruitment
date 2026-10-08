<?php

declare(strict_types=1);

use OCA\FlzRecruitment\Exception\ValidationException;
use OCA\FlzRecruitment\Service\DesiredWeeklyHoursService;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;

TestRunner::test('desired weekly hours share one normalizer for forms and mail suggestions', static function (): void {
    $service = new DesiredWeeklyHoursService();

    assertSame(
        ['desiredWeeklyHours' => 20.5, 'desiredWeeklyHoursMax' => null],
        $service->parseSuggestion('ca. 20,5 Stunden'),
    );
    assertSame(
        ['desiredWeeklyHours' => 25.0, 'desiredWeeklyHoursMax' => 30.0],
        $service->parseSuggestion('25 bis 30'),
    );
    assertSame(
        ['desiredWeeklyHours' => 25.0, 'desiredWeeklyHoursMax' => null],
        $service->normalize(25.0, 25.0),
    );
});

TestRunner::test('desired weekly hours reject malformed and invalid boundaries', static function (): void {
    $service = new DesiredWeeklyHoursService();

    foreach (['keine Angabe', '0', '81', '40-20'] as $value) {
        assertThrows(static fn() => $service->parseSuggestion($value), ValidationException::class);
    }
    assertThrows(static fn() => $service->normalize(null, 20.0), ValidationException::class);
});
