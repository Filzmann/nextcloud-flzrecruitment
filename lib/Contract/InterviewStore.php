<?php

declare(strict_types=1);

namespace OCA\Recruitment\Contract;

interface InterviewStore {
    public function applicationExists(int $id): bool;

    /** @return array<string,mixed> */
    public function templateSnapshot(int $id): array;

    /** @param array<string,mixed> $interview */
    public function createInterview(array $interview): int;

    /** @return array<string,mixed> */
    public function interview(int $id): array;

    /** @param array<string,mixed> $answers
     *  @return array<string,mixed>
     */
    public function saveInterviewDraft(int $id, array $answers, string $status, int $expectedVersion): array;

    /** @param array<string,mixed> $answers
     *  @return array<string,mixed>
     */
    public function completeInterview(int $id, array $answers, int $expectedVersion): array;
}
