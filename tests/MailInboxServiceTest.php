<?php

declare(strict_types=1);

use OCA\FlzRecruitment\Contract\MailAttachmentStorage;
use OCA\FlzRecruitment\Contract\MailInboxStore;
use OCA\FlzRecruitment\Contract\PdfTextExtractor;
use OCA\FlzRecruitment\Exception\ConflictException;
use OCA\FlzRecruitment\Exception\ValidationException;
use OCA\FlzRecruitment\Service\ApplicationMailFieldExtractor;
use OCA\FlzRecruitment\Service\LocalPdfTextExtractor;
use OCA\FlzRecruitment\Service\MailInboxService;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;
use function RecruitmentTests\assertTrue;

final class MemoryMailAttachmentStorage implements MailAttachmentStorage {
    /** @var array<string,string> */
    public array $files = [];
    public function store(int $messageId, string $contentHash, string $content): string {
        $path = "mail-inbox/message-{$messageId}/{$contentHash}.pdf";
        $this->files[$path] = $content;
        return $path;
    }
    public function read(string $storagePath): string { return $this->files[$storagePath]; }
}

final class MemoryPdfTextExtractor implements PdfTextExtractor {
    public array $contents = [];
    public function available(): bool { return true; }
    public function engineLabel(): string { return 'Test-PDF-Extraktor'; }
    public function extract(string $pdfContent): string {
        $this->contents[] = $pdfContent;
        return "Wohnort Berlin\nDeutsch C1";
    }
}

final class MemoryMailInboxStore implements MailInboxStore {
    /** @var array<int,array<string,mixed>> */
    public array $messages = [];
    /** @var array<string,int> */
    private array $mailboxes = [];
    public int $writeCount = 0;
    public array $hiringData = [10 => ['city' => 'Potsdam']];
    public array $applicationData = [
        10 => ['previousExperience' => '', 'germanLanguageLevel' => 'B1', 'desiredWeeklyHours' => null, 'desiredWeeklyHoursMax' => null],
        11 => ['desiredWeeklyHours' => 20.0, 'desiredWeeklyHoursMax' => 25.0],
    ];
    public array $people = [];
    public array $applications = [];
    public bool $failAtomicCreation = false;

