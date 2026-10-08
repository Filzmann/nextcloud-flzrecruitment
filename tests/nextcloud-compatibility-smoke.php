<?php

declare(strict_types=1);

use OCA\FlzRecruitment\Service\RecruitmentAccessService;
use OCA\FlzRecruitment\Repository\RecruitmentRepository;
use OCA\FlzRecruitment\Service\RecruitmentUseCaseService;
use OCA\FlzRecruitment\Service\DocumentReviewService;
use OCA\FlzRecruitment\Service\MailInboxService;

$objectStorageMail = static fn(): array => [
    'mailbox' => [
        'technicalKey' => 'fr08-object-storage',
        'label' => 'FR-08 Object Storage',
        'address' => 'fr08-object-storage@example.invalid',
    ],
    'externalMessageId' => '<fr08-object-storage@example.invalid>',
    'senderAddress' => 'synthetic-applicant@example.invalid',
    'recipients' => ['fr08-object-storage@example.invalid'],
    'subject' => 'Synthetischer Object-Storage-Nachweis',
    'receivedAt' => '2035-01-02T09:00:00+00:00',
    'bodyText' => 'Synthetischer, neutraler Integrationstest.',
    'attachments' => [[
        'originalName' => 'fr08-nachweis.pdf',
        'mimeType' => 'application/pdf',
        'content' => "%PDF-1.4\n% FR-08 synthetic object-storage proof\n%%EOF\n",
        'extractedText' => 'Synthetischer Nachweis ohne Personendaten.',
    ]],
];
$postgresqlAssignmentKey = 'fr04-postgresql-upgrade';
$postgresqlPersonEmail = 'fr04-postgresql@example.invalid';
$postgresqlTemplateName = 'FR-04 PostgreSQL Upgrade-Vorlage';

