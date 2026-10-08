<?php

declare(strict_types=1);

namespace Psr\Log { interface LoggerInterface { public function error(string $message, array $context = []): void; } }

namespace {
    use OCA\FlzRecruitment\Contract\OutboundMailTransport;
    use OCA\FlzRecruitment\Contract\StatusMailOutboxStore;
    use OCA\FlzRecruitment\Service\StatusMailDeliveryService;
    use OCA\FlzRecruitment\Service\StatusMailBodyService;
    use Psr\Log\LoggerInterface;
    use RecruitmentTests\TestRunner;

    use function RecruitmentTests\assertSame;

    final class MemoryStatusMailOutbox implements StatusMailOutboxStore {
        public ?array $job = null; public array $sent = []; public array $failed = [];
        public function claimDueMailJob(DateTimeImmutable $now): ?array { $job = $this->job; $this->job = null; return $job; }
        public function markMailJobSent(int $jobId, int $draftId, DateTimeImmutable $sentAt): void { $this->sent[] = [$jobId, $draftId]; }
        public function markMailJobFailed(int $jobId, int $draftId, string $errorCode, DateTimeImmutable $retryAt): void { $this->failed[] = [$jobId, $draftId, $errorCode, $retryAt]; }
    }

    final class MemoryMailTransport implements OutboundMailTransport {
        public array $sent = []; public bool $fail = false;
        public function send(string $recipient, string $subject, string $htmlBody, string $plainBody): void {
            if ($this->fail) throw new RuntimeException('synthetic transport failure');
            $this->sent[] = compact('recipient', 'subject', 'htmlBody', 'plainBody');
        }
    }

    TestRunner::test('due outbox mail is delivered once and marked sent', static function (): void {
        $store = new MemoryStatusMailOutbox(); $transport = new MemoryMailTransport();
        $store->job = ['id' => 4, 'draftId' => 9, 'attempts' => 1, 'recipient' => 'test@example.invalid', 'subject' => 'Betreff', 'body' => '<p><strong>Text</strong></p>', 'bodyFormat' => 'html'];
        $logger = new class implements LoggerInterface { public array $errors = []; public function error(string $message, array $context = []): void { $this->errors[] = [$message, $context]; } };
        $delivery = new StatusMailDeliveryService($store, $transport, $logger, new StatusMailBodyService());
        assertSame(true, $delivery->deliverOne()); assertSame(false, $delivery->deliverOne());
        assertSame([4, 9], $store->sent[0]); assertSame('test@example.invalid', $transport->sent[0]['recipient']);
        assertSame('<p><strong>Text</strong></p>', $transport->sent[0]['htmlBody']); assertSame('Text', $transport->sent[0]['plainBody']); assertSame([], $logger->errors);
    });

    TestRunner::test('transport failure schedules bounded retry without logging mail content', static function (): void {
        $store = new MemoryStatusMailOutbox(); $transport = new MemoryMailTransport(); $transport->fail = true;
        $store->job = ['id' => 5, 'draftId' => 10, 'attempts' => 2, 'recipient' => 'private@example.invalid', 'subject' => 'Privat', 'body' => 'Vertraulich'];
        $logger = new class implements LoggerInterface { public array $errors = []; public function error(string $message, array $context = []): void { $this->errors[] = [$message, $context]; } };
        assertSame(true, (new StatusMailDeliveryService($store, $transport, $logger, new StatusMailBodyService()))->deliverOne());
        assertSame(5, $store->failed[0][0]); assertSame(RuntimeException::class, $store->failed[0][2]);
        assertSame(['exceptionClass', 'outboxJobId', 'attempt'], array_keys($logger->errors[0][1]));
    });
}
