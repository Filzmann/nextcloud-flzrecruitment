<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\Recruitment\Exception\ValidationException;
use OCA\Recruitment\Repository\RecruitmentRepository;

final class StatusMailService {
    public function __construct(
        private RecruitmentRepository $repository,
        private StatusMailWorkflow $workflow,
        private ApplicationStatusService $statuses,
        private StatusMailSettingsService $settings,
        private StatusMailBodyService $bodies,
    ) {}

    public function configuration(): array { return $this->repository->mailConfiguration() + ['settings' => $this->settings->settings()]; }

    public function createTemplate(string $name, string $subject, string $body, string $bodyFormat, string $actorUid): array {
        $name = trim($name); if ($name === '' || strlen($name) > 255) throw new ValidationException('Der Vorlagenname ist ungültig.');
        [$body, $bodyFormat] = $this->validatedBody($subject, $body, $bodyFormat);
        return $this->repository->createMailTemplate($name, trim($subject), $body, $bodyFormat, $actorUid);
    }

    public function reviseTemplate(int $id, string $name, string $subject, string $body, string $bodyFormat, bool $active, int $version, string $actorUid): array {
        $name = trim($name); if ($name === '' || strlen($name) > 255) throw new ValidationException('Der Vorlagenname ist ungültig.');
        [$body, $bodyFormat] = $this->validatedBody($subject, $body, $bodyFormat);
        return $this->repository->reviseMailTemplate($id, $name, trim($subject), $body, $bodyFormat, $active, $version, $actorUid);
    }

    public function configureRule(string $fromStatus, string $toStatus, int $templateId, bool $enabled, string $timing, int $version, string $actorUid): array {
        $this->statuses->targetStatus($fromStatus, $toStatus);
        $this->repository->mailTemplate($templateId);
        if (!in_array($timing, [StatusMailWorkflow::TIMING_IMMEDIATE, StatusMailWorkflow::TIMING_SCHEDULED, StatusMailWorkflow::TIMING_NEXT_MONDAY], true)) {
            throw new ValidationException('Die Standard-Versandplanung ist ungültig.');
        }
        return $this->repository->saveStatusMailRule($fromStatus, $toStatus, $templateId, $enabled, $timing, $version, $actorUid);
    }

    public function createTextBlock(string $label, string $text, string $actorUid): array {
        $label = trim($label); $text = trim($text);
        if ($label === '' || strlen($label) > 255 || $text === '') throw new ValidationException('Textblockbezeichnung und Text sind erforderlich.');
        return $this->repository->createMailTextBlock($label, $text, $actorUid);
    }

    public function drafts(int $applicationId): array { return $this->repository->mailDrafts($applicationId); }
    public function draft(int $id): array { return $this->repository->mailDraft($id); }

    public function saveDraft(int $id, string $subject, string $body, string $bodyFormat, int $version): array {
        $subject = trim($subject);
        if ($subject === '' || strlen($subject) > 998) throw new ValidationException('Betreff und Nachrichtentext sind erforderlich.');
        $html = $this->bodies->editableHtml($body, $bodyFormat);
        return $this->repository->saveMailDraft($id, $subject, $html, 'html', $version);
    }

    public function approveDraft(int $id, string $subject, string $body, string $bodyFormat, string $recipient, string $timing, ?string $scheduledAt, int $version, string $actorUid, string $jobKey): array {
        if (preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $jobKey) !== 1) throw new ValidationException('Die Versandauftragskennung ist ungültig.');
        $draft = $this->repository->mailDraft($id); $settings = $this->settings->settings();
        try { $requested = $scheduledAt === null || trim($scheduledAt) === '' ? null : new DateTimeImmutable($scheduledAt); }
        catch (\Throwable) { throw new ValidationException('Der Versandzeitpunkt ist ungültig.'); }
        $approved = $this->workflow->approveDraft(
            $draft, $subject, $this->bodies->editableHtml($body, $bodyFormat), $recipient, $timing, $requested,
            new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')),
            $settings['testMode'] ? $settings['testRecipient'] : '', 'html',
        );
        return $this->repository->approveMailDraft($id, $approved, $version, $actorUid, $jobKey);
    }

    public function cancelDraft(int $id, int $version): array { return $this->repository->cancelMailDraft($id, $version); }
    public function saveSettings(bool $testMode, string $testRecipient, int $revision): array { return $this->settings->save($testMode, $testRecipient, $revision); }

    /** @return array{string,string} */
    private function validatedBody(string $subject, string $body, string $bodyFormat): array {
        if (!in_array($bodyFormat, ['plain', 'html'], true)) throw new ValidationException('Das Format des Vorlagentexts ist ungültig.');
        $body = $bodyFormat === 'html' ? $this->bodies->sanitize($body) : trim($body);
        $this->workflow->renderDraft(
            ['id' => 1, 'revision' => 1, 'subject' => $subject, 'body' => $body, 'bodyFormat' => $bodyFormat],
            ['given_name' => 'Vorname', 'family_name' => 'Nachname', 'job_title' => 'Stelle'],
            'template-check@example.invalid',
        );
        return [$body, $bodyFormat];
    }
}
