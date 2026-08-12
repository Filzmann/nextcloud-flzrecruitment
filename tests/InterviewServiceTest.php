<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use OCA\Recruitment\Contract\InterviewStore;
use OCA\Recruitment\Contract\TemplateStore;
use OCA\Recruitment\Exception\ConflictException;
use OCA\Recruitment\Exception\NotFoundException;
use OCA\Recruitment\Exception\ValidationException;
use OCA\Recruitment\Service\InterviewService;
use OCA\Recruitment\Service\InterviewWorkflow;
use OCA\Recruitment\Service\TemplateService;
use RecruitmentTests\TestRunner;

use function RecruitmentTests\assertSame;
use function RecruitmentTests\assertThrows;

final class MemoryInterviewStore implements TemplateStore, InterviewStore {
    /** @var array<int,array<string,mixed>> */
    public array $templates = [];
    /** @var array<int,array<string,mixed>> */
    public array $questions = [];
    /** @var array<int,array<string,mixed>> */
    public array $bubbles = [];
    /** @var array<int,array<string,mixed>> */
    public array $interviews = [];
    public array $applications = [5 => ['id' => 5]];

    public function createTemplate(array $template): int {
        $id = count($this->templates) + 1;
        $this->templates[$id] = ['id' => $id, 'revision' => 1] + $template;
        return $id;
    }

    public function createQuestion(array $question): int {
        $id = count($this->questions) + 1;
        $this->questions[$id] = ['id' => $id] + $question;
        $this->templates[$question['templateId']]['revision']++;
        return $id;
    }

    public function updateQuestion(int $id, array $question): void {
        if (!isset($this->questions[$id])) {
            throw new NotFoundException('Frage nicht gefunden.');
        }
        $this->questions[$id] = $this->questions[$id] + $question;
        foreach ($question as $key => $value) {
            $this->questions[$id][$key] = $value;
        }
        $this->templates[$this->questions[$id]['templateId']]['revision']++;
    }

    public function createBubble(array $bubble): int {
        $id = count($this->bubbles) + 1;
        $this->bubbles[$id] = ['id' => $id] + $bubble;
        $templateId = $this->questions[$bubble['questionId']]['templateId'];
        $this->templates[$templateId]['revision']++;
        return $id;
    }

    public function question(int $id): array {
        if (!isset($this->questions[$id])) {
            throw new NotFoundException('Frage nicht gefunden.');
        }
        return $this->questions[$id];
    }

    public function templateSnapshot(int $id): array {
        if (!isset($this->templates[$id])) {
            throw new NotFoundException('Vorlage nicht gefunden.');
        }
        $template = $this->templates[$id];
        $template['questions'] = [];
        foreach ($this->questions as $question) {
            if ($question['templateId'] !== $id || $question['active'] !== true) {
                continue;
            }
            $question['bubbles'] = array_values(array_filter(
                $this->bubbles,
                static fn (array $bubble): bool => $bubble['questionId'] === $question['id'] && $bubble['active'] === true,
            ));
            $template['questions'][] = $question;
        }
        return $template;
    }

    public function applicationExists(int $id): bool { return isset($this->applications[$id]); }

    public function createInterview(array $interview): int {
        $id = count($this->interviews) + 1;
        $this->interviews[$id] = ['id' => $id, 'status' => 'not_started', 'answers' => [], 'version' => 1] + $interview;
        return $id;
    }

    public function interview(int $id): array {
        if (!isset($this->interviews[$id])) {
            throw new NotFoundException('Interview nicht gefunden.');
        }
        return $this->interviews[$id];
    }

    public function saveInterviewDraft(int $id, array $answers, string $status, int $expectedVersion): array {
        $current = $this->interview($id);
        if ($current['version'] !== $expectedVersion || $current['status'] === 'completed') {
            throw new ConflictException('Konkurrierende Änderung.');
        }
        $this->interviews[$id]['answers'] = $answers;
        $this->interviews[$id]['status'] = $status;
        $this->interviews[$id]['version']++;
        return $this->interviews[$id];
    }

