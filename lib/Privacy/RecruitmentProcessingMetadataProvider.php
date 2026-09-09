<?php

declare(strict_types=1);

namespace OCA\Recruitment\Privacy;

use DomainException;
use InvalidArgumentException;
use JsonException;
use OCA\FilzmannDataProtection\PublicApi\V1\ProcessingMetadataCatalog;
use OCA\FilzmannDataProtection\PublicApi\V1\ProcessingMetadataProvider;
use OCA\FilzmannDataProtection\PublicApi\V1\ProcessingMetadataProviderDescriptor;
use OCA\Recruitment\AppInfo\Application;

final class RecruitmentProcessingMetadataProvider implements ProcessingMetadataProvider {
    public function descriptor(): ProcessingMetadataProviderDescriptor {
        return new ProcessingMetadataProviderDescriptor(Application::APP_ID, 'AD Recruitment', '1.0');
    }

    public function catalog(): ProcessingMetadataCatalog {
        $path = dirname(__DIR__, 2) . '/resources/privacy-processing.json';
        if (!is_file($path)) {
            throw new DomainException('Processing metadata catalog unavailable.');
        }

        try {
            $content = file_get_contents($path);
            if ($content === false) {
                throw new DomainException('Processing metadata catalog unavailable.');
            }
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new DomainException('Processing metadata catalog is invalid.');
            }
            return ProcessingMetadataCatalog::fromArray($payload);
        } catch (JsonException|InvalidArgumentException) {
            throw new DomainException('Processing metadata catalog is invalid.');
        }
    }
}
