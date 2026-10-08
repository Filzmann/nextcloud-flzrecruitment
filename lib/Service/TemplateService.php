<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;

use OCA\FlzRecruitment\Contract\TemplateStore;
use OCA\FlzRecruitment\Exception\ValidationException;

/**
 * Validiert und versioniert Interviewvorlagen, Fragen und Antwort-Bubbles.
 */
final class TemplateService {
    private const TEMPLATE_TYPES = ['questionnaire', 'phone', 'live', 'other'];
    private const AUDIENCES = ['candidate', 'interviewer'];
    private const QUESTION_TYPES = ['text', 'textarea', 'boolean', 'single_choice', 'multiple_choice', 'rating'];
    private const VISIBILITIES = ['internal', 'external'];
    private const FREE_TEXT_TYPES = ['text', 'textarea'];

    public function create(
        TemplateStore $store,
        string $name,
        string $type,
        string $description,
        string $audience,
    ): int {
        $name = trim($name);
        if ($name === '') {
            throw new ValidationException('Der Vorlagenname ist erforderlich.');
        }
        if (!in_array($type, self::TEMPLATE_TYPES, true)) {
            throw new ValidationException('Der Interviewtyp ist ungültig.');
        }
        if (!in_array($audience, self::AUDIENCES, true)) {
            throw new ValidationException('Die Zielgruppe ist ungültig.');
        }

        return $store->createTemplate([
            'name' => $name,
            'type' => $type,
            'description' => trim($description),
            'audience' => $audience,
            'active' => true,
        ]);
    }

    /** @param list<string> $options */
    public function addQuestion(
        TemplateStore $store,
        int $templateId,
        string $prompt,
        string $hint,
        string $type,
        bool $required,
        int $sortOrder,
        array $options,
        string $visibility,
    ): int {
        return $store->createQuestion($this->questionData(
            $templateId,
            $prompt,
            $hint,
            $type,
            $required,
            $sortOrder,
            $options,
            $visibility,
            true,
        ));
    }

    /** @param list<string> $options */
    public function updateQuestion(
        TemplateStore $store,
        int $id,
        string $prompt,
        string $hint,
        string $type,
        bool $required,
        int $sortOrder,
        array $options,
        string $visibility,
        bool $active,
    ): void {
        $existing = $store->question($id);
        $store->updateQuestion($id, $this->questionData(
            (int)$existing['templateId'],
            $prompt,
            $hint,
            $type,
            $required,
            $sortOrder,
            $options,
            $visibility,
            $active,
        ));
    }

    public function addBubble(
        TemplateStore $store,
        int $questionId,
        string $label,
        string $insertText,
        int $sortOrder,
        bool $active,
    ): int {
        $question = $store->question($questionId);
        if (!in_array($question['type'] ?? '', self::FREE_TEXT_TYPES, true)) {
            throw new ValidationException('Antwort-Bubbles sind nur bei Freitextfragen erlaubt.');
        }
        $label = trim($label);
        $insertText = trim($insertText);
        if ($label === '' || $insertText === '') {
            throw new ValidationException('Beschriftung und Einfügetext sind erforderlich.');
        }

        return $store->createBubble([
            'questionId' => $questionId,
            'label' => $label,
            'insertText' => $insertText,
            'sortOrder' => $sortOrder,
            'active' => $active,
        ]);
    }

    /**
     * @param list<string> $options
     * @return array<string,mixed>
     */
    private function questionData(
        int $templateId,
        string $prompt,
        string $hint,
        string $type,
        bool $required,
        int $sortOrder,
        array $options,
        string $visibility,
        bool $active,
    ): array {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new ValidationException('Der Fragetext ist erforderlich.');
        }
        if (!in_array($type, self::QUESTION_TYPES, true)) {
            throw new ValidationException('Der Fragetyp ist ungültig.');
        }
        if (!in_array($visibility, self::VISIBILITIES, true)) {
            throw new ValidationException('Die Sichtbarkeit ist ungültig.');
        }

        $cleanOptions = array_values(array_filter(
            array_map(static fn (mixed $option): string => trim((string)$option), $options),
            static fn (string $option): bool => $option !== '',
        ));
        if (in_array($type, ['single_choice', 'multiple_choice'], true) && count($cleanOptions) < 2) {
            throw new ValidationException('Auswahlfragen benötigen mindestens zwei Optionen.');
        }
        if (!in_array($type, ['single_choice', 'multiple_choice'], true)) {
            $cleanOptions = [];
        }

        return [
            'templateId' => $templateId,
            'prompt' => $prompt,
            'hint' => trim($hint),
            'type' => $type,
            'required' => $required,
            'sortOrder' => $sortOrder,
            'options' => $cleanOptions,
            'visibility' => $visibility,
            'active' => $active,
        ];
    }
}
