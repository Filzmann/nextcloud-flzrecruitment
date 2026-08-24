<?php

declare(strict_types=1);

namespace OCA\FilzmannDataProtection\PublicApi\V1;

use DomainException;
use OCA\FilzmannDataProtection\Service\PersonalDataProviderRegistry;
use OCP\EventDispatcher\Event;

final class RegisterPersonalDataProvidersEvent extends Event {
    private PersonalDataProviderRegistry $registry;

    /** @var array<string, string> */
    private array $registrationFailures = [];

    public function __construct() {
        parent::__construct();
        $this->registry = new PersonalDataProviderRegistry();
    }

    public function register(PersonalDataProvider $provider): void {
        $appId = $provider->descriptor()->appId();

        try {
            $this->registry->register($provider);
        } catch (DomainException) {
            $this->registrationFailures[$appId] = 'Provider incompatible.';
        }
    }

    /** @return array<string, PersonalDataProvider> */
    public function providers(): array {
        return array_diff_key($this->registry->snapshot(), $this->registrationFailures);
    }

    /** @return array<string, string> */
    public function registrationFailures(): array {
        return $this->registrationFailures;
    }
}
