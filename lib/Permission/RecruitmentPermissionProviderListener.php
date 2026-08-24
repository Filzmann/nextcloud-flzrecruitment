<?php

declare(strict_types=1);

namespace OCA\Recruitment\Permission;

use OCA\FilzmannPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;

final class RecruitmentPermissionProviderListener {
    public function __construct(private RecruitmentPermissionProvider $provider) {}

    public function handle(object $event): void {
        if ($event instanceof RegisterPermissionProvidersEvent) {
            $event->register($this->provider);
        }
    }
}
