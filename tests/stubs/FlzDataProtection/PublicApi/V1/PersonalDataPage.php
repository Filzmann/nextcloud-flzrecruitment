<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\PublicApi\V1;

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
            throw new InvalidArgumentException('Ungültiger Provider-Seitenstatus.');
        }
        foreach ($entries as $entry) {
            if (!$entry instanceof PersonalDataEntry) {
                throw new InvalidArgumentException('Ungültiger personenbezogener Dateneintrag.');
            }
        }
        foreach ($restrictions as $restriction) {
            if (!is_string($restriction) || trim($restriction) === '') {
                throw new InvalidArgumentException('Ungültige Provider-Einschränkung.');
            }
        }
        if ($status === 'partial' && $restrictions === []) {
            throw new InvalidArgumentException('Teil-Provider-Seiten erfordern eine Einschränkung.');
        }
        if ($status === 'not_applicable' && ($entries !== [] || $nextCursor !== null)) {
            throw new InvalidArgumentException('Nicht zutreffende Provider-Seiten dürfen weder Daten noch einen Cursor enthalten.');
        }
        if ($nextCursor !== null && ($nextCursor === '' || strlen($nextCursor) > 1024)) {
            throw new InvalidArgumentException('Ungültiger Provider-Cursor.');
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
