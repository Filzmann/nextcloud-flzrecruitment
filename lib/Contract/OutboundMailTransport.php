<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Contract;

interface OutboundMailTransport {
    public function send(string $recipient, string $subject, string $htmlBody, string $plainBody): void;
}
