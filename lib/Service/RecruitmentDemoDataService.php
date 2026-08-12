<?php

declare(strict_types=1);

namespace OCA\Recruitment\Service;

/** Installiert einen ausschließlich synthetischen, wiederholbaren Recruitment-Datensatz. */
final class RecruitmentDemoDataService {
    private const ACTOR = 'demo-seed';

    /** @var list<array{internalTitle:string,publicTitle:string,assignmentKey:string,bq:bool,professionCategory:string}> */
    private const JOBS = [
        [
            'internalTitle' => 'Assistenz – Persönliche Assistenz (Demo)',
            'publicTitle' => 'Assistent*in für Menschen mit Behinderung (Demo)',
            'assignmentKey' => 'demo-assistenz',
            'bq' => true,
            'professionCategory' => 'assistance',
        ],
        [
            'internalTitle' => 'Pflegefachkraft (Demo)',
            'publicTitle' => 'Pflegefachkraft im ambulanten Dienst (Demo)',
            'assignmentKey' => 'demo-pflegefachkraft',
            'bq' => false,
            'professionCategory' => 'nursing',
        ],
    ];

    /** @var list<array{givenName:string,familyName:string,email:string,phone:string}> */
    private const PEOPLE = [
        ['givenName' => 'Ari', 'familyName' => 'Beispiel', 'email' => 'ari.beispiel@demo.invalid', 'phone' => '+49 30 5550101'],
        ['givenName' => 'Mika', 'familyName' => 'Muster', 'email' => 'mika.muster@demo.invalid', 'phone' => '+49 30 5550102'],
        ['givenName' => 'Nuri', 'familyName' => 'Neutral', 'email' => 'nuri.neutral@demo.invalid', 'phone' => '+49 30 5550103'],
        ['givenName' => 'Toni', 'familyName' => 'Test', 'email' => 'toni.test@demo.invalid', 'phone' => '+49 30 5550104'],
    ];

    /** @var list<array{email:string,assignmentKey:string,receivedOn:string,source:string,desiredWeeklyHours:float,desiredWeeklyHoursMax?:float,path:list<string>,bq?:bool,interview?:bool}> */
    private const APPLICATIONS = [
        ['email' => 'ari.beispiel@demo.invalid', 'assignmentKey' => 'demo-assistenz', 'receivedOn' => '2026-07-29', 'source' => 'email_import', 'desiredWeeklyHours' => 20.0, 'desiredWeeklyHoursMax' => 25.0, 'path' => ['received']],
        ['email' => 'mika.muster@demo.invalid', 'assignmentKey' => 'demo-assistenz', 'receivedOn' => '2026-07-22', 'source' => 'email_import', 'desiredWeeklyHours' => 30.0, 'path' => ['received', 'screening', 'phone_planned', 'phone_completed', 'decision_pending'], 'bq' => true],
        ['email' => 'nuri.neutral@demo.invalid', 'assignmentKey' => 'demo-assistenz', 'receivedOn' => '2026-07-25', 'source' => 'email_import', 'desiredWeeklyHours' => 15.0, 'path' => ['received', 'screening', 'phone_planned'], 'interview' => true],
        ['email' => 'toni.test@demo.invalid', 'assignmentKey' => 'demo-pflegefachkraft', 'receivedOn' => '2026-07-27', 'source' => 'referral', 'desiredWeeklyHours' => 32.0, 'path' => ['received', 'screening']],
    ];

    public function __construct(private RecruitmentUseCaseService $useCases) {}

    /** @return array{jobs:int,people:int,applications:int,basisQualifications:int,templates:int} */
    public function install(): array {
        foreach (self::JOBS as $fixture) $this->ensureJob($fixture);
        foreach (self::PEOPLE as $fixture) $this->ensurePerson($fixture);

        $applicationIds = [];
        foreach (self::APPLICATIONS as $fixture) {
            $applicationId = $this->ensureApplication($fixture);
            $applicationIds[$fixture['email']] = $applicationId;
            $this->advance($applicationId, $fixture['path']);
        }

        $runId = $this->ensureBasisQualificationRun();
        $bqApplicationId = $applicationIds['mika.muster@demo.invalid'];
        if ($this->useCases->basisQualificationAssignments($bqApplicationId) === []) {
            $application = $this->useCases->applicationSummary($bqApplicationId);
            if ((string)$application['status'] === 'decision_pending') {
                $this->useCases->assignBasisQualification(
                    $bqApplicationId,
                    $runId,
                    (int)$application['version'],
                    self::ACTOR,
                );
            }
        }
        $this->ensureHiringData($bqApplicationId);

        $templateId = $this->ensureInterviewTemplate();
        $this->ensureInterview($applicationIds['nuri.neutral@demo.invalid'], $templateId);

        return [
            'jobs' => count(self::JOBS),
            'people' => count(self::PEOPLE),
            'applications' => count(self::APPLICATIONS),
            'basisQualifications' => 1,
            'templates' => 1,
        ];
    }

    /** @param array{internalTitle:string,publicTitle:string,assignmentKey:string,bq:bool,professionCategory:string} $fixture */
    private function ensureJob(array $fixture): int {
        foreach ($this->useCases->overview()['jobs'] as $job) {
            if ((string)$job['assignmentKey'] === $fixture['assignmentKey']) return (int)$job['id'];
        }
        return $this->useCases->createJob(
            $fixture['internalTitle'],
            $fixture['publicTitle'],
            true,
            [],
            [],
            $fixture['assignmentKey'],
            $fixture['bq'],
            $fixture['professionCategory'],
        );
    }

