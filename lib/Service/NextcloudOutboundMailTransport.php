<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use OCA\Recruitment\Contract\OutboundMailTransport;
use OCP\Mail\IMailer;

final class NextcloudOutboundMailTransport implements OutboundMailTransport {
    public function __construct(private IMailer $mailer) {}

    public function send(string $recipient, string $subject, string $htmlBody, string $plainBody): void {
        $message = $this->mailer->createMessage();
        $message->setTo([$recipient]);
        $message->setSubject($subject);
        $message->setHtmlBody($htmlBody);
        $message->setPlainBody($plainBody);
        $this->mailer->send($message);
    }
}