    public function ensureMailbox(array $mailbox): int {
        return $this->mailboxes[$mailbox['technicalKey']] ??= count($this->mailboxes) + 1;
    }
    public function findMessageByIdentity(int $mailboxId, ?string $externalMessageId, string $contentHash): ?array {
        foreach ($this->messages as $message) {
            if ($message['mailboxId'] === $mailboxId
                && ($message['contentHash'] === $contentHash || ($externalMessageId !== null && $message['externalMessageId'] === $externalMessageId))) {
                return $message;
            }
        }
        return null;
    }
    public function createInboxMessage(array $message): int {
        $id = count($this->messages) + 1;
        $this->messages[$id] = ['id' => $id, 'version' => 1, 'attachments' => [], 'audit' => [['fromState' => '', 'toState' => 'new']]] + $message;
        $this->writeCount++;
        return $id;
    }
    public function addInboxAttachment(int $messageId, array $attachment): int {
        $this->messages[$messageId]['attachments'][] = $attachment;
        $this->writeCount++;
        return count($this->messages[$messageId]['attachments']);
    }
    public function markInboxImportError(int $messageId, string $actorUid): void { $this->messages[$messageId]['state'] = 'error'; }
    public function inboxMessage(int $messageId): array { return $this->messages[$messageId]; }
    public function inboxMessages(): array {
        return array_values(array_filter($this->messages, static fn(array $message): bool => ($message['applicationId'] ?? null) === null));
    }
    public function inboxMessagesForApplication(int $applicationId): array {
        return array_values(array_filter($this->messages, static fn(array $message): bool => ($message['applicationId'] ?? null) === $applicationId));
    }
    public function inboxApplicationExists(int $applicationId): bool { return in_array($applicationId, [10, 11], true); }
    public function inboxJobExists(int $jobId): bool { return $jobId === 4; }
    public function createAndAssignInboxApplication(
        int $messageId,
        int $expectedVersion,
        string $actorUid,
        array $person,
        array $application,
        array $hiringDefaults = [],
        array $applicationDefaults = [],
    ): array {
        $snapshot = [$this->messages, $this->people, $this->applications, $this->hiringData, $this->applicationData];
        try {
            $message = $this->messages[$messageId];
            if ($message['version'] !== $expectedVersion || !in_array($message['state'], ['new', 'unclear'], true)) {
                throw new ConflictException('Konflikt');
            }
            $personId = count($this->people) + 1;
            $applicationId = count($this->applications) + 100;
            $this->people[$personId] = ['id' => $personId] + $person;
            $this->applications[$applicationId] = ['id' => $applicationId, 'personId' => $personId] + $application;
            if ($this->failAtomicCreation) throw new ConflictException('Erzwungener Testkonflikt');
            $this->applicationData[$applicationId] = [
                'desiredWeeklyHours' => $application['desiredWeeklyHours'],
                'desiredWeeklyHoursMax' => $application['desiredWeeklyHoursMax'],
            ];
            $assigned = $this->assignInboxMessage($messageId, $applicationId, $expectedVersion, $actorUid, $hiringDefaults, $applicationDefaults);
            return ['personId' => $personId, 'applicationId' => $applicationId, 'message' => $assigned];
        } catch (\Throwable $error) {
            [$this->messages, $this->people, $this->applications, $this->hiringData, $this->applicationData] = $snapshot;
            throw $error;
        }
    }
    public function assignInboxMessage(int $messageId, int $applicationId, int $expectedVersion, string $actorUid, array $hiringDefaults = [], array $applicationDefaults = []): array {
        $message = $this->messages[$messageId];
        if ($message['version'] !== $expectedVersion) throw new ConflictException('Konflikt');
        $this->messages[$messageId]['state'] = 'assigned';
        $this->messages[$messageId]['applicationId'] = $applicationId;
        $this->messages[$messageId]['version']++;
        $this->messages[$messageId]['audit'][] = ['fromState' => $message['state'], 'toState' => 'assigned', 'actorUid' => $actorUid];
        foreach ($hiringDefaults as $field => $value) {
            if (($this->hiringData[$applicationId][$field] ?? '') === '') $this->hiringData[$applicationId][$field] = $value;
        }
        foreach ($applicationDefaults as $field => $value) {
            if (($this->applicationData[$applicationId][$field] ?? '') === '') $this->applicationData[$applicationId][$field] = $value;
        }
        return $this->messages[$messageId];
    }
    public function ignoreInboxMessage(int $messageId, int $expectedVersion, string $actorUid): array {
        $message = $this->messages[$messageId];
        if ($message['version'] !== $expectedVersion) throw new ConflictException('Konflikt');
        $this->messages[$messageId]['state'] = 'ignored';
        $this->messages[$messageId]['applicationId'] = null;
        $this->messages[$messageId]['version']++;
        $this->messages[$messageId]['audit'][] = ['fromState' => $message['state'], 'toState' => 'ignored', 'actorUid' => $actorUid];
        return $this->messages[$messageId];
    }
}

/** @return array<string,mixed> */
function syntheticMail(array $overrides = []): array {
    return array_replace_recursive([
        'mailbox' => ['technicalKey' => 'website', 'label' => 'Website-Bewerbungen', 'address' => 'bewerbung@example.invalid'],
        'externalMessageId' => '<demo-1@example.invalid>',
        'senderAddress' => 'alex@example.invalid',
        'senderName' => 'Alex Beispiel',
        'recipients' => ['bewerbung@example.invalid'],
        'subject' => 'Bewerbung Assistenz',
        'receivedAt' => '2026-08-02T09:00:00+02:00',
        'bodyText' => "Name: Alex Beispiel\nE-Mail: alex@example.invalid\nIch bewerbe mich als: Assistenz",
        'attachments' => [['originalName' => 'Bewerbung Alex.pdf', 'mimeType' => 'application/pdf', 'content' => "%PDF-1.4\nsynthetisch"]],
    ], $overrides);
}

