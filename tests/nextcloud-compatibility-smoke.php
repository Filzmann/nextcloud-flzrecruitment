<?php

declare(strict_types=1);

use OCA\Recruitment\Service\RecruitmentAccessService;

return [
    'providerRegistrations' => [
        'filzmann_data_protection' => [
            OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent::class,
            OCA\FilzmannDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent::class,
        ],
        'filzmann_permission_matrix' => [
            OCA\FilzmannPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent::class,
        ],
    ],
    'uiPath' => '/index.php/apps/adrecruitment/',
    'preGrantUiStatuses' => [403],
    'postGrantUiStatuses' => [200],
    'grantService' => OCA\Recruitment\Service\TemporaryAdminAccessService::class,
    'permissionProbe' => static fn(string $uid): bool => OCP\Server::get(RecruitmentAccessService::class)
        ->canSomewhere(RecruitmentAccessService::VIEW),
    'apiSmokes' => [
        ['/index.php/apps/adrecruitment/api/bootstrap', [200]],
    ],
];
