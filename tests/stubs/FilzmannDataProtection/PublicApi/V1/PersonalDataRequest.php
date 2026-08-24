<?php

declare(strict_types=1);

namespace OCA\FilzmannDataProtection\PublicApi\V1;

use InvalidArgumentException;

final class PersonalDataRequest {
    private ?string $activeProviderAppId = null;

    /**
     * @param array<string, string> $providerCursors
     */
    public function __construct(
        private DataSubjectRef $subject,
        private string $language,
        private string $purpose,
        private int $pageLimit,
        private array $providerCursors,
    ) {
        if ($language === '' || $purpose === '' || $pageLimit < 1 || $pageLimit > 1000) {
            throw new InvalidArgumentException('Invalid personal data request.');
        }
        foreach ($providerCursors as $appId => $cursor) {
            if (
                !is_string($appId)
                || !preg_match('/^[a-z][a-z0-9_]{1,63}$/', $appId)
                || !is_string($cursor)
                || $cursor === ''
                || strlen($cursor) > 1024
            ) {
                throw new InvalidArgumentException('Invalid provider cursor.');
            }
        }
    }

    public function subject(): DataSubjectRef {
        return $this->subject;
    }

    public function language(): string {
        return $this->language;
    }

    public function purpose(): string {
        return $this->purpose;
    }

    public function pageLimit(): int {
        return $this->pageLimit;
    }

    public function cursor(): ?string {
        if ($this->activeProviderAppId === null) {
            return null;
        }

        return $this->providerCursors[$this->activeProviderAppId] ?? null;
    }

    public function forProvider(string $appId, int $pageLimit): self {
        $request = new self($this->subject, $this->language, $this->purpose, $pageLimit, $this->providerCursors);
        $request->activeProviderAppId = $appId;
        return $request;
    }
}
