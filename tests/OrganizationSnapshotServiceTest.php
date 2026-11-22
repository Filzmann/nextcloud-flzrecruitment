<?php

declare(strict_types=1);

namespace OCP\App {
    if (!interface_exists(IAppManager::class)) {
        interface IAppManager {
            /** @return array<string,string> */
            public function getAppInstalledVersions(bool $onlyEnabled = false): array;
            /** @return list<string> */
            public function getInstalledApps(): array;
            /** @return list<string> */
            public function getEnabledApps(): array;
        }
    }
}

namespace OCP {
    if (!class_exists(Server::class)) {
        final class Server
        {
            public static ?object $service = null;

            public static function get(string $name): object
            {
                if (self::$service === null)
                {
                    throw new \RuntimeException('Synthetic provider is unavailable.');
                }
                return self::$service;
            }
        }
    }
}

namespace Psr\Log {
    if (!interface_exists(LoggerInterface::class)) {
        interface LoggerInterface {
            public function emergency($message, array $context = []): void;
            public function alert($message, array $context = []): void;
            public function critical($message, array $context = []): void;
            public function error($message, array $context = []): void;
            public function warning($message, array $context = []): void;
            public function notice($message, array $context = []): void;
            public function info($message, array $context = []): void;
            public function debug($message, array $context = []): void;
            public function log($level, $message, array $context = []): void;
        }
    }
}

namespace OCA\FlzOrganization\PublicApi\V1 {
    if (!class_exists(Contract::class)) {
        final class Contract {
            public const APP_ID = 'flz_organization';
            public static function supports(string $version): bool { return $version === '1.0'; }
        }
        final class OrganizationSnapshot {
            public function __construct(
                private bool $valid,
                private int $definitionVersion,
                private array $roles,
                private array $areas,
            ) {}
            public function contractVersion(): string { return '1.0'; }
            public function isValid(): bool { return $this->valid; }
            public function definitionVersion(): int { return $this->definitionVersion; }
            public function checksum(): string { return 'flz-organization-checksum'; }
            public function roles(): array { return $this->valid ? $this->roles : []; }
            public function areas(): array { return $this->valid ? $this->areas : []; }
        }
        final class OrganizationSnapshotService
        {
            public function __construct(private OrganizationSnapshot $snapshot)
            {
            }

            public function snapshot(): OrganizationSnapshot
            {
                return $this->snapshot;
            }
        }
    }
}

namespace {
    use OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshot as ProviderSnapshot;
    use OCA\FlzRecruitment\Organization\OrganizationSnapshot;
    use OCA\FlzRecruitment\Organization\OrganizationSnapshotService;
    use OCP\App\IAppManager;
    use Psr\Log\LoggerInterface;
    use RecruitmentTests\TestRunner;

    use function RecruitmentTests\assertSame;

    final class OrganizationApps implements IAppManager {
        /** @param list<string> $installed @param list<string> $enabled */
        public function __construct(private array $installed, private array $enabled) {}
        public function getAppInstalledVersions(bool $onlyEnabled = false): array {
            return array_fill_keys($onlyEnabled ? $this->enabled : $this->installed, '1.0.0');
        }
        public function getInstalledApps(): array { return $this->enabled; }
        public function getEnabledApps(): array { return $this->enabled; }
    }

    final class OrganizationLogger implements LoggerInterface {
        public array $warnings = [];
        public function emergency($message, array $context = []): void {}
        public function alert($message, array $context = []): void {}
        public function critical($message, array $context = []): void {}
        public function error($message, array $context = []): void {}
        public function warning($message, array $context = []): void { $this->warnings[] = [$message, $context]; }
        public function notice($message, array $context = []): void {}
        public function info($message, array $context = []): void {}
        public function debug($message, array $context = []): void {}
        public function log($level, $message, array $context = []): void {}
    }

    final class OrganizationConsumerUnderTest extends OrganizationSnapshotService {
        public bool $compatible = true;
        public ProviderSnapshot|\Throwable|null $provider = null;
        protected function isContractCompatible(): bool { return $this->compatible; }
        protected function readProviderSnapshot(): ProviderSnapshot {
            if ($this->provider instanceof \Throwable) throw $this->provider;
            if (!$this->provider instanceof ProviderSnapshot) throw new \UnexpectedValueException('Missing provider.');
            return $this->provider;
        }
    }

