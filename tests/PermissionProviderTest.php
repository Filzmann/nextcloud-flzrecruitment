<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\PublicApi\V1 {
    interface PermissionProvider { public function descriptor(): PermissionProviderDescriptor; public function collect(): PermissionProviderResult; }
    final class PermissionProviderDescriptor {
        public function __construct(public string $appId, public string $displayName, public string $version, public array $capabilities) {}
    }
    final class PermissionCondition {
        private function __construct(public string $operator, public ?string $groupId = null, public array $children = []) {}
        public static function group(string $groupId): self { return new self('group', $groupId); }
        public static function all(array $children): self { return new self('all', null, $children); }
        public static function nextcloudAdmin(): self { return new self('nextcloud-admin'); }
    }
    final class PermissionRule {
        public function __construct(
            public string $objectType,
            public string $objectName,
            public string $detail,
            public string $permission,
            public string $permissionLabel,
            public string $effect,
            public string $scope,
            public PermissionCondition $condition,
            public string $source,
            public string $confidence,
        ) {}
    }
    final class PermissionProviderResult {
        public function __construct(public array $rules, public bool $complete = true, public array $warnings = []) {}
    }
    final class RegisterPermissionProvidersEvent {
        public array $providers = [];
        public function register(PermissionProvider $provider): void { $this->providers[] = $provider; }
    }
}

namespace RecruitmentTests {
    use OCA\FilzmannPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
    use OCA\LocalBase\Organization\AdOrganizationSnapshot;
    use OCA\Recruitment\Permission\RecruitmentPermissionProvider;
    use OCA\Recruitment\Permission\RecruitmentPermissionProviderListener;
    use OCA\Recruitment\Permission\RecruitmentPermissionSourceInterface;

    $snapshot = new AdOrganizationSnapshot(true, 4, [
        'staff_hr' => ['groupId' => 'ad-HR', 'label' => 'HR'],
        'payroll' => ['groupId' => 'ad-Payroll', 'label' => 'Lohn'],
        'eb' => ['groupId' => 'ad-EB', 'label' => 'Einsatzbegleitung'],
    ], [
        'north' => ['groupId' => 'ad-Area-North', 'label' => 'Nord'],
    ]);

    $source = new class($snapshot) implements RecruitmentPermissionSourceInterface {
        public function __construct(private AdOrganizationSnapshot $snapshot) {}
        public function organization(): AdOrganizationSnapshot { return $this->snapshot; }
        public function permissionSettings(): array {
            return ['firstGuideGroupId' => 'ad-first-guides', 'representatives' => []];
        }
    };
    $provider = new RecruitmentPermissionProvider($source);
    $result = $provider->collect();
    assertSame(true, $result->complete);

    $rules = static function (string $permission) use ($result): array {
        return array_values(array_filter(
            $result->rules,
            static fn($rule): bool => $rule->permission === $permission,
        ));
    };
    $conditions = static fn(string $permission): array => array_map(
        static fn($rule): string => $rule->condition->operator . ':' . ($rule->condition->groupId ?? ''),
        $rules($permission),
    );

    assertTrue(in_array('group:ad-HR', $conditions('recruitment.view_dossier'), true));
    assertTrue(in_array('group:ad-Payroll', $conditions('recruitment.edit_payroll_data'), true));
    assertTrue(!in_array('group:ad-HR', $conditions('recruitment.edit_payroll_data'), true));
    assertTrue(in_array('nextcloud-admin:', $conditions('recruitment.manage_delegations'), true));

    $firstGuide = array_values(array_filter(
        $rules('recruitment.view_dossier'),
        static fn($rule): bool => $rule->scope === 'released-first-guide-access:area:north',
    ))[0] ?? null;
    assertTrue($firstGuide !== null);
    assertSame('all', $firstGuide->condition->operator);
    assertSame(
        ['ad-first-guides', 'ad-EB', 'ad-Area-North'],
        array_map(static fn($condition): ?string => $condition->groupId, $firstGuide->condition->children),
    );

    $documentRules = $rules('recruitment.manage_documents');
    assertTrue(str_contains($documentRules[0]->detail ?? '', 'Dateiinhalte werden nicht untersucht'));

    $partialSource = new class($snapshot) implements RecruitmentPermissionSourceInterface {
        public function __construct(private AdOrganizationSnapshot $snapshot) {}
        public function organization(): AdOrganizationSnapshot { return $this->snapshot; }
        public function permissionSettings(): array {
            return [
                'firstGuideGroupId' => 'ad-first-guides',
                'representatives' => [[
                    'uid' => 'representative-a',
                    'capabilities' => ['view_dossier'],
                    'all' => true,
                    'areaKeys' => [],
                    'applicationIds' => [],
                ]],
            ];
        }
    };
    $partial = (new RecruitmentPermissionProvider($partialSource))->collect();
    assertSame(false, $partial->complete);
    assertTrue(str_contains(implode(' ', $partial->warnings), 'UID-basierte Vertretungsfreigaben'));
    assertTrue(!str_contains(serialize($partial->rules), 'representative-a'));

    $event = new RegisterPermissionProvidersEvent();
    (new RecruitmentPermissionProviderListener($provider))->handle($event);
    assertSame($provider, $event->providers[0] ?? null);

    $application = (string)file_get_contents(dirname(__DIR__) . '/lib/AppInfo/Application.php');
    assertTrue(str_contains($application, 'RegisterPermissionProvidersEvent::class, RecruitmentPermissionProviderListener::class'));
}