    public function completeInterview(int $id, array $answers, int $expectedVersion): array {
        $current = $this->interview($id);
        if ($current['version'] !== $expectedVersion || $current['status'] === 'completed') {
            throw new ConflictException('Konkurrierende Änderung.');
        }
        $this->interviews[$id]['answers'] = $answers;
        $this->interviews[$id]['status'] = 'completed';
        $this->interviews[$id]['version']++;
        return $this->interviews[$id];
    }
}

TestRunner::test('questions can be sorted, deactivated and bubbles only belong to free text', static function (): void {
    $store = new MemoryInterviewStore();
    $service = new TemplateService();
    $template = $service->create($store, 'Telefoninterview', 'phone', '', 'interviewer');
    $question = $service->addQuestion($store, $template, 'Rahmenbedingungen?', '', 'textarea', true, 20, [], 'internal');
    $service->updateQuestion($store, $question, 'Rahmenbedingungen?', '', 'textarea', true, 5, [], 'internal', false);

    assertSame(5, $store->questions[$question]['sortOrder']);
    assertSame(false, $store->questions[$question]['active']);

    $choice = $service->addQuestion($store, $template, 'Auswahl?', '', 'single_choice', false, 10, ['A', 'B'], 'internal');
    assertThrows(
        static fn () => $service->addBubble($store, $choice, 'A', 'Text A', 1, true),
        ValidationException::class,
    );
});

TestRunner::test('interview instance keeps its versioned template snapshot and editable result text', static function (): void {
    $store = new MemoryInterviewStore();
    $templates = new TemplateService();
    $interviews = new InterviewService(new InterviewWorkflow());
    $template = $templates->create($store, 'Kurzfragebogen', 'questionnaire', '', 'candidate');
    $question = $templates->addQuestion($store, $template, 'Verfügbarkeit?', '', 'text', true, 1, [], 'external');
    $templates->addBubble($store, $question, 'flexibel', 'Zeitlich flexibel.', 1, true);

    $id = $interviews->instantiate($store, 5, $template, 'interviewer-user');
    $snapshotText = $store->interviews[$id]['snapshot']['questions'][0]['prompt'];
    $templates->updateQuestion($store, $question, 'Geänderter Text', '', 'text', true, 1, [], 'external', true);

    assertSame('Verfügbarkeit?', $snapshotText);
    assertSame('Verfügbarkeit?', $store->interviews[$id]['snapshot']['questions'][0]['prompt']);

    $draft = $interviews->saveDraft($store, $id, ['1' => 'Zeitlich flexibel.'], 1);
    assertSame('Zeitlich flexibel.', $draft['answers']['1']);
    assertSame('in_progress', $draft['status']);

    assertThrows(
        static fn () => $interviews->saveDraft($store, $id, ['1' => 'Veraltet'], 1),
        ConflictException::class,
    );
});

TestRunner::test('completed interview persists required answers and becomes immutable', static function (): void {
    $store = new MemoryInterviewStore();
    $templates = new TemplateService();
    $interviews = new InterviewService(new InterviewWorkflow());
    $template = $templates->create($store, 'Liveinterview', 'live', '', 'interviewer');
    $templates->addQuestion($store, $template, 'Beobachtung?', '', 'textarea', true, 1, [], 'internal');
    $id = $interviews->instantiate($store, 5, $template, 'interviewer-user');

    assertThrows(
        static fn () => $interviews->complete($store, $id, [], 1),
        ValidationException::class,
    );
    $completed = $interviews->complete($store, $id, ['1' => 'Neutrale Beobachtung.'], 1);
    assertSame('completed', $completed['status']);

    assertThrows(
        static fn () => $interviews->saveDraft($store, $id, ['1' => 'Still geändert'], 2),
        ConflictException::class,
    );
});

TestRunner::test('disabled templates cannot be instantiated through a manipulated identifier', static function (): void {
    $store = new MemoryInterviewStore();
    $templates = new TemplateService();
    $interviews = new InterviewService(new InterviewWorkflow());
    $template = $templates->create($store, 'Deaktivierte Vorlage', 'phone', '', 'interviewer');
    $store->templates[$template]['active'] = false;

    assertThrows(
        static fn () => $interviews->instantiate($store, 5, $template, 'interviewer-user'),
        ValidationException::class,
    );
    assertSame([], $store->interviews);
});