TestRunner::test('mail inbox imports valid PDF applications idempotently and keeps originals immutable', static function (): void {
    $store = new MemoryMailInboxStore();
    $files = new MemoryMailAttachmentStorage();
    $service = new MailInboxService(new ApplicationMailFieldExtractor(), $store, $files, new MemoryPdfTextExtractor());
    $first = $service->import(syntheticMail(), 'importer');
    $duplicate = $service->import(syntheticMail(), 'importer');

    assertSame(true, $first['imported']);
    assertSame(false, $duplicate['imported']);
    assertSame(1, count($store->messages));
    assertSame(2, $store->writeCount);
    assertSame('new', $first['message']['state']);
    assertSame('assistance', $first['message']['fieldSuggestions']['jobCategory']);
    $path = array_key_first($files->files);
    assertTrue($path !== null && preg_match('#^mail-inbox/message-1/[a-f0-9]{64}\.pdf$#', $path) === 1);
    assertSame("%PDF-1.4\nsynthetisch", $files->files[$path]);
});

TestRunner::test('mail inbox extracts attached PDF text before scanning standard fields', static function (): void {
    $store = new MemoryMailInboxStore();
    $pdfText = new MemoryPdfTextExtractor();
    $service = new MailInboxService(
        new ApplicationMailFieldExtractor(),
        $store,
        new MemoryMailAttachmentStorage(),
        $pdfText,
    );
    $message = $service->import(syntheticMail(), 'importer')['message'];
    assertSame(["%PDF-1.4\nsynthetisch"], $pdfText->contents);
    assertSame(['value' => 'Berlin', 'source' => 'resume_text_keyword'], $message['fieldSuggestions']['location']);
    assertSame(['value' => 'C1', 'source' => 'resume_text_keyword'], $message['fieldSuggestions']['germanLanguageLevel']);
});

TestRunner::test('mail inbox remains functional when local PDF extraction is unavailable', static function (): void {
    $store = new MemoryMailInboxStore();
    $files = new MemoryMailAttachmentStorage();
    $service = new MailInboxService(
        new ApplicationMailFieldExtractor(),
        $store,
        $files,
        new LocalPdfTextExtractor('/definitely/not/installed/pdftotext'),
    );

    $result = $service->import(syntheticMail(), 'importer');

    assertSame(true, $result['imported']);
    assertSame(1, count($result['message']['attachments']));
    assertSame("%PDF-1.4\nsynthetisch", reset($files->files));
    assertSame(false, isset($result['message']['fieldSuggestions']['location']), 'Ohne Engine dürfen keine PDF-basierten Vorschläge erfunden werden.');
});

TestRunner::test('mail inbox rejects non-PDF and oversized attachments before persistence', static function (): void {
    $store = new MemoryMailInboxStore();
    $files = new MemoryMailAttachmentStorage();
    $service = new MailInboxService(new ApplicationMailFieldExtractor(), $store, $files, new MemoryPdfTextExtractor());
    assertThrows(static fn() => $service->import(syntheticMail(['attachments' => [[
        'originalName' => 'bewerbung.txt', 'mimeType' => 'text/plain', 'content' => 'not a pdf',
    ]]]), 'importer'), ValidationException::class);
    assertSame([], $store->messages);
    assertSame([], $files->files);
});

TestRunner::test('mail assignment is versioned, audited and limited to assignable states', static function (): void {
    $store = new MemoryMailInboxStore();
    $service = new MailInboxService(new ApplicationMailFieldExtractor(), $store, new MemoryMailAttachmentStorage(), new MemoryPdfTextExtractor());
    $message = $service->import(syntheticMail(), 'importer')['message'];
    $assigned = $service->assign($message['id'], 10, 1, 'hr-user');
    assertSame('assigned', $assigned['state']);
    assertSame(10, $assigned['applicationId']);
    assertSame('hr-user', $assigned['audit'][1]['actorUid']);
    assertSame([], $service->messages(), 'Assigned mail must disappear from the globally managed unassigned inbox.');
    assertSame(1, count($service->messagesForApplication(10)), 'Assigned mail must remain available through its dossier scope.');
    assertThrows(static fn() => $service->assign($message['id'], 11, 1, 'hr-user'), ConflictException::class);
    $original = $store->messages[$message['id']];
    assertThrows(static fn() => $service->ignore($message['id'], 2, 'hr-user'), ValidationException::class);
    assertSame($original['bodyText'], $store->messages[$message['id']]['bodyText']);
    assertSame($original['attachments'], $store->messages[$message['id']]['attachments']);
});

