<?php

declare(strict_types=1);

namespace OCA\FlzDataProtection\PublicApi\V1;

use InvalidArgumentException;

final class DataSubjectRef {
    public function __construct(private string $subjectType, private string $subjectId) {
        if (!preg_match('/^[a-z][a-z0-9-]{1,63}$/', $subjectType)) {
            throw new InvalidArgumentException('Ungültiger Subjekttyp.');
        }
        if ($subjectId === '' || strlen($subjectId) > 255) {
            throw new InvalidArgumentException('Ungültiger Subjektbezeichner.');
        }
    }

    public function subjectType(): string {
        return $this->subjectType;
    }

    public function subjectId(): string {
        return $this->subjectId;
    }
}

