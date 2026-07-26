<?php

declare(strict_types=1);

namespace OCA\Recruitment\Exception;

final class AccessDeniedException extends \RuntimeException {
    public function __construct() {
        parent::__construct('Keine Berechtigung.');
    }
}