TestRunner::test('mail assignment prefills empty contract fields without replacing existing data', static function (): void {
    $store = new MemoryMailInboxStore();
    $service = new MailInboxService(new ApplicationMailFieldExtractor(), $store, new MemoryMailAttachmentStorage(), new MemoryPdfTextExtractor(), new \OCA\FlzRecruitment\Service\HiringMasterDataService());
    $message = $service->import(syntheticMail(['bodyText' => "Anrede: Frau\nTitel: Dr.\nE-Mail: alex@example.invalid\nTelefon: +49 30 123\nVerfügbar ab: 01.10.2026\nWohnort: Berlin"]), 'importer')['message'];
    $service->assign($message['id'], 10, 1, 'hr-user', [
        'salutation' => 'Frau',
        'title' => 'Dr.',
        'email' => 'alex@example.invalid',
        'availableFrom' => '01.10.2026',
        'location' => 'Berlin',
    ]);
    assertSame('Potsdam', $store->hiringData[10]['city']);
    assertSame('female', $store->hiringData[10]['salutation']);
    assertSame('dr', $store->hiringData[10]['title']);
    assertSame('alex@example.invalid', $store->hiringData[10]['privateEmail']);
    assertSame('2026-10-01', $store->hiringData[10]['plannedStartDate']);
});

TestRunner::test('mail assignment applies only explicitly accepted and corrected suggestions', static function (): void {
    $store = new MemoryMailInboxStore();
    $service = new MailInboxService(new ApplicationMailFieldExtractor(), $store, new MemoryMailAttachmentStorage(), new MemoryPdfTextExtractor(), new \OCA\FlzRecruitment\Service\HiringMasterDataService());
    $message = $service->import(syntheticMail(['bodyText' => "E-Mail: alex@example.invalid\nTelefon: +49 30 123\nVerfügbar ab: 01.10.2026\nWohnort: Berlin\nGewünschte Wochenstunden: 25 bis 30\nBerufserfahrung: Zwei Jahre Assistenz\nDeutschkenntnisse: C1"]), 'importer')['message'];

    $service->assign($message['id'], 10, 1, 'hr-user', [
        'email' => 'korrigiert@example.invalid',
        'availableFrom' => '2026-11-01',
        'previousExperience' => 'Drei Jahre Assistenz',
        'germanLanguageLevel' => 'C1',
        'desiredWeeklyHours' => '24-32',
    ]);

    assertSame('korrigiert@example.invalid', $store->hiringData[10]['privateEmail']);
    assertSame('2026-11-01', $store->hiringData[10]['plannedStartDate']);
    assertSame(null, $store->hiringData[10]['privatePhone'] ?? null);
    assertSame('Potsdam', $store->hiringData[10]['city']);
    assertSame('Drei Jahre Assistenz', $store->applicationData[10]['previousExperience']);
    assertSame('B1', $store->applicationData[10]['germanLanguageLevel']);
    assertSame(24.0, $store->applicationData[10]['desiredWeeklyHours']);
    assertSame(32.0, $store->applicationData[10]['desiredWeeklyHoursMax']);

    $service->assign($message['id'], 11, 2, 'hr-user', [
        'germanLanguageLevel' => 'native',
        'desiredWeeklyHours' => '40',
    ]);
    assertSame('native', $store->applicationData[11]['germanLanguageLevel']);
    assertSame(20.0, $store->applicationData[11]['desiredWeeklyHours']);
    assertSame(25.0, $store->applicationData[11]['desiredWeeklyHoursMax']);
});

