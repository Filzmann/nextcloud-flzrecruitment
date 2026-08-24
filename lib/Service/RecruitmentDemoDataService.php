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
            'contractTerm' => 'permanent', 'payGrade' => '5', 'advertisedWeeklyHours' => 30.0,
            'fullTimeWeeklyHours' => 38.5, 'vacationDays' => 30.0, 'workLocation' => 'Berlin',
        ],
        [
            'internalTitle' => 'Pflegefachkraft (Demo)',
            'publicTitle' => 'Pflegefachkraft im ambulanten Dienst (Demo)',
            'assignmentKey' => 'demo-pflegefachkraft',
            'bq' => false,
            'professionCategory' => 'nursing',
            'contractTerm' => 'permanent', 'payGrade' => '8', 'advertisedWeeklyHours' => 32.0,
            'fullTimeWeeklyHours' => 38.5, 'vacationDays' => 30.0, 'workLocation' => 'Berlin',
        ],
    ];

    /** @var list<array{givenName:string,familyName:string,email:string,phone:string,salutation:string,title:string,plannedStartDate:string,city:string}> */
    private const PEOPLE = [
        ['givenName' => 'Ari', 'familyName' => 'Beispiel', 'email' => 'ari.beispiel@demo.invalid', 'phone' => '+49 30 5550101', 'salutation' => 'neutral', 'title' => '', 'plannedStartDate' => '2026-09-15', 'city' => 'Berlin'],
        ['givenName' => 'Mika', 'familyName' => 'Muster', 'email' => 'mika.muster@demo.invalid', 'phone' => '+49 30 5550102', 'salutation' => 'male', 'title' => 'dr', 'plannedStartDate' => '2026-10-01', 'city' => 'Berlin'],
        ['givenName' => 'Nuri', 'familyName' => 'Neutral', 'email' => 'nuri.neutral@demo.invalid', 'phone' => '+49 30 5550103', 'salutation' => 'diverse', 'title' => 'prof', 'plannedStartDate' => '2026-11-01', 'city' => 'Berlin'],
        ['givenName' => 'Toni', 'familyName' => 'Test', 'email' => 'toni.test@demo.invalid', 'phone' => '+49 30 5550104', 'salutation' => 'female', 'title' => 'prof_dr', 'plannedStartDate' => '2026-09-01', 'city' => 'Berlin'],
    ];

    /** @var list<array{email:string,assignmentKey:string,receivedOn:string,source:string,desiredWeeklyHours:float,desiredWeeklyHoursMax?:float,path:list<string>,bq?:bool,interview?:bool}> */
    private const APPLICATIONS = [
        ['email' => 'ari.beispiel@demo.invalid', 'assignmentKey' => 'demo-assistenz', 'receivedOn' => '2026-07-29', 'source' => 'email_import', 'desiredWeeklyHours' => 20.0, 'desiredWeeklyHoursMax' => 25.0, 'path' => ['received']],
        ['email' => 'mika.muster@demo.invalid', 'assignmentKey' => 'demo-assistenz', 'receivedOn' => '2026-07-22', 'source' => 'email_import', 'desiredWeeklyHours' => 30.0, 'path' => ['received', 'screening', 'phone_planned', 'phone_completed', 'decision_pending'], 'bq' => true],
        ['email' => 'nuri.neutral@demo.invalid', 'assignmentKey' => 'demo-assistenz', 'receivedOn' => '2026-07-25', 'source' => 'email_import', 'desiredWeeklyHours' => 15.0, 'path' => ['received', 'screening', 'phone_planned'], 'interview' => true],
        ['email' => 'toni.test@demo.invalid', 'assignmentKey' => 'demo-pflegefachkraft', 'receivedOn' => '2026-07-27', 'source' => 'referral', 'desiredWeeklyHours' => 32.0, 'path' => ['received', 'screening']],
    ];

    /**
     * Neutralisierter Auszug aus dem Fragenkatalog der früheren Bewerber-App.
     * Stammdaten und besonders sensible Angaben bleiben in ihren kanonischen
     * Fachfeldern und werden nicht als freie Interviewnotizen dupliziert.
     *
     * @var list<array{name:string,type:string,description:string,questions:list<array{prompt:string,hint:string}>}>
     */
    private const INTERVIEW_TEMPLATES = [
        [
            'name' => 'Telefoninterview Assistenz (Demo)',
            'type' => 'phone',
            'description' => 'Neutralisierter Demo-Fragenkatalog für ein telefonisches Kurzinterview.',
            'questions' => [
                ['prompt' => 'Wie sind Sie auf uns aufmerksam geworden?', 'hint' => 'Nur die für das Recruiting relevante Quelle dokumentieren.'],
                ['prompt' => 'Was motiviert die Person für die persönliche Assistenz?', 'hint' => 'Nur neutrale, für die Auswahl relevante Beobachtungen dokumentieren.'],
                ['prompt' => 'Welche relevanten Erfahrungen, Ausbildungen oder Fortbildungen bringen Sie mit?', 'hint' => 'Art, Dauer und gegebenenfalls erforderliche Nachweise festhalten.'],
                ['prompt' => 'Welche zeitliche Perspektive verbinden Sie mit der Tätigkeit?', 'hint' => 'Kurzfristige Übergangslösung oder längerfristige Perspektive sachlich festhalten.'],
                ['prompt' => 'Wie viele Stunden pro Woche möchten Sie arbeiten?', 'hint' => 'Gewünschten Umfang und mögliche Bandbreite erfassen.'],
                ['prompt' => 'Wie soll die Tätigkeit mit Studium, Ausbildung oder Nebentätigkeiten kombiniert werden?', 'hint' => 'Nur arbeitszeitrelevante Rahmenbedingungen dokumentieren.'],
                ['prompt' => 'An welchen Wochentagen und zu welchen Zeiten können Sie arbeiten?', 'hint' => 'Regelmäßige Verfügbarkeit einschließlich Wochenenden klären.'],
                ['prompt' => 'Gibt es konkrete Ausschlusszeiten?', 'hint' => 'Nur zeitliche Verfügbarkeit erfassen, keine privaten Begründungen verlangen.'],
                ['prompt' => 'Welche Schichtlängen und Tageszeiten kommen für Sie infrage?', 'hint' => 'Präferenzen und verbindliche Grenzen getrennt notieren.'],
                ['prompt' => 'Ab wann können Sie beginnen und welche Fristen sind zu berücksichtigen?', 'hint' => 'Frühesten Start sowie Kündigungsfristen oder bereits geplante Abwesenheiten erfassen.'],
            ],
        ],
        [
            'name' => 'Vorstellungsgespräch Assistenz (Demo)',
            'type' => 'live',
            'description' => 'Neutralisierter Demo-Fragenkatalog für ein persönliches Gespräch mit Fallfragen.',
            'questions' => [
                ['prompt' => 'Was interessiert Sie an einem Wechsel in die persönliche Assistenz?', 'hint' => 'Motivation ohne Bewertung des bisherigen Berufswegs erfragen.'],
                ['prompt' => 'Was verstehen Sie unter persönlicher Assistenz und Selbstbestimmung?', 'hint' => 'Abgrenzung zur Betreuung und Rolle der assistierten Person besprechen.'],
                ['prompt' => 'Mit welchen Personengruppen rechnen Sie in der persönlichen Assistenz?', 'hint' => 'Vorstellungen zu Alter und Unterstützungsbedarf sachlich einordnen.'],
                ['prompt' => 'Welche konkreten Aufgaben erwarten Sie in der persönlichen Assistenz?', 'hint' => 'Körperbezogene Assistenz, Haushalt, Transfers und Begleitung einbeziehen.'],
                ['prompt' => 'Gibt es Aufgaben, die Sie nicht übernehmen können oder möchten?', 'hint' => 'Grenzen offen und ohne Rechtfertigungsdruck klären.'],
                ['prompt' => 'Wie stellen Sie sich die Zusammenarbeit mit einer Person mit Sprechbeeinträchtigung vor?', 'hint' => 'Kommunikationswege, Einarbeitung und vorhandene Unterstützung ansprechen.'],
                ['prompt' => 'Wie würden Sie mit Konflikten mit einer assistierten Person umgehen?', 'hint' => 'Direkte Klärung, professionelle Unterstützung und erreichbare Ansprechpersonen berücksichtigen.'],
                ['prompt' => 'Ihre Ablösung erscheint nicht und Sie haben selbst einen Termin. Wie handeln Sie?', 'hint' => 'Sicherstellung, rechtzeitige Eskalation und zuständige Rufbereitschaft besprechen.'],
                ['prompt' => 'Die assistierte Person möchte nachts spontan länger wach bleiben. Wie reagieren Sie?', 'hint' => 'Selbstbestimmung, Absprachen und sichere Assistenz gegeneinander abwägen.'],
                ['prompt' => 'Welche professionelle Beziehung wünschen Sie sich zur assistierten Person?', 'hint' => 'Nähe, Distanz, Rollenklärung und Privatsphäre thematisieren.'],
                ['prompt' => 'Wie reagieren Sie auf die Bitte, Alkohol anzureichen?', 'hint' => 'Selbstbestimmung, Anleitungsfähigkeit und bekannte medizinische Vorgaben berücksichtigen.'],
                ['prompt' => 'Wie reagieren Sie auf die Bitte, illegale Rauschmittel zu beschaffen oder anzureichen?', 'hint' => 'Rechtliche Grenze benennen und zuständige Unterstützung hinzuziehen.'],
                ['prompt' => 'Wie gehen Sie mit einer Assistenzaufgabe um, die Ihren persönlichen Ernährungsgewohnheiten widerspricht?', 'hint' => 'Professionelle Rolle, konkrete Aufgabe und begründete Grenzen besprechen.'],
            ],
        ],
    ];

    /** @var array<string,list<array{label:string,insertText:string}>> */
    private const DEMO_ANSWER_BUBBLES = [
        'Wie sind Sie auf uns aufmerksam geworden?' => [
            ['label' => 'Stellenportal', 'insertText' => 'Ich habe die Ausschreibung über ein Stellenportal gefunden.'],
            ['label' => 'Empfehlung', 'insertText' => 'Mir wurde die Stelle persönlich empfohlen.'],
            ['label' => 'Online-Auftritt', 'insertText' => 'Ich bin über den Online-Auftritt auf die Stelle aufmerksam geworden.'],
        ],
        'Was motiviert die Person für die persönliche Assistenz?' => [
            ['label' => 'Selbstbestimmung', 'insertText' => 'Mich motiviert, eine selbstbestimmte Lebensführung praktisch zu unterstützen.'],
            ['label' => 'Abwechslungsreiche Aufgabe', 'insertText' => 'Ich suche eine abwechslungsreiche Tätigkeit mit direkter Verantwortung.'],
            ['label' => 'Berufliche Perspektive', 'insertText' => 'Ich möchte persönliche Assistenz als längerfristige berufliche Perspektive kennenlernen.'],
        ],
        'Welche relevanten Erfahrungen, Ausbildungen oder Fortbildungen bringen Sie mit?' => [
            ['label' => 'Direkte Erfahrung', 'insertText' => 'Ich habe bereits praktische Erfahrung in Assistenz oder Pflege gesammelt.'],
            ['label' => 'Übertragbare Erfahrung', 'insertText' => 'Ich bringe übertragbare Erfahrung aus einem anderen sozialen oder dienstleistenden Bereich mit.'],
            ['label' => 'Quereinstieg', 'insertText' => 'Ich habe noch keine direkte Vorerfahrung und möchte mich durch Einarbeitung und Qualifizierung einarbeiten.'],
        ],
        'Welche zeitliche Perspektive verbinden Sie mit der Tätigkeit?' => [
            ['label' => 'Langfristig', 'insertText' => 'Ich suche eine längerfristige Tätigkeit.'],
            ['label' => 'Übergangsweise', 'insertText' => 'Ich plane die Tätigkeit zunächst für einen begrenzten Zeitraum.'],
            ['label' => 'Noch offen', 'insertText' => 'Meine zeitliche Perspektive ist noch offen und hängt von der konkreten Einsatzplanung ab.'],
        ],
        'Wie viele Stunden pro Woche möchten Sie arbeiten?' => [
            ['label' => 'Bis 20 Stunden', 'insertText' => 'Ich möchte bis zu 20 Stunden pro Woche arbeiten.'],
            ['label' => '20–30 Stunden', 'insertText' => 'Ich möchte zwischen 20 und 30 Stunden pro Woche arbeiten.'],
            ['label' => 'Ab 30 Stunden', 'insertText' => 'Ich möchte mindestens 30 Stunden pro Woche arbeiten.'],
        ],
        'Wie soll die Tätigkeit mit Studium, Ausbildung oder Nebentätigkeiten kombiniert werden?' => [
            ['label' => 'Studium/Ausbildung', 'insertText' => 'Die Tätigkeit soll mit meinem Studium oder meiner Ausbildung abgestimmt werden.'],
            ['label' => 'Nebentätigkeit', 'insertText' => 'Bei der Einsatzplanung muss eine weitere Tätigkeit berücksichtigt werden.'],
            ['label' => 'Keine Kombination', 'insertText' => 'Ich habe derzeit keine weiteren arbeitszeitrelevanten Verpflichtungen.'],
        ],
        'An welchen Wochentagen und zu welchen Zeiten können Sie arbeiten?' => [
            ['label' => 'Werktags', 'insertText' => 'Ich kann regelmäßig werktags arbeiten.'],
            ['label' => 'Abends/Nachts', 'insertText' => 'Ich kann grundsätzlich Abend- und Nachtdienste übernehmen.'],
            ['label' => 'Wochenenden', 'insertText' => 'Ich kann regelmäßig an Wochenenden arbeiten.'],
        ],
        'Gibt es konkrete Ausschlusszeiten?' => [
            ['label' => 'Keine', 'insertText' => 'Ich habe keine regelmäßigen Ausschlusszeiten.'],
            ['label' => 'Feste Wochentage', 'insertText' => 'An einzelnen fest benannten Wochentagen kann ich nicht arbeiten.'],
            ['label' => 'Vorübergehend', 'insertText' => 'Ich habe vorübergehende Ausschlusszeiten, die zeitlich klar begrenzt sind.'],
        ],
        'Welche Schichtlängen und Tageszeiten kommen für Sie infrage?' => [
            ['label' => 'Kurze Dienste', 'insertText' => 'Ich bevorzuge kürzere Dienste.'],
            ['label' => 'Lange Dienste', 'insertText' => 'Ich kann auch längere Dienste übernehmen.'],
            ['label' => 'Flexibel', 'insertText' => 'Ich bin bei Schichtlänge und Tageszeit flexibel.'],
        ],
        'Ab wann können Sie beginnen und welche Fristen sind zu berücksichtigen?' => [
            ['label' => 'Kurzfristig', 'insertText' => 'Ich kann kurzfristig beginnen.'],
            ['label' => 'Kündigungsfrist', 'insertText' => 'Vor meinem Beginn ist eine bestehende Kündigungsfrist zu berücksichtigen.'],
            ['label' => 'Termin noch offen', 'insertText' => 'Den frühestmöglichen Starttermin muss ich noch abschließend klären.'],
        ],
        'Was interessiert Sie an einem Wechsel in die persönliche Assistenz?' => [
            ['label' => 'Neuorientierung', 'insertText' => 'Ich möchte mich bewusst beruflich neu orientieren.'],
            ['label' => 'Erfahrung vertiefen', 'insertText' => 'Ich möchte vorhandene Erfahrungen in der persönlichen Assistenz vertiefen.'],
            ['label' => 'Sinnvolle Tätigkeit', 'insertText' => 'Ich suche eine sinnvolle und verantwortliche Tätigkeit mit direktem Kontakt.'],
        ],
        'Was verstehen Sie unter persönlicher Assistenz und Selbstbestimmung?' => [
            ['label' => 'Selbstbestimmung', 'insertText' => 'Die assistierte Person entscheidet selbst über ihren Alltag und die gewünschte Unterstützung.'],
            ['label' => 'Anleitung', 'insertText' => 'Ich richte Assistenzhandlungen an der Anleitung der assistierten Person aus.'],
            ['label' => 'Keine Betreuung', 'insertText' => 'Persönliche Assistenz bedeutet für mich Unterstützung ohne Bevormundung oder erzieherischen Auftrag.'],
        ],
        'Mit welchen Personengruppen rechnen Sie in der persönlichen Assistenz?' => [
            ['label' => 'Erwachsene Menschen', 'insertText' => 'Ich rechne mit erwachsenen Menschen mit unterschiedlichem Unterstützungsbedarf.'],
            ['label' => 'Körperliche Behinderung', 'insertText' => 'Ich erwarte vor allem Einsätze bei Menschen mit körperlichen Behinderungen.'],
            ['label' => 'Offen', 'insertText' => 'Ich bin gegenüber unterschiedlichen Assistenzsituationen offen.'],
        ],
        'Welche konkreten Aufgaben erwarten Sie in der persönlichen Assistenz?' => [
            ['label' => 'Körperbezogene Assistenz', 'insertText' => 'Ich rechne mit körperbezogener Assistenz und möglichen Transfers.'],
            ['label' => 'Haushalt', 'insertText' => 'Ich rechne mit Unterstützung im Haushalt, etwa Kochen, Reinigen oder Wäsche.'],
            ['label' => 'Begleitung', 'insertText' => 'Ich rechne mit Begleitung im Alltag, bei Arbeit, Freizeit oder Reisen.'],
        ],
        'Gibt es Aufgaben, die Sie nicht übernehmen können oder möchten?' => [
            ['label' => 'Keine Grenze', 'insertText' => 'Ich sehe derzeit keine grundsätzliche Aufgabengrenze.'],
            ['label' => 'Vorher klären', 'insertText' => 'Einzelne Aufgaben möchte ich vor einem Einsatz konkret besprechen.'],
            ['label' => 'Konkrete Grenze', 'insertText' => 'Ich habe eine konkrete persönliche Aufgabengrenze, die ich offen benenne.'],
        ],
        'Wie stellen Sie sich die Zusammenarbeit mit einer Person mit Sprechbeeinträchtigung vor?' => [
            ['label' => 'Signale lernen', 'insertText' => 'Ich möchte die individuellen Kommunikationssignale aufmerksam erlernen.'],
            ['label' => 'Geduldig nachfragen', 'insertText' => 'Ich würde ausreichend Zeit geben und bei Unklarheiten geduldig nachfragen.'],
            ['label' => 'Einarbeitung nutzen', 'insertText' => 'Ich würde Einarbeitung und vorhandene Kommunikationshilfen konsequent nutzen.'],
        ],
        'Wie würden Sie mit Konflikten mit einer assistierten Person umgehen?' => [
            ['label' => 'Direkt klären', 'insertText' => 'Ich würde den Konflikt zunächst ruhig und direkt ansprechen.'],
            ['label' => 'Deeskalieren', 'insertText' => 'In einer angespannten Situation würde ich zunächst Abstand schaffen und deeskalieren.'],
            ['label' => 'Unterstützung holen', 'insertText' => 'Wenn eine direkte Klärung nicht gelingt, würde ich die zuständige Unterstützung hinzuziehen.'],
        ],
        'Ihre Ablösung erscheint nicht und Sie haben selbst einen Termin. Wie handeln Sie?' => [
            ['label' => 'Nicht allein lassen', 'insertText' => 'Ich lasse die assistierte Person nicht ohne gesicherte Ablösung zurück.'],
            ['label' => 'Sofort melden', 'insertText' => 'Ich melde die fehlende Ablösung unverzüglich bei der zuständigen Stelle.'],
            ['label' => 'Rufbereitschaft', 'insertText' => 'Ich nutze die vorgesehene Rufbereitschaft, um eine sichere Lösung zu organisieren.'],
        ],
        'Die assistierte Person möchte nachts spontan länger wach bleiben. Wie reagieren Sie?' => [
            ['label' => 'Wunsch respektieren', 'insertText' => 'Ich respektiere den spontanen Wunsch als Teil der selbstbestimmten Lebensführung.'],
            ['label' => 'Rahmen klären', 'insertText' => 'Ich kläre gemeinsam bestehende Absprachen und eine sichere Durchführung.'],
            ['label' => 'Unterstützung einholen', 'insertText' => 'Wenn ich die Situation nicht sicher lösen kann, hole ich zuständige Unterstützung hinzu.'],
        ],
        'Welche professionelle Beziehung wünschen Sie sich zur assistierten Person?' => [
            ['label' => 'Respektvoll', 'insertText' => 'Ich wünsche mir eine respektvolle und verlässliche Zusammenarbeit.'],
            ['label' => 'Professionelle Grenzen', 'insertText' => 'Mir sind klare professionelle Grenzen bei Nähe und Distanz wichtig.'],
            ['label' => 'Privatsphäre', 'insertText' => 'Ich respektiere die Privatsphäre und persönlichen Entscheidungen der assistierten Person.'],
        ],
        'Wie reagieren Sie auf die Bitte, Alkohol anzureichen?' => [
            ['label' => 'Selbstbestimmung', 'insertText' => 'Ich respektiere den selbstbestimmten Wunsch grundsätzlich.'],
            ['label' => 'Vorgaben prüfen', 'insertText' => 'Ich berücksichtige bekannte medizinische Vorgaben und prüfe, ob eine sichere Anleitung möglich ist.'],
            ['label' => 'Unsicherheit klären', 'insertText' => 'Bei Unsicherheit kläre ich die Situation vor der Handlung mit der zuständigen Unterstützung.'],
        ],
        'Wie reagieren Sie auf die Bitte, illegale Rauschmittel zu beschaffen oder anzureichen?' => [
            ['label' => 'Ablehnen', 'insertText' => 'Ich lehne die Beschaffung oder das Anreichen illegaler Rauschmittel ab.'],
            ['label' => 'Grenze erklären', 'insertText' => 'Ich erkläre die rechtliche und professionelle Grenze ruhig und eindeutig.'],
            ['label' => 'Unterstützung hinzuziehen', 'insertText' => 'Ich ziehe zur weiteren Klärung die zuständige Unterstützung hinzu.'],
        ],
        'Wie gehen Sie mit einer Assistenzaufgabe um, die Ihren persönlichen Ernährungsgewohnheiten widerspricht?' => [
            ['label' => 'Vegan – kein Fleisch', 'insertText' => 'Als vegan lebende Person kann ich keine Speisen mit Fleisch zubereiten.'],
            ['label' => 'ASN entscheidet selbst', 'insertText' => 'Die assistierte Person entscheidet selbst, was sie essen möchte. Ich richte mein Handeln danach aus.'],
            ['label' => 'Vorher abstimmen', 'insertText' => 'Ich spreche einen persönlichen Konflikt frühzeitig an und suche vor dem Einsatz eine verlässliche Abstimmung.'],
        ],
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
            $this->ensureMailHiringData($applicationId, $this->personFixture($fixture['email']));
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
        $templateIds = $this->ensureInterviewTemplates();
        $this->ensureInterview($applicationIds['nuri.neutral@demo.invalid'], $templateIds['Telefoninterview Assistenz (Demo)']);

        return [
            'jobs' => count(self::JOBS),
            'people' => count(self::PEOPLE),
            'applications' => count(self::APPLICATIONS),
            'basisQualifications' => 1,
            'templates' => count(self::INTERVIEW_TEMPLATES),
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
            $fixture['contractTerm'], $fixture['payGrade'], $fixture['advertisedWeeklyHours'],
            $fixture['fullTimeWeeklyHours'], $fixture['vacationDays'], $fixture['workLocation'],
        );
    }

    /** @param array{givenName:string,familyName:string,email:string,phone:string,salutation:string,title:string,plannedStartDate:string,city:string} $fixture */
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

    /** @param array{givenName:string,familyName:string,email:string,phone:string,salutation:string,title:string,plannedStartDate:string,city:string} $fixture */
    private function ensureMailHiringData(int $applicationId, array $fixture): void {
        $stored = $this->useCases->hiringData($applicationId);
        $mailDefaults = [
            'salutation' => $fixture['salutation'], 'title' => $fixture['title'],
            'privateEmail' => $fixture['email'], 'privatePhone' => $fixture['phone'],
            'plannedStartDate' => $fixture['plannedStartDate'], 'city' => $fixture['city'],
        ];
        $missing = array_filter(
            $mailDefaults,
            static fn(string $value, string $field): bool => $value !== '' && trim((string)($stored['data'][$field] ?? '')) === '',
            ARRAY_FILTER_USE_BOTH,
        );
        if ($missing !== []) $this->useCases->saveHiringData($applicationId, $missing, (int)$stored['version'], self::ACTOR);
    }

    /** @return array{givenName:string,familyName:string,email:string,phone:string,salutation:string,title:string,plannedStartDate:string,city:string} */
    private function personFixture(string $email): array {
        foreach (self::PEOPLE as $fixture) if ($fixture['email'] === $email) return $fixture;
        throw new \LogicException('Demo-Person zur Bewerbung fehlt.');
    }

    /** @return array<string,int> */
    private function ensureInterviewTemplates(): array {
        $templateIds = [];
        foreach (self::INTERVIEW_TEMPLATES as $fixture) {
            $templateId = 0;
            foreach ($this->useCases->overview()['templates'] as $template) {
                if ((string)$template['name'] === $fixture['name']) $templateId = (int)$template['id'];
            }
            if ($templateId === 0) {
                $templateId = $this->useCases->createTemplate(
                    $fixture['name'],
                    $fixture['type'],
                    $fixture['description'],
                    'interviewer',
                );
            }

            $template = $this->useCases->templateDetail($templateId);
            $existingByPrompt = [];
            foreach ($template['questions'] as $question) {
                $existingByPrompt[(string)$question['prompt']] = $question;
            }
            foreach ($fixture['questions'] as $index => $question) {
                $stored = $existingByPrompt[$question['prompt']] ?? null;
                $questionId = $stored === null
                    ? $this->useCases->createQuestion(
                        $templateId,
                        $question['prompt'],
                        $question['hint'],
                        'textarea',
                        true,
                        ($index + 1) * 10,
                        [],
                        'internal',
                    )
                    : (int)$stored['id'];
                $this->ensureDemoBubbles(
                    $questionId,
                    $stored['bubbles'] ?? [],
                    self::DEMO_ANSWER_BUBBLES[$question['prompt']],
                );
            }
            $templateIds[$fixture['name']] = $templateId;
        }
        return $templateIds;
    }

    /**
     * @param list<array<string,mixed>> $storedBubbles
     * @param list<array{label:string,insertText:string}> $fixtures
     */
    private function ensureDemoBubbles(int $questionId, array $storedBubbles, array $fixtures): void {
        $storedLabels = array_fill_keys(array_map(
            static fn (array $bubble): string => (string)$bubble['label'],
            $storedBubbles,
        ), true);
        foreach ($fixtures as $index => $bubble) {
            if (isset($storedLabels[$bubble['label']])) continue;
            $this->useCases->createBubble(
                $questionId,
                $bubble['label'],
                $bubble['insertText'],
                ($index + 1) * 10,
                true,
            );
        }
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