    /** @param array{givenName:string,familyName:string,email:string,phone:string} $fixture */
    private function ensurePerson(array $fixture): int {
        foreach ($this->useCases->overview()['people'] as $person) {
            if ((string)$person['email'] === $fixture['email']) return (int)$person['id'];
        }
        return $this->useCases->createPerson(
            $fixture['givenName'],
            $fixture['familyName'],
            $fixture['email'],
            $fixture['phone'],
        );
    }

    /** @param array{email:string,assignmentKey:string,receivedOn:string,source:string,desiredWeeklyHours:float,desiredWeeklyHoursMax?:float,path:list<string>,bq?:bool,interview?:bool} $fixture */
    private function ensureApplication(array $fixture): int {
        $overview = $this->useCases->overview();
        $personId = $this->idBy($overview['people'], 'email', $fixture['email']);
        $jobId = $this->idBy($overview['jobs'], 'assignmentKey', $fixture['assignmentKey']);
        foreach ($overview['applications'] as $application) {
            if ((int)$application['personId'] === $personId
                && (int)$application['jobId'] === $jobId
                && (string)$application['receivedOn'] === $fixture['receivedOn']) return (int)$application['id'];
        }
        return $this->useCases->createApplication(
            $personId,
            $jobId,
            $fixture['source'],
            $fixture['receivedOn'],
            'ad-demo-persref',
            $fixture['desiredWeeklyHours'],
            $fixture['desiredWeeklyHoursMax'] ?? null,
        );
    }

    /** @param list<string> $path */
    private function advance(int $applicationId, array $path): void {
        $application = $this->useCases->applicationSummary($applicationId);
        $position = array_search((string)$application['status'], $path, true);
        if ($position === false) return;
        for ($index = $position + 1, $count = count($path); $index < $count; $index++) {
            $application = $this->useCases->transitionStatus(
                $applicationId,
                $path[$index],
                (int)$application['version'],
                self::ACTOR,
            );
        }
    }

    private function ensureBasisQualificationRun(): int {
        foreach ($this->useCases->basisQualificationRuns() as $run) {
            if ((string)$run['startsOn'] === '2026-09-07' && (string)$run['endsOn'] === '2026-09-18') return (int)$run['id'];
        }
        return $this->useCases->createBasisQualificationRun('2026-09-07', '2026-09-18', self::ACTOR);
    }

    private function ensureHiringData(int $applicationId): void {
        $stored = $this->useCases->hiringData($applicationId);
        if (trim((string)($stored['data']['city'] ?? '')) !== '') return;
        $this->useCases->saveHiringData($applicationId, [
            'birthDate' => '1994-04-12',
            'street' => 'Musterweg',
            'houseNumber' => '12',
            'postalCode' => '10115',
            'city' => 'Berlin',
            'country' => 'Deutschland',
            'privateEmail' => 'mika.muster@demo.invalid',
            'privatePhone' => '+49 30 5550102',
            'iban' => 'DE89370400440532013000',
            'healthInsurance' => 'Beispielkasse',
            'plannedStartDate' => '2026-10-01',
            'contractType' => 'social_insurance',
            'contractTerm' => 'permanent',
            'workingTimeModel' => 'kapovaz',
            'payGrade' => '5',
            'payStep' => '1',
            'positionTitle' => 'Persönliche Assistenz',
            'workLocation' => 'Berlin',
            'weeklyHours' => 30,
            'salaryCurrency' => 'EUR',
            'vacationDays' => 30,
        ], (int)$stored['version'], self::ACTOR);
    }

    private function ensureInterviewTemplate(): int {
        $templateId = 0;
        foreach ($this->useCases->overview()['templates'] as $template) {
            if ((string)$template['name'] === 'Telefoninterview Assistenz (Demo)') $templateId = (int)$template['id'];
        }
        if ($templateId === 0) {
            $templateId = $this->useCases->createTemplate(
                'Telefoninterview Assistenz (Demo)',
                'phone',
                'Synthetische Demo-Vorlage für ein erstes Telefoninterview.',
                'interviewer',
            );
        }
        $template = $this->useCases->templateDetail($templateId);
        foreach ($template['questions'] as $question) {
            if ((string)$question['prompt'] === 'Was motiviert die Person für die persönliche Assistenz?') {
                $this->ensureDemoBubble((int)$question['id'], $question['bubbles'] ?? []);
                return $templateId;
            }
        }
        $questionId = $this->useCases->createQuestion(
            $templateId,
            'Was motiviert die Person für die persönliche Assistenz?',
            'Nur neutrale, für die Auswahl relevante Beobachtungen dokumentieren.',
            'textarea',
            true,
            10,
            [],
            'internal',
        );
        $this->ensureDemoBubble($questionId, []);
        return $templateId;
    }

    /** @param list<array<string,mixed>> $bubbles */
    private function ensureDemoBubble(int $questionId, array $bubbles): void {
        foreach ($bubbles as $bubble) if ((string)$bubble['label'] === 'Nachvollziehbar') return;
        $this->useCases->createBubble(
            $questionId,
            'Nachvollziehbar',
            'Die Motivation wurde nachvollziehbar und anhand neutraler Beispiele beschrieben.',
            10,
            true,
        );
    }

    private function ensureInterview(int $applicationId, int $templateId): void {
        foreach ($this->useCases->applicationDetail($applicationId)['interviews'] as $interview) {
            if ((int)$interview['templateId'] === $templateId) return;
        }
        $this->useCases->createInterview($applicationId, $templateId, self::ACTOR);
    }

    /** @param list<array<string,mixed>> $items */
    private function idBy(array $items, string $field, string $value): int {
        foreach ($items as $item) if ((string)$item[$field] === $value) return (int)$item['id'];
        throw new \LogicException("Demo-Referenz {$field}={$value} fehlt.");
    }
}
