<?php

declare(strict_types=1);

namespace OCA\FilzmannDataProtection\PublicApi\V1;

use InvalidArgumentException;

final class PersonalDataPage {
    private const STATUSES = ['complete', 'partial', 'not_applicable'];

    /**
     * @param list<PersonalDataEntry> $entries
     * @param list<string> $restrictions
     */
    public function __construct(
        private string $status,
        private array $entries = [],
        private array $restrictions = [],
        private ?string $nextCursor = null,
    ) {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Invalid provider page status.');
        }
        foreach ($entries as $entry) {
            if (!$entry instanceof PersonalDataEntry) {
                throw new InvalidArgumentException('Invalid personal data entry.');
            }
        }
        foreach ($restrictions as $restriction) {
            if (!is_string($restriction) || trim($restriction) === '') {
                throw new InvalidArgumentException('Invalid provider restriction.');
            }
        }
        if ($status === 'partial' && $restrictions === []) {
            throw new InvalidArgumentException('Partial provider pages require a restriction.');
        }
        if ($status === 'not_applicable' && ($entries !== [] || $nextCursor !== null)) {
            throw new InvalidArgumentException('Not-applicable provider pages cannot contain data or a cursor.');
        }
        if ($nextCursor !== null && ($nextCursor === '' || strlen($nextCursor) > 1024)) {
            throw new InvalidArgumentException('Invalid provider cursor.');
        }
    }

    public function status(): string {
        return $this->status;
    }

    /** @return list<PersonalDataEntry> */
    public function entries(): array {
        return $this->entries;
    }

    /** @return list<string> */
    public function restrictions(): array {
        return $this->restrictions;
    }

    public function nextCursor(): ?string {
        return $this->nextCursor;
    }
}