    $roles = [
        'staff_hr' => ['groupId' => 'group-hr', 'label' => 'Personalreferat'],
        'eb' => ['groupId' => 'group-eb', 'label' => 'Einsatzbegleitung'],
    ];
    $areas = ['west' => ['groupId' => 'area-west', 'label' => 'West']];

    TestRunner::test('native public service resolution preserves standalone organization mappings', static function () use ($roles, $areas): void {
        \OCP\Server::$service = new \OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshotService(
            new ProviderSnapshot(true, 5, $roles, $areas),
        );
        try
        {
            $consumer = new OrganizationSnapshotService(
                new OrganizationApps(['flz_organization'], ['flz_organization']),
                new OrganizationLogger(),
            );
            $snapshot = $consumer->snapshot();
            assertSame(OrganizationSnapshot::VALID, $snapshot->status());
            assertSame('group-hr', $snapshot->roleGroupId('staff_hr'));
            assertSame('area-west', $snapshot->areaGroupId('west'));
        }
        finally
        {
            \OCP\Server::$service = null;
        }
    });

    TestRunner::test('compatible flz_organization V1 is the only organization source', static function () use ($roles, $areas): void {
        $logger = new OrganizationLogger();
        $consumer = new OrganizationConsumerUnderTest(new OrganizationApps(['flz_organization'], ['flz_organization']), $logger);
        $consumer->provider = new ProviderSnapshot(true, 5, $roles, $areas);
        $snapshot = $consumer->snapshot();

        assertSame(OrganizationSnapshot::VALID, $snapshot->status());
        assertSame('group-hr', $snapshot->roleGroupId('staff_hr'));
        assertSame('area-west', $snapshot->areaGroupId('west'));
        assertSame([], $logger->warnings);
    });

    TestRunner::test('LocalBase presence cannot replace a missing standalone provider', static function (): void {
        $consumer = new OrganizationConsumerUnderTest(new OrganizationApps(['localbase'], ['localbase']), new OrganizationLogger());
        assertSame(OrganizationSnapshot::MISSING, $consumer->snapshot()->status());
    });

    TestRunner::test('disabled and incompatible standalone providers fail closed before resolution', static function (): void {
        $disabled = new OrganizationConsumerUnderTest(new OrganizationApps(['flz_organization'], []), new OrganizationLogger());
        assertSame(OrganizationSnapshot::DISABLED, $disabled->snapshot()->status());

        $incompatible = new OrganizationConsumerUnderTest(new OrganizationApps(['flz_organization'], ['flz_organization']), new OrganizationLogger());
        $incompatible->compatible = false;
        assertSame(OrganizationSnapshot::INCOMPATIBLE, $incompatible->snapshot()->status());
    });

    TestRunner::test('invalid and failed snapshots expose no organization mappings', static function () use ($roles, $areas): void {
        $invalid = new OrganizationConsumerUnderTest(new OrganizationApps(['flz_organization'], ['flz_organization']), new OrganizationLogger());
        $invalid->provider = new ProviderSnapshot(false, 5, $roles, $areas);
        assertSame(OrganizationSnapshot::INVALID, $invalid->snapshot()->status());
        assertSame([], $invalid->snapshot()->roles());

        $logger = new OrganizationLogger();
        $failed = new OrganizationConsumerUnderTest(new OrganizationApps(['flz_organization'], ['flz_organization']), $logger);
        $failed->provider = new RuntimeException('synthetic secret detail');
        $failedSnapshot = $failed->snapshot();
        assertSame(OrganizationSnapshot::UNAVAILABLE, $failedSnapshot->status());
        assertSame([], $failedSnapshot->areas());
        assertSame(1, count($logger->warnings));
        assertSame(false, str_contains((string)$logger->warnings[0][0], 'synthetic secret detail'));
        assertSame([
            'app' => 'flzrecruitment',
            'provider' => 'flz_organization',
            'exceptionClass' => RuntimeException::class,
        ], $logger->warnings[0][1]);
    });
}
