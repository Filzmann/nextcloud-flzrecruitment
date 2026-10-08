<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Organization;

use InvalidArgumentException;
use OCA\LocalBase\PublicApi\V1\OrganizationSnapshot as ProviderSnapshot;
use OCA\LocalBase\PublicApi\V1\OrganizationSnapshotService as ProviderSnapshotService;
use OCP\App\IAppManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/** Resolves LocalBase Organization V1 lazily and fails closed at the runtime-app boundary. */
class OrganizationSnapshotService {
    private const PROVIDER_APP_ID = 'localbase';
    private const SUPPORTED_CONTRACT_VERSION = '1.0';

    public function __construct(
        private IAppManager $apps,
        private LoggerInterface $logger,
    ) {}

    public function snapshot(): OrganizationSnapshot {
        try {
            if (!array_key_exists(self::PROVIDER_APP_ID, $this->apps->getAppInstalledVersions(false))) {
                return OrganizationSnapshot::unavailable(OrganizationSnapshot::MISSING);
            }
            if (!in_array(self::PROVIDER_APP_ID, $this->apps->getEnabledApps(), true)) {
                return OrganizationSnapshot::unavailable(OrganizationSnapshot::DISABLED);
            }
        } catch (\Throwable $error) {
            return $this->providerFailure($error);
        }

        if (!$this->providerContractAvailable()) {
            return OrganizationSnapshot::unavailable(OrganizationSnapshot::INCOMPATIBLE);
        }

        try {
            $provider = $this->readProviderSnapshot();
            $contractVersion = $this->providerContractVersion($provider);
            $definitionVersion = $provider->definitionVersion();
            $checksum = $provider->checksum();
        } catch (InvalidArgumentException|UnexpectedValueException) {
            return OrganizationSnapshot::unavailable(OrganizationSnapshot::INCOMPATIBLE);
        } catch (\Throwable $error) {
            return $this->providerFailure($error);
        }

        if ($contractVersion !== self::SUPPORTED_CONTRACT_VERSION) {
            return OrganizationSnapshot::unavailable(
                OrganizationSnapshot::INCOMPATIBLE,
                $contractVersion,
                $definitionVersion,
                $checksum,
            );
        }
        if (!$provider->isValid()) {
            return OrganizationSnapshot::unavailable(
                OrganizationSnapshot::INVALID,
                $contractVersion,
                $definitionVersion,
                $checksum,
            );
        }

        return OrganizationSnapshot::valid(
            $contractVersion,
            $definitionVersion,
            $checksum,
            $provider->roles(),
            $provider->areas(),
        );
    }

    protected function providerContractAvailable(): bool {
        return class_exists(ProviderSnapshot::class) && class_exists(ProviderSnapshotService::class);
    }

    protected function readProviderSnapshot(): ProviderSnapshot {
        $provider = Server::get(ProviderSnapshotService::class);
        if (!$provider instanceof ProviderSnapshotService) {
            throw new UnexpectedValueException('LocalBase Organization V1 service is not resolvable.');
        }
        return $provider->snapshot();
    }

    protected function providerContractVersion(ProviderSnapshot $snapshot): string {
        return $snapshot->contractVersion();
    }

    private function providerFailure(\Throwable $error): OrganizationSnapshot {
        $this->logger->warning('Recruitment organization snapshot unavailable', [
            'app' => 'flzrecruitment',
            'provider' => self::PROVIDER_APP_ID,
            'exception' => $error,
        ]);
        return OrganizationSnapshot::unavailable(OrganizationSnapshot::UNAVAILABLE);
    }
}
