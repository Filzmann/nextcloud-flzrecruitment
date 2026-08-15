<?php

declare(strict_types=1);

namespace OCA\Recruitment\Command;

use OCA\Recruitment\Service\MailInboxService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Importiert neutrale Mailbeispiele über exakt dieselbe Fachgrenze wie ein späterer SMTP-Adapter. */
final class SeedInboxCommand extends Command {
    public function __construct(private MailInboxService $inbox) { parent::__construct(); }

    protected function configure(): void {
        $this->setName('adrecruitment:inbox:seed')
            ->setDescription('Importiert wiederholbar zwei synthetische Bewerbungs-Mails mit PDF-Anhängen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $imported = 0;
        foreach ($this->messages() as $message) {
            if ($this->inbox->import($message, 'demo-seed')['imported']) $imported++;
        }
        $output->writeln(sprintf('<info>%d von 2 synthetischen Eingangsnachrichten wurden neu importiert.</info>', $imported));
        return self::SUCCESS;
    }

    /** @return list<array<string,mixed>> */
    private function messages(): array {
        $mailbox = ['technicalKey' => 'website', 'label' => 'Website-Bewerbungen', 'address' => 'bewerbung@example.org'];
        $pdf = static fn(string $title): string => "%PDF-1.4\n% synthetisches Demo-Dokument\n1 0 obj << /Title ({$title}) >> endobj\n%%EOF\n";
        return [[
            'mailbox' => $mailbox,
            'externalMessageId' => '<demo-assistenz-20260802@example.org>',
            'senderAddress' => 'mara.muster@example.org',
            'recipients' => ['bewerbung@example.org'],
            'subject' => 'Bewerbung als Assistenz',
            'receivedAt' => '2026-08-02T09:15:00+02:00',
            'bodyText' => "Name: Mara Muster\nE-Mail: mara.muster@example.org\nTelefon: +49 30 5550101\nIch bewerbe mich als: Assistenz\nNachricht: Ich interessiere mich für die Assistenz.",
            'attachments' => [[
                'originalName' => 'Bewerbungsunterlagen.pdf',
                'mimeType' => 'application/pdf',
                'content' => $pdf('Bewerbungsunterlagen'),
                'extractedText' => "Berufserfahrung: Drei Jahre persönliche Assistenz\nDeutschkenntnisse: C1\nWohnort: Berlin",
            ]],
        ], [
            'mailbox' => $mailbox,
            'externalMessageId' => '<demo-freie-mail-20260802@example.org>',
            'senderAddress' => 'noah.beispiel@example.org',
            'recipients' => ['bewerbung@example.org'],
            'subject' => 'Interesse an einer Mitarbeit',
            'receivedAt' => '2026-08-02T10:30:00+02:00',
            'bodyText' => "Guten Tag,\n\nich interessiere mich für eine Mitarbeit in der Assistenz. Sie erreichen mich unter noah.beispiel@example.org.\n\nFreundliche Grüße",
            'attachments' => [[
                'originalName' => 'Lebenslauf.pdf',
                'mimeType' => 'application/pdf',
                'content' => $pdf('Lebenslauf'),
                'extractedText' => "Berufserfahrung: Zwei Jahre Pflegeassistenz\nDeutschkenntnisse: B2\nWohnort: Berlin",
            ]],
        ]];
    }
}
