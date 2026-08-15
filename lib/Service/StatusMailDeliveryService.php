<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\Recruitment\Contract\OutboundMailTransport;
use OCA\Recruitment\Contract\StatusMailOutboxStore;
use Psr\Log\LoggerInterface;

final class StatusMailDeliveryService {
    public function __construct(
        private StatusMailOutboxStore $store,
        private OutboundMailTransport $transport,
        private LoggerInterface $logger,
        private StatusMailBodyService $bodies,
    ) {}

    public function deliverOne(): bool {
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
        $job = $this->store->claimDueMailJob($now);
        if ($job === null) return false;
        try {
            $html = $this->bodies->editableHtml((string)$job['body'], (string)($job['bodyFormat'] ?? 'plain'));
            $this->transport->send((string)$job['recipient'], (string)$job['subject'], $html, $this->bodies->plainText($html));
            $this->store->markMailJobSent((int)$job['id'], (int)$job['draftId'], $now);
        } catch (\Throwable $error) {
            $attempts = max(1, (int)$job['attempts']);
            $minutes = min(1440, 2 ** min(10, $attempts));
            $this->store->markMailJobFailed((int)$job['id'], (int)$job['draftId'], $error::class, $now->modify("+{$minutes} minutes"));
            $this->logger->error('AD-Recruitment-Mailversand fehlgeschlagen.', [
                'exceptionClass' => $error::class, 'outboxJobId' => (int)$job['id'], 'attempt' => $attempts,
            ]);
        }
        return true;
    }
}
