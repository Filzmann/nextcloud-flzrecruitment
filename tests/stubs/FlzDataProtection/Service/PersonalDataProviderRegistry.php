<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\Service;

use DomainException;
use OCA\FlzDataProtection\PublicApi\V1\PersonalDataProvider;

final class PersonalDataProviderRegistry {
    public const CONTRACT_VERSION = '1.0';

    /** @var array<string, PersonalDataProvider> */
    private array $providers = [];

    public function register(PersonalDataProvider $provider): void {
        $descriptor = $provider->descriptor();
        $appId = $descriptor->appId();

        if ($descriptor->contractVersion() !== self::CONTRACT_VERSION) {
            throw new DomainException('Incompatible provider contract version.');
        }
        if (!in_array('personal-data', $descriptor->capabilities(), true)) {
            throw new DomainException('Personal-data capability is missing.');
        }
        if (isset($this->providers[$appId])) {
            throw new DomainException('Duplicate provider app ID.');
        }

        $this->providers[$appId] = $provider;
    }

    /** @return array<string, PersonalDataProvider> */
    public function snapshot(): array {
        return $this->providers;
    }
}
