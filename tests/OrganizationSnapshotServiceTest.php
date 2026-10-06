<?php

declare(strict_types=1);

namespace OCP\App {
    if (!interface_exists(IAppManager::class)) {
        interface IAppManager {
            /** @return array<string,string> */
            public function getAppInstalledVersions(bool $onlyEnabled = false): array;
            /** @return list<string> Deprecated Nextcloud alias for enabled apps. */
            public function getInstalledApps(): array;
            /** @return list<string> */
            public function getEnabledApps(): array;
        }
    }
}

namespace OCP {
    if (!class_exists(Server::class)) {
        final class Server {
            public static mixed $service = null;
            public static function get(string $name): mixed { return self::$service; }
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

namespace {
    use OCA\LocalBase\PublicApi\V1\OrganizationSnapshot as ProviderSnapshot;
    use OCA\Recruitment\Organization\OrganizationSnapshot;
    use OCA\Recruitment\Organization\OrganizationSnapshotService;
    use OCP\App\IAppManager;
    use Psr\Log\LoggerInterface;
    use RecruitmentTests\TestRunner;

    use function RecruitmentTests\assertSame;

    final class OrganizationApps implements IAppManager {
        /** @param list<string> $installed @param list<string> $enabled */
        public function __construct(private array $installed, private array $enabled) {}
        public function getAppInstalledVersions(bool $onlyEnabled = false): array {
            $apps = $onlyEnabled ? $this->enabled : $this->installed;
            return array_fill_keys($apps, '1.0.0');
        }
        public function getInstalledApps(): array { return $this->enabled; }
        public function getEnabledApps(): array { return $this->enabled; }
    }

    final class OrganizationLogger implements LoggerInterface {
        /** @var list<array{message:mixed,context:array<mixed>}> */
        public array $warnings = [];
        public function emergency($message, array $context = []): void {}
        public function alert($message, array $context = []): void {}
        public function critical($message, array $context = []): void {}
        public function error($message, array $context = []): void {}
        public function warning($message, array $context = []): void { $this->warnings[] = ['message' => $message, 'context' => $context]; }
        public function notice($message, array $context = []): void {}
        public function info($message, array $context = []): void {}
        public function debug($message, array $context = []): void {}
        public function log($level, $message, array $context = []): void {}
    }

    final class OrganizationAdapterUnderTest extends OrganizationSnapshotService {
        public function __construct(
            IAppManager $apps,
            OrganizationLogger $logger,
            private bool $contractAvailable,
            private ProviderSnapshot|\Throwable|null $providerSnapshot,
            private string $contractVersion = ProviderSnapshot::CONTRACT_VERSION,
        ) {
            parent::__construct($apps, $logger);
        }

        protected function providerContractAvailable(): bool { return $this->contractAvailable; }

        protected function readProviderSnapshot(): ProviderSnapshot {
            if ($this->providerSnapshot instanceof \Throwable) throw $this->providerSnapshot;
            if ($this->providerSnapshot === null) throw new \UnexpectedValueException('synthetic incompatible provider');
            return $this->providerSnapshot;
        }

        protected function providerContractVersion(ProviderSnapshot $snapshot): string {
            return $this->contractVersion;
        }
    }

    $validProvider = new ProviderSnapshot(true, 4, [
        'staff_hr' => ['groupId' => 'group-hr', 'label' => 'Personalreferat'],
        'eb' => ['groupId' => 'group-eb', 'label' => 'Einsatzbegleitung'],
    ], [
        'west' => ['groupId' => 'area-west', 'label' => 'West'],
    ]);

    TestRunner::test('enabled compatible Organization V1 is projected without a second source of truth', static function () use ($validProvider): void {
        $logger = new OrganizationLogger();
        $snapshot = (new OrganizationAdapterUnderTest(
            new OrganizationApps(['localbase'], ['localbase']),
            $logger,
            true,
            $validProvider,
        ))->snapshot();

        assertSame(OrganizationSnapshot::VALID, $snapshot->status());
        assertSame(true, $snapshot->isValid());
        assertSame('1.0', $snapshot->contractVersion());
        assertSame(4, $snapshot->definitionVersion());
        assertSame('group-hr', $snapshot->roleGroupId('staff_hr'));
        assertSame('area-west', $snapshot->areaGroupId('west'));
        assertSame([], $logger->warnings);
    });

    TestRunner::test('missing and disabled LocalBase expose distinct empty non-authoritative states', static function () use ($validProvider): void {
        $missing = (new OrganizationAdapterUnderTest(
            new OrganizationApps([], []), new OrganizationLogger(), true, $validProvider,
        ))->snapshot();
        $disabled = (new OrganizationAdapterUnderTest(
            new OrganizationApps(['localbase'], []), new OrganizationLogger(), true, $validProvider,
        ))->snapshot();

        assertSame(OrganizationSnapshot::MISSING, $missing->status());
        assertSame(OrganizationSnapshot::DISABLED, $disabled->status());
        assertSame([], $missing->roles());
        assertSame([], $disabled->areas());
    });

    TestRunner::test('old, incompatible and invalid providers fail closed without mappings', static function () use ($validProvider): void {
        $apps = new OrganizationApps(['localbase'], ['localbase']);
        $old = (new OrganizationAdapterUnderTest($apps, new OrganizationLogger(), false, $validProvider))->snapshot();
        $incompatible = (new OrganizationAdapterUnderTest($apps, new OrganizationLogger(), true, $validProvider, '2.0'))->snapshot();
        $invalid = (new OrganizationAdapterUnderTest(
            $apps,
            new OrganizationLogger(),
            true,
            new ProviderSnapshot(false, 4, [], []),
        ))->snapshot();

        assertSame(OrganizationSnapshot::INCOMPATIBLE, $old->status());
        assertSame(OrganizationSnapshot::INCOMPATIBLE, $incompatible->status());
        assertSame(OrganizationSnapshot::INVALID, $invalid->status());
        assertSame([], $old->roles());
        assertSame([], $incompatible->areas());
        assertSame([], $invalid->roles());
    });

    TestRunner::test('provider errors are diagnosable and cannot leak organization mappings', static function (): void {
        $logger = new OrganizationLogger();
        $failed = (new OrganizationAdapterUnderTest(
            new OrganizationApps(['localbase'], ['localbase']),
            $logger,
            true,
            new \RuntimeException('synthetic provider failure'),
        ))->snapshot();

        assertSame(OrganizationSnapshot::UNAVAILABLE, $failed->status());
        assertSame([], $failed->roles());
        assertSame([], $failed->areas());
        assertSame(1, count($logger->warnings));
        assertSame(false, str_contains((string)$logger->warnings[0]['message'], 'synthetic provider failure'));
    });
}