return [
    'providerRegistrations' => [
        'flz_data_protection' => [
            OCA\FlzDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent::class,
            OCA\FlzDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent::class,
        ],
        'flz_permission_matrix' => [
            OCA\FlzPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent::class,
        ],
    ],
    'uiPath' => '/index.php/apps/flzrecruitment/',
    'preGrantUiStatuses' => [200],
    'postGrantUiStatuses' => [200],
    'grantService' => OCA\FlzRecruitment\Service\TemporaryAdminAccessService::class,
    'permissionProbe' => static fn(string $uid): bool => OCP\Server::get(RecruitmentAccessService::class)
        ->canSomewhere(RecruitmentAccessService::VIEW),
    'apiSmokes' => [
        ['/index.php/apps/flzrecruitment/api/bootstrap', [200]],
    ],
    'postgresqlUpgradeSeed' => static function (string $uid) use ($postgresqlAssignmentKey, $postgresqlPersonEmail, $postgresqlTemplateName): void {
        $useCases = OCP\Server::get(RecruitmentUseCaseService::class);
        $repository = OCP\Server::get(RecruitmentRepository::class);
        $jobId = $useCases->createJob(
            'FR-04 PostgreSQL Stelle',
            'Synthetische Stelle',
            true,
            [$uid],
            [],
            $postgresqlAssignmentKey,
            false,
            'other',
            '',
            '',
            null,
            null,
            null,
            'Berlin',
        );
        $personId = $useCases->createPerson('Synthetisch', 'PostgreSQL', $postgresqlPersonEmail, '');
        $applicationId = $useCases->createApplication(
            $personId,
            $jobId,
            'manual',
            '2036-03-04',
            $uid,
            null,
            null,
        );
        $application = $repository->findApplication($applicationId);
        if ($application['desiredWeeklyHours'] !== null || $application['desiredWeeklyHoursMax'] !== null) {
            throw new RuntimeException('Der synthetische Recruitment-NULL-Bestand wurde nicht korrekt angelegt.');
        }

        $template = $repository->createMailTemplate(
            $postgresqlTemplateName,
            'Synthetischer Betreff',
            'Synthetischer Inhalt',
            'plain',
            $uid,
        );
        try {
            $repository->reviseMailTemplate(
                (int)$template['id'],
                $postgresqlTemplateName,
                'Darf nicht gespeichert werden',
                'Darf nicht gespeichert werden',
                'plain',
                true,
                999,
                $uid,
            );
            throw new RuntimeException('Der Recruitment-Konfliktfall hat unerwartet committed.');
        } catch (OCA\FlzRecruitment\Exception\ConflictException) {
            // Die optimistische Sperre muss die Transaktion ohne Teilmutation zurückrollen.
        }
        $unchanged = $repository->mailTemplate((int)$template['id']);
        if ((int)$unchanged['version'] !== 1 || (string)$unchanged['subject'] !== 'Synthetischer Betreff') {
            throw new RuntimeException('Der Recruitment-Konfliktfall hinterließ eine Teilmutation.');
        }
    },
    'postgresqlUpgradeVerify' => static function (string $uid) use ($postgresqlAssignmentKey, $postgresqlPersonEmail, $postgresqlTemplateName): void {
        $repository = OCP\Server::get(RecruitmentRepository::class);
        $overview = $repository->overview();
        $jobs = array_values(array_filter(
            $overview['jobs'],
            static fn(array $job): bool => (string)$job['assignmentKey'] === $postgresqlAssignmentKey,
        ));
        $people = array_values(array_filter(
            $overview['people'],
            static fn(array $person): bool => (string)$person['email'] === $postgresqlPersonEmail,
        ));
        if (count($jobs) !== 1 || count($people) !== 1) {
            throw new RuntimeException('Recruitment-Stelle oder -Person fehlt nach dem PostgreSQL-Upgrade.');
        }
        $applications = array_values(array_filter(
            $overview['applications'],
            static fn(array $application): bool => (int)$application['jobId'] === (int)$jobs[0]['id']
                && (int)$application['personId'] === (int)$people[0]['id'],
        ));
        if (count($applications) !== 1
            || $applications[0]['desiredWeeklyHours'] !== null
            || $applications[0]['desiredWeeklyHoursMax'] !== null) {
            throw new RuntimeException('Die synthetische Recruitment-Bewerbung wurde beim PostgreSQL-Upgrade verändert.');
        }
        $templates = array_values(array_filter(
            $repository->mailConfiguration()['templates'],
            static fn(array $template): bool => (string)$template['name'] === $postgresqlTemplateName,
        ));
        if (count($templates) !== 1
            || (int)$templates[0]['version'] !== 1
            || (string)$templates[0]['subject'] !== 'Synthetischer Betreff') {
            throw new RuntimeException('Die transaktionale Recruitment-Mailvorlage wurde beim PostgreSQL-Upgrade verändert.');
        }
    },
    'objectStorageWebSetup' => static function (string $uid) use ($objectStorageMail): void {
        $result = OCP\Server::get(MailInboxService::class)->import($objectStorageMail(), $uid);
        if (($result['imported'] ?? false) !== true || count($result['message']['attachments'] ?? []) !== 1) {
            throw new RuntimeException('Der Recruitment-Mailimport hat den synthetischen PDF-Anhang nicht angelegt.');
        }
    },
    'objectStorageJobVerify' => static function (string $uid) use ($objectStorageMail): void {
        $result = OCP\Server::get(MailInboxService::class)->import($objectStorageMail(), $uid);
        if (($result['imported'] ?? true) !== false || count($result['message']['attachments'] ?? []) !== 1) {
            throw new RuntimeException('Der getrennte Jobprozess hat den Recruitment-Import nicht idempotent wiedererkannt.');
        }
        $attachmentId = (int)$result['message']['attachments'][0]['id'];
        $document = OCP\Server::get(DocumentReviewService::class)->document($attachmentId);
        if (($document['content'] ?? '') !== ($objectStorageMail()['attachments'][0]['content'] ?? '')) {
            throw new RuntimeException('Der Recruitment-PDF-Anhang ist im Jobprozess nicht unverändert aus AppData lesbar.');
        }
    },
];
