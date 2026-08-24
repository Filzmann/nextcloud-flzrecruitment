<?php

declare(strict_types=1);

use OCA\Recruitment\Service\ApplicationStatusService;
use OCA\Recruitment\Service\DefaultStatusMailTemplateCatalog;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertTrue;

TestRunner::test('every allowed status transition has one disabled editable HTML default', static function (): void {
    $statuses = new ApplicationStatusService();
    $catalog = new DefaultStatusMailTemplateCatalog();
    $defaults = $catalog->templates($statuses->transitions());

    $expectedCount = array_sum(array_map('count', $statuses->transitions()));
    assertSame($expectedCount, count($defaults));
    assertSame($expectedCount, count(array_unique(array_map(static fn(array $item): string => $item['fromStatus'] . '>' . $item['toStatus'], $defaults))));
    foreach ($defaults as $default) {
        assertSame(false, $default['enabled']);
        assertSame('html', $default['bodyFormat']);
        assertTrue(str_contains($default['body'], '{{given_name}}'));
        assertTrue(str_contains($default['body'], '{{family_name}}'));
        assertTrue(str_contains($default['body'], '{{job_title}}'));
    }
    $withdrawn = array_values(array_filter($defaults, static fn(array $item): bool => $item['toStatus'] === 'withdrawn'));
    assertTrue(count($withdrawn) > 1);
    assertSame(1, count(array_unique(array_column($withdrawn, 'name'))));
});
