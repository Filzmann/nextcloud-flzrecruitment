<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\PublicApi\V1;

use InvalidArgumentException;

final class PersonalDataEntry {
    /**
     * @param list<string> $recipientCategories
     * @param array<string, scalar|null> $attributes
     */
    public function __construct(
        private string $categoryId,
        private string $categoryLabel,
        private string $reference,
        private string $summary,
        private string $purpose,
        private string $source,
        private array $recipientCategories,
        private string $retention,
        private string $thirdCountryTransfer,
        private string $automatedDecision,
        private ?string $thirdPartyContentNotice,
        private array $attributes,
    ) {
        foreach ([$categoryId, $categoryLabel, $reference, $summary, $purpose, $source, $retention, $thirdCountryTransfer, $automatedDecision] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Required personal data metadata is missing.');
            }
        }
        if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $categoryId) || $recipientCategories === []) {
            throw new InvalidArgumentException('Invalid personal data category or recipients.');
        }
        if (strlen($reference) > 255 || preg_match('/[\x00-\x1F\x7F]/', $reference)) {
            throw new InvalidArgumentException('Invalid personal data reference.');
        }
        foreach ($recipientCategories as $recipientCategory) {
            if (!is_string($recipientCategory) || trim($recipientCategory) === '') {
                throw new InvalidArgumentException('Invalid recipient category.');
            }
        }
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || $name === '' || (!is_scalar($value) && $value !== null)) {
                throw new InvalidArgumentException('Invalid personal data attribute.');
            }
        }
    }

    public function categoryId(): string {
        return $this->categoryId;
    }

    public function categoryLabel(): string {
        return $this->categoryLabel;
    }

    public function reference(): string {
        return $this->reference;
    }

    public function summary(): string {
        return $this->summary;
    }

    public function purpose(): string {
        return $this->purpose;
    }

    public function source(): string {
        return $this->source;
    }

    /** @return list<string> */
    public function recipientCategories(): array {
        return $this->recipientCategories;
    }

    public function retention(): string {
        return $this->retention;
    }

    public function thirdCountryTransfer(): string {
        return $this->thirdCountryTransfer;
    }

    public function automatedDecision(): string {
        return $this->automatedDecision;
    }

    public function thirdPartyContentNotice(): ?string {
        return $this->thirdPartyContentNotice;
    }

    /** @return array<string, scalar|null> */
    public function attributes(): array {
        return $this->attributes;
    }
}
