<?php

declare(strict_types=1);

namespace OCA\Recruitment\Contract;

interface TemplateStore {
    /** @param array<string,mixed> $template */
    public function createTemplate(array $template): int;

    /** @param array<string,mixed> $question */
    public function createQuestion(array $question): int;

    /** @param array<string,mixed> $question */
    public function updateQuestion(int $id, array $question): void;

    /** @param array<string,mixed> $bubble */
    public function createBubble(array $bubble): int;

    /** @return array<string,mixed> */
    public function question(int $id): array;

    /** @return array<string,mixed> */
    public function templateSnapshot(int $id): array;
}
