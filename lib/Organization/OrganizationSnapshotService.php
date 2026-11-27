<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Organization;

use InvalidArgumentException;
use OCA\FlzOrganization\PublicApi\V1\Contract;
use OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshot as ProviderSnapshot;
use OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshotService as ProviderSnapshotService;
use OCP\App\IAppManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;

/**
 * Resolves the standalone organization V1 contract lazily and fail-closed.
 *
 * LocalBase is deliberately not a fallback: flz_organization owns the
 * canonical organization state and is the only supported runtime provider.
 */
class OrganizationSnapshotService {
    private const PROVIDER_APP_ID = 'flz_organization';
    private const SUPPORTED_CONTRACT_VERSION = '1.0';

    public function __construct(
        private IAppManager $apps,
        private LoggerInterface $logger,
    ) {}

    public function snapshot(): OrganizationSnapshot {
        try {
            $installed = array_key_exists(self::PROVIDER_APP_ID, $this->apps->getAppInstalledVersions(false));
            if (!$installed) {
                return OrganizationSnapshot::unavailable(OrganizationSnapshot::MISSING);
            }
            if (!in_array(self::PROVIDER_APP_ID, $this->apps->getEnabledApps(), true)) {
                return OrganizationSnapshot::unavailable(OrganizationSnapshot::DISABLED);
            }
        } catch (\Throwable $error) {
            return $this->providerFailure($error);
        }

        if (!$this->isContractCompatible()) {
            return OrganizationSnapshot::unavailable(OrganizationSnapshot::INCOMPATIBLE);
        }

        try {
            $provider = $this->readProviderSnapshot();
            $contractVersion = $provider->contractVersion();
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
                is_string($contractVersion) ? $contractVersion : '',
                is_int($definitionVersion) ? $definitionVersion : 0,
                is_string($checksum) ? $checksum : '',
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

    protected function isContractCompatible(): bool {
        return class_exists(Contract::class)
            && Contract::supports(self::SUPPORTED_CONTRACT_VERSION)
            && class_exists(ProviderSnapshot::class)
            && class_exists(ProviderSnapshotService::class);
    }

    protected function readProviderSnapshot(): ProviderSnapshot {
        $provider = Server::get(ProviderSnapshotService::class);
        if (!$provider instanceof ProviderSnapshotService) {
            throw new UnexpectedValueException('Organization V1 service is not resolvable.');
        }
        return $provider->snapshot();
    }

    private function providerFailure(\Throwable $error): OrganizationSnapshot {
        $this->logger->warning('Recruitment organization snapshot unavailable', [
            'app' => 'flzrecruitment',
            'provider' => self::PROVIDER_APP_ID,
            'exceptionClass' => $error::class,
        ]);
        return OrganizationSnapshot::unavailable(OrganizationSnapshot::UNAVAILABLE);
    }
}