TestRunner::test('mail assignment rejects manipulated suggestion fields without mutation', static function (): void {
    $store = new MemoryMailInboxStore();
    $service = new MailInboxService(new ApplicationMailFieldExtractor(), $store, new MemoryMailAttachmentStorage(), new MemoryPdfTextExtractor(), new \OCA\FlzRecruitment\Service\HiringMasterDataService());
    $message = $service->import(syntheticMail(), 'importer')['message'];
    $before = $store->messages[$message['id']];

    assertThrows(
        static fn() => $service->assign($message['id'], 10, 1, 'hr-user', ['taxId' => 'DEMO']),
        ValidationException::class,
    );
    assertThrows(
        static fn() => $service->assign($message['id'], 10, 1, 'hr-user', ['email' => 'keine-mailadresse']),
        ValidationException::class,
    );
    assertThrows(
        static fn() => $service->assign($message['id'], 10, 1, 'hr-user', ['germanLanguageLevel' => 'C3']),
        ValidationException::class,
    );
    assertThrows(
        static fn() => $service->assign($message['id'], 10, 1, 'hr-user', ['desiredWeeklyHours' => '40-20']),
        ValidationException::class,
    );
    assertSame($before, $store->messages[$message['id']]);
    assertSame(['city' => 'Potsdam'], $store->hiringData[10]);
});

TestRunner::test('inbox creates person, application and assignment atomically', static function (): void {
    $store = new MemoryMailInboxStore();
    $service = new MailInboxService(new ApplicationMailFieldExtractor(), $store, new MemoryMailAttachmentStorage(), new MemoryPdfTextExtractor());
    $message = $service->import(syntheticMail(['bodyText' => "Name: Ari Beispiel\nE-Mail: ari@example.invalid\nGewünschte Wochenstunden: 20-30\nBerufserfahrung: Zwei Jahre Assistenz"]), 'importer')['message'];

    $created = $service->createAndAssignApplication(
        $message['id'], 1, 4, 'Ari', 'Beispiel', 'ari@example.invalid', '+49 30 123', '',
        ['email' => 'ari@example.invalid', 'desiredWeeklyHours' => '20-30', 'previousExperience' => 'Drei Jahre Assistenz'],
        'hr-user',
    );

    assertSame(1, $created['personId']);
    assertSame(100, $created['applicationId']);
    assertSame('assigned', $created['message']['state']);
    assertSame('Ari', $store->people[1]['givenName']);
    assertSame(4, $store->applications[100]['jobId']);
    assertSame('email_import', $store->applications[100]['source']);
    assertSame(20.0, $store->applications[100]['desiredWeeklyHours']);
    assertSame(30.0, $store->applications[100]['desiredWeeklyHoursMax']);
    assertSame('Drei Jahre Assistenz', $store->applicationData[100]['previousExperience']);
    assertSame('ari@example.invalid', $store->hiringData[100]['privateEmail']);

    $before = [$store->people, $store->applications, $store->messages];
    assertThrows(
        static fn() => $service->createAndAssignApplication(
            $message['id'], 1, 4, 'Ari', 'Beispiel', 'ari@example.invalid', '', '', [], 'hr-user',
        ),
        ValidationException::class,
    );
    assertSame($before, [$store->people, $store->applications, $store->messages]);
});

TestRunner::test('inbox creation rejects unavailable jobs and rolls back a late conflict', static function (): void {
    $store = new MemoryMailInboxStore();
    $service = new MailInboxService(new ApplicationMailFieldExtractor(), $store, new MemoryMailAttachmentStorage(), new MemoryPdfTextExtractor());
    $message = $service->import(syntheticMail(), 'importer')['message'];
    $before = [$store->people, $store->applications, $store->messages];

    assertThrows(
        static fn() => $service->createAndAssignApplication(
            $message['id'], 1, 999, 'Ari', 'Beispiel', 'ari@example.invalid', '', '', [], 'hr-user',
        ),
        ValidationException::class,
    );
    assertSame($before, [$store->people, $store->applications, $store->messages]);

    assertThrows(
        static fn() => $service->createAndAssignApplication(
            $message['id'], 1, 5, 'Ari', 'Beispiel', 'ari@example.invalid', '', '', [], 'hr-user',
        ),
        ValidationException::class,
    );
    assertSame($before, [$store->people, $store->applications, $store->messages]);

    $store->failAtomicCreation = true;
    assertThrows(
        static fn() => $service->createAndAssignApplication(
            $message['id'], 1, 4, 'Ari', 'Beispiel', 'ari@example.invalid', '', '', [], 'hr-user',
        ),
        ConflictException::class,
    );
    assertSame($before, [$store->people, $store->applications, $store->messages]);
});
