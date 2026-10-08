<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;

use OCA\FlzRecruitment\Contract\InterviewStore;
use OCA\FlzRecruitment\Exception\NotFoundException;
use OCA\FlzRecruitment\Exception\ValidationException;

/**
 * Anwendungsfälle für versionierte Interviewinstanzen.
 */
final class InterviewService {
    public function __construct(private InterviewWorkflow $workflow) {
    }

    public function instantiate(
        InterviewStore $store,
        int $applicationId,
        int $templateId,
        string $actorUid,
    ): int {
        if (!$store->applicationExists($applicationId)) {
            throw new NotFoundException('Die Bewerbung wurde nicht gefunden.');
        }
        $snapshot = $this->workflow->snapshot($store->templateSnapshot($templateId));
        if (($snapshot['active'] ?? false) !== true) {
            throw new ValidationException('Eine deaktivierte Interviewvorlage kann nicht verwendet werden.');
        }

        return $store->createInterview([
            'applicationId' => $applicationId,
            'templateId' => $templateId,
            'templateRevision' => (int)$snapshot['revision'],
            'snapshot' => $snapshot,
            'actorUid' => $actorUid,
        ]);
    }

    /**
     * @param array<string,mixed> $answers
     * @return array<string,mixed>
     */
    public function saveDraft(
        InterviewStore $store,
        int $id,
        array $answers,
        int $expectedVersion,
    ): array {
        $interview = $store->interview($id);
        $status = $this->workflow->assertDraftWritable(
            (string)$interview['status'],
            (int)$interview['version'],
            $expectedVersion,
        );
        return $store->saveInterviewDraft($id, $answers, $status, $expectedVersion);
    }

    /**
     * @param array<string,mixed> $answers
     * @return array<string,mixed>
     */
    public function complete(
        InterviewStore $store,
        int $id,
        array $answers,
        int $expectedVersion,
    ): array {
        $interview = $store->interview($id);
        $this->workflow->assertDraftWritable(
            (string)$interview['status'],
            (int)$interview['version'],
            $expectedVersion,
        );
        $this->workflow->complete((array)$interview['snapshot'], $answers);
        return $store->completeInterview($id, $answers, $expectedVersion);
    }
}
