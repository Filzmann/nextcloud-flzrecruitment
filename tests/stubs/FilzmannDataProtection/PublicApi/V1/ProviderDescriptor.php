<?php

declare(strict_types=1);

namespace OCA\FilzmannDataProtection\PublicApi\V1;

use InvalidArgumentException;

final class ProviderDescriptor {
    /**
     * @param list<string> $subjectTypes
     * @param list<string> $capabilities
     */
    public function __construct(
        private string $appId,
        private string $displayName,
        private string $contractVersion,
        private array $subjectTypes,
        private array $capabilities,
        private int $maxPageSize,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $appId)) {
            throw new InvalidArgumentException('Invalid provider app ID.');
        }
        if ($displayName === '') {
            throw new InvalidArgumentException('Provider display name must not be empty.');
        }
        if (!preg_match('/^[1-9][0-9]*\.[0-9]+$/', $contractVersion)) {
            throw new InvalidArgumentException('Invalid provider contract version.');
        }
        if (
            $subjectTypes === []
            || $capabilities === []
            || count($subjectTypes) !== count(array_unique($subjectTypes))
            || count($capabilities) !== count(array_unique($capabilities))
            || $maxPageSize < 1
            || $maxPageSize > 1000
        ) {
            throw new InvalidArgumentException('Invalid provider limits.');
        }
        foreach (array_merge($subjectTypes, $capabilities) as $identifier) {
            if (!is_string($identifier) || !preg_match('/^[a-z][a-z0-9-]{1,63}$/', $identifier)) {
                throw new InvalidArgumentException('Invalid provider capability or subject type.');
            }
        }
    }

    public function appId(): string {
        return $this->appId;
    }

    public function displayName(): string {
        return $this->displayName;
    }

    public function contractVersion(): string {
        return $this->contractVersion;
    }

    /** @return list<string> */
    public function subjectTypes(): array {
        return $this->subjectTypes;
    }

    public function supportsSubjectType(string $subjectType): bool {
        return in_array($subjectType, $this->subjectTypes, true);
    }

    /** @return list<string> */
    public function capabilities(): array {
        return $this->capabilities;
    }

    public function maxPageSize(): int {
        return $this->maxPageSize;
    }
}
