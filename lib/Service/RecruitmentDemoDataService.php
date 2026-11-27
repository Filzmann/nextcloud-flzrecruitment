<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;
use OCP\IL10N;

/** Installiert einen ausschließlich synthetischen, wiederholbaren Recruitment-Datensatz. */
final class RecruitmentDemoDataService {
    private const ACTOR = 'demo-seed';

    /** @var list<array{internalTitle:string,publicTitle:string,assignmentKey:string,bq:bool,professionCategory:string}> */
    private function jobs(): array {
        return [
        [
            'internalTitle' => $this->t('Assistenz – Persönliche Assistenz (Demo)'),
            'publicTitle' => 'Assistent*in für Menschen mit Behinderung (Demo)',
            'assignmentKey' => 'demo-assistenz',
            'bq' => true,
            'professionCategory' => 'assistance',
            'contractTerm' => 'permanent', 'payGrade' => '5', 'advertisedWeeklyHours' => 30.0,
            'fullTimeWeeklyHours' => 38.5, 'vacationDays' => 30.0, 'workLocation' => 'Berlin',
        ],
        [
            'internalTitle' => $this->t('Pflegefachkraft (Demo)'),
            'publicTitle' => $this->t('Pflegefachkraft im ambulanten Dienst (Demo)'),
            'assignmentKey' => 'demo-pflegefachkraft',
            'bq' => false,
            'professionCategory' => 'nursing',
            'contractTerm' => 'permanent', 'payGrade' => '8', 'advertisedWeeklyHours' => 32.0,
            'fullTimeWeeklyHours' => 38.5, 'vacationDays' => 30.0, 'workLocation' => 'Berlin',
        ],
        ];
    }

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
    private function interview_templates(): array {
        return [
        [
            'name' => $this->t('Telefoninterview Assistenz (Demo)'),
            'type' => 'phone',
            'description' => $this->t('Neutralisierter Demo-Fragenkatalog für ein telefonisches Kurzinterview.'),
            'questions' => [
                ['prompt' => $this->t('Wie sind Sie auf uns aufmerksam geworden?'), 'hint' => $this->t('Nur die für das Recruiting relevante Quelle dokumentieren.')],
                ['prompt' => $this->t('Was motiviert die Person für die persönliche Assistenz?'), 'hint' => $this->t('Nur neutrale, für die Auswahl relevante Beobachtungen dokumentieren.')],
                ['prompt' => $this->t('Welche relevanten Erfahrungen, Ausbildungen oder Fortbildungen bringen Sie mit?'), 'hint' => $this->t('Art, Dauer und gegebenenfalls erforderliche Nachweise festhalten.')],
                ['prompt' => $this->t('Welche zeitliche Perspektive verbinden Sie mit der Tätigkeit?'), 'hint' => $this->t('Kurzfristige Übergangslösung oder längerfristige Perspektive sachlich festhalten.')],
                ['prompt' => $this->t('Wie viele Stunden pro Woche möchten Sie arbeiten?'), 'hint' => $this->t('Gewünschten Umfang und mögliche Bandbreite erfassen.')],
                ['prompt' => $this->t('Wie soll die Tätigkeit mit Studium, Ausbildung oder Nebentätigkeiten kombiniert werden?'), 'hint' => $this->t('Nur arbeitszeitrelevante Rahmenbedingungen dokumentieren.')],
                ['prompt' => $this->t('An welchen Wochentagen und zu welchen Zeiten können Sie arbeiten?'), 'hint' => $this->t('Regelmäßige Verfügbarkeit einschließlich Wochenenden klären.')],
                ['prompt' => $this->t('Gibt es konkrete Ausschlusszeiten?'), 'hint' => $this->t('Nur zeitliche Verfügbarkeit erfassen, keine privaten Begründungen verlangen.')],
                ['prompt' => $this->t('Welche Schichtlängen und Tageszeiten kommen für Sie infrage?'), 'hint' => $this->t('Präferenzen und verbindliche Grenzen getrennt notieren.')],
                ['prompt' => $this->t('Ab wann können Sie beginnen und welche Fristen sind zu berücksichtigen?'), 'hint' => $this->t('Frühesten Start sowie Kündigungsfristen oder bereits geplante Abwesenheiten erfassen.')],
            ],
        ],
        [
            'name' => $this->t('Vorstellungsgespräch Assistenz (Demo)'),
            'type' => 'live',
            'description' => $this->t('Neutralisierter Demo-Fragenkatalog für ein persönliches Gespräch mit Fallfragen.'),
            'questions' => [
                ['prompt' => $this->t('Was interessiert Sie an einem Wechsel in die persönliche Assistenz?'), 'hint' => $this->t('Motivation ohne Bewertung des bisherigen Berufswegs erfragen.')],
                ['prompt' => $this->t('Was verstehen Sie unter persönlicher Assistenz und Selbstbestimmung?'), 'hint' => $this->t('Abgrenzung zur Betreuung und Rolle der assistierten Person besprechen.')],
                ['prompt' => $this->t('Mit welchen Personengruppen rechnen Sie in der persönlichen Assistenz?'), 'hint' => $this->t('Vorstellungen zu Alter und Unterstützungsbedarf sachlich einordnen.')],
                ['prompt' => $this->t('Welche konkreten Aufgaben erwarten Sie in der persönlichen Assistenz?'), 'hint' => $this->t('Körperbezogene Assistenz, Haushalt, Transfers und Begleitung einbeziehen.')],
                ['prompt' => $this->t('Gibt es Aufgaben, die Sie nicht übernehmen können oder möchten?'), 'hint' => $this->t('Grenzen offen und ohne Rechtfertigungsdruck klären.')],
                ['prompt' => $this->t('Wie stellen Sie sich die Zusammenarbeit mit einer Person mit Sprechbeeinträchtigung vor?'), 'hint' => $this->t('Kommunikationswege, Einarbeitung und vorhandene Unterstützung ansprechen.')],
                ['prompt' => $this->t('Wie würden Sie mit Konflikten mit einer assistierten Person umgehen?'), 'hint' => $this->t('Direkte Klärung, professionelle Unterstützung und erreichbare Ansprechpersonen berücksichtigen.')],
                ['prompt' => $this->t('Ihre Ablösung erscheint nicht und Sie haben selbst einen Termin. Wie handeln Sie?'), 'hint' => $this->t('Sicherstellung, rechtzeitige Eskalation und zuständige Rufbereitschaft besprechen.')],
                ['prompt' => $this->t('Die assistierte Person möchte nachts spontan länger wach bleiben. Wie reagieren Sie?'), 'hint' => $this->t('Selbstbestimmung, Absprachen und sichere Assistenz gegeneinander abwägen.')],
                ['prompt' => $this->t('Welche professionelle Beziehung wünschen Sie sich zur assistierten Person?'), 'hint' => $this->t('Nähe, Distanz, Rollenklärung und Privatsphäre thematisieren.')],
                ['prompt' => $this->t('Wie reagieren Sie auf die Bitte, Alkohol anzureichen?'), 'hint' => $this->t('Selbstbestimmung, Anleitungsfähigkeit und bekannte medizinische Vorgaben berücksichtigen.')],
                ['prompt' => $this->t('Wie reagieren Sie auf die Bitte, illegale Rauschmittel zu beschaffen oder anzureichen?'), 'hint' => $this->t('Rechtliche Grenze benennen und zuständige Unterstützung hinzuziehen.')],
                ['prompt' => $this->t('Wie gehen Sie mit einer Assistenzaufgabe um, die Ihren persönlichen Ernährungsgewohnheiten widerspricht?'), 'hint' => $this->t('Professionelle Rolle, konkrete Aufgabe und begründete Grenzen besprechen.')],
            ],
        ],
        ];
    }

    /** @var array<string,list<array{label:string,insertText:string}>> */
    private function demo_answer_bubbles(): array {
        return [
        $this->t('Wie sind Sie auf uns aufmerksam geworden?') => [
            ['label' => 'Stellenportal', 'insertText' => $this->t('Ich habe die Ausschreibung über ein Stellenportal gefunden.')],
            ['label' => 'Empfehlung', 'insertText' => $this->t('Mir wurde die Stelle persönlich empfohlen.')],
            ['label' => 'Online-Auftritt', 'insertText' => $this->t('Ich bin über den Online-Auftritt auf die Stelle aufmerksam geworden.')],
        ],
        $this->t('Was motiviert die Person für die persönliche Assistenz?') => [
            ['label' => 'Selbstbestimmung', 'insertText' => $this->t('Mich motiviert, eine selbstbestimmte Lebensführung praktisch zu unterstützen.')],
            ['label' => $this->t('Abwechslungsreiche Aufgabe'), 'insertText' => $this->t('Ich suche eine abwechslungsreiche Tätigkeit mit direkter Verantwortung.')],
            ['label' => $this->t('Berufliche Perspektive'), 'insertText' => $this->t('Ich möchte persönliche Assistenz als längerfristige berufliche Perspektive kennenlernen.')],
        ],
        $this->t('Welche relevanten Erfahrungen, Ausbildungen oder Fortbildungen bringen Sie mit?') => [
            ['label' => $this->t('Direkte Erfahrung'), 'insertText' => $this->t('Ich habe bereits praktische Erfahrung in Assistenz oder Pflege gesammelt.')],
            ['label' => $this->t('Übertragbare Erfahrung'), 'insertText' => $this->t('Ich bringe übertragbare Erfahrung aus einem anderen sozialen oder dienstleistenden Bereich mit.')],
            ['label' => 'Quereinstieg', 'insertText' => $this->t('Ich habe noch keine direkte Vorerfahrung und möchte mich durch Einarbeitung und Qualifizierung einarbeiten.')],
        ],
        $this->t('Welche zeitliche Perspektive verbinden Sie mit der Tätigkeit?') => [
            ['label' => 'Langfristig', 'insertText' => $this->t('Ich suche eine längerfristige Tätigkeit.')],
            ['label' => $this->t('Übergangsweise'), 'insertText' => $this->t('Ich plane die Tätigkeit zunächst für einen begrenzten Zeitraum.')],
            ['label' => $this->t('Noch offen'), 'insertText' => $this->t('Meine zeitliche Perspektive ist noch offen und hängt von der konkreten Einsatzplanung ab.')],
        ],
        $this->t('Wie viele Stunden pro Woche möchten Sie arbeiten?') => [
            ['label' => $this->t('Bis 20 Stunden'), 'insertText' => $this->t('Ich möchte bis zu 20 Stunden pro Woche arbeiten.')],
            ['label' => '20–30 Stunden', 'insertText' => $this->t('Ich möchte zwischen 20 und 30 Stunden pro Woche arbeiten.')],
            ['label' => $this->t('Ab 30 Stunden'), 'insertText' => $this->t('Ich möchte mindestens 30 Stunden pro Woche arbeiten.')],
        ],
        $this->t('Wie soll die Tätigkeit mit Studium, Ausbildung oder Nebentätigkeiten kombiniert werden?') => [
            ['label' => 'Studium/Ausbildung', 'insertText' => $this->t('Die Tätigkeit soll mit meinem Studium oder meiner Ausbildung abgestimmt werden.')],
            ['label' => $this->t('Nebentätigkeit'), 'insertText' => $this->t('Bei der Einsatzplanung muss eine weitere Tätigkeit berücksichtigt werden.')],
            ['label' => $this->t('Keine Kombination'), 'insertText' => $this->t('Ich habe derzeit keine weiteren arbeitszeitrelevanten Verpflichtungen.')],
        ],
        $this->t('An welchen Wochentagen und zu welchen Zeiten können Sie arbeiten?') => [
            ['label' => 'Werktags', 'insertText' => $this->t('Ich kann regelmäßig werktags arbeiten.')],
            ['label' => 'Abends/Nachts', 'insertText' => $this->t('Ich kann grundsätzlich Abend- und Nachtdienste übernehmen.')],
            ['label' => 'Wochenenden', 'insertText' => $this->t('Ich kann regelmäßig an Wochenenden arbeiten.')],
        ],
        $this->t('Gibt es konkrete Ausschlusszeiten?') => [
            ['label' => $this->t('Keine'), 'insertText' => $this->t('Ich habe keine regelmäßigen Ausschlusszeiten.')],
            ['label' => $this->t('Feste Wochentage'), 'insertText' => $this->t('An einzelnen fest benannten Wochentagen kann ich nicht arbeiten.')],
            ['label' => $this->t('Vorübergehend'), 'insertText' => $this->t('Ich habe vorübergehende Ausschlusszeiten, die zeitlich klar begrenzt sind.')],
        ],
        $this->t('Welche Schichtlängen und Tageszeiten kommen für Sie infrage?') => [
            ['label' => $this->t('Kurze Dienste'), 'insertText' => $this->t('Ich bevorzuge kürzere Dienste.')],
            ['label' => $this->t('Lange Dienste'), 'insertText' => $this->t('Ich kann auch längere Dienste übernehmen.')],
            ['label' => 'Flexibel', 'insertText' => $this->t('Ich bin bei Schichtlänge und Tageszeit flexibel.')],
        ],
        $this->t('Ab wann können Sie beginnen und welche Fristen sind zu berücksichtigen?') => [
            ['label' => 'Kurzfristig', 'insertText' => $this->t('Ich kann kurzfristig beginnen.')],
            ['label' => $this->t('Kündigungsfrist'), 'insertText' => $this->t('Vor meinem Beginn ist eine bestehende Kündigungsfrist zu berücksichtigen.')],
            ['label' => $this->t('Termin noch offen'), 'insertText' => $this->t('Den frühestmöglichen Starttermin muss ich noch abschließend klären.')],
        ],
        $this->t('Was interessiert Sie an einem Wechsel in die persönliche Assistenz?') => [
            ['label' => 'Neuorientierung', 'insertText' => $this->t('Ich möchte mich bewusst beruflich neu orientieren.')],
            ['label' => $this->t('Erfahrung vertiefen'), 'insertText' => $this->t('Ich möchte vorhandene Erfahrungen in der persönlichen Assistenz vertiefen.')],
            ['label' => $this->t('Sinnvolle Tätigkeit'), 'insertText' => $this->t('Ich suche eine sinnvolle und verantwortliche Tätigkeit mit direktem Kontakt.')],
        ],
        $this->t('Was verstehen Sie unter persönlicher Assistenz und Selbstbestimmung?') => [
            ['label' => 'Selbstbestimmung', 'insertText' => $this->t('Die assistierte Person entscheidet selbst über ihren Alltag und die gewünschte Unterstützung.')],
            ['label' => 'Anleitung', 'insertText' => $this->t('Ich richte Assistenzhandlungen an der Anleitung der assistierten Person aus.')],
            ['label' => $this->t('Keine Betreuung'), 'insertText' => $this->t('Persönliche Assistenz bedeutet für mich Unterstützung ohne Bevormundung oder erzieherischen Auftrag.')],
        ],
        $this->t('Mit welchen Personengruppen rechnen Sie in der persönlichen Assistenz?') => [
            ['label' => $this->t('Erwachsene Menschen'), 'insertText' => $this->t('Ich rechne mit erwachsenen Menschen mit unterschiedlichem Unterstützungsbedarf.')],
            ['label' => $this->t('Körperliche Behinderung'), 'insertText' => $this->t('Ich erwarte vor allem Einsätze bei Menschen mit körperlichen Behinderungen.')],
            ['label' => 'Offen', 'insertText' => $this->t('Ich bin gegenüber unterschiedlichen Assistenzsituationen offen.')],
        ],
        $this->t('Welche konkreten Aufgaben erwarten Sie in der persönlichen Assistenz?') => [
            ['label' => $this->t('Körperbezogene Assistenz'), 'insertText' => $this->t('Ich rechne mit körperbezogener Assistenz und möglichen Transfers.')],
            ['label' => 'Haushalt', 'insertText' => $this->t('Ich rechne mit Unterstützung im Haushalt, etwa Kochen, Reinigen oder Wäsche.')],
            ['label' => 'Begleitung', 'insertText' => $this->t('Ich rechne mit Begleitung im Alltag, bei Arbeit, Freizeit oder Reisen.')],
        ],
        $this->t('Gibt es Aufgaben, die Sie nicht übernehmen können oder möchten?') => [
            ['label' => $this->t('Keine Grenze'), 'insertText' => $this->t('Ich sehe derzeit keine grundsätzliche Aufgabengrenze.')],
            ['label' => $this->t('Vorher klären'), 'insertText' => $this->t('Einzelne Aufgaben möchte ich vor einem Einsatz konkret besprechen.')],
            ['label' => $this->t('Konkrete Grenze'), 'insertText' => $this->t('Ich habe eine konkrete persönliche Aufgabengrenze, die ich offen benenne.')],
        ],
        $this->t('Wie stellen Sie sich die Zusammenarbeit mit einer Person mit Sprechbeeinträchtigung vor?') => [
            ['label' => $this->t('Signale lernen'), 'insertText' => $this->t('Ich möchte die individuellen Kommunikationssignale aufmerksam erlernen.')],
            ['label' => $this->t('Geduldig nachfragen'), 'insertText' => $this->t('Ich würde ausreichend Zeit geben und bei Unklarheiten geduldig nachfragen.')],
            ['label' => $this->t('Einarbeitung nutzen'), 'insertText' => $this->t('Ich würde Einarbeitung und vorhandene Kommunikationshilfen konsequent nutzen.')],
        ],
        $this->t('Wie würden Sie mit Konflikten mit einer assistierten Person umgehen?') => [
            ['label' => $this->t('Direkt klären'), 'insertText' => $this->t('Ich würde den Konflikt zunächst ruhig und direkt ansprechen.')],
            ['label' => 'Deeskalieren', 'insertText' => $this->t('In einer angespannten Situation würde ich zunächst Abstand schaffen und deeskalieren.')],
            ['label' => $this->t('Unterstützung holen'), 'insertText' => $this->t('Wenn eine direkte Klärung nicht gelingt, würde ich die zuständige Unterstützung hinzuziehen.')],
        ],
        $this->t('Ihre Ablösung erscheint nicht und Sie haben selbst einen Termin. Wie handeln Sie?') => [
            ['label' => $this->t('Nicht allein lassen'), 'insertText' => $this->t('Ich lasse die assistierte Person nicht ohne gesicherte Ablösung zurück.')],
            ['label' => $this->t('Sofort melden'), 'insertText' => $this->t('Ich melde die fehlende Ablösung unverzüglich bei der zuständigen Stelle.')],
            ['label' => 'Rufbereitschaft', 'insertText' => $this->t('Ich nutze die vorgesehene Rufbereitschaft, um eine sichere Lösung zu organisieren.')],
        ],
        $this->t('Die assistierte Person möchte nachts spontan länger wach bleiben. Wie reagieren Sie?') => [
            ['label' => $this->t('Wunsch respektieren'), 'insertText' => $this->t('Ich respektiere den spontanen Wunsch als Teil der selbstbestimmten Lebensführung.')],
            ['label' => $this->t('Rahmen klären'), 'insertText' => $this->t('Ich kläre gemeinsam bestehende Absprachen und eine sichere Durchführung.')],
            ['label' => $this->t('Unterstützung einholen'), 'insertText' => $this->t('Wenn ich die Situation nicht sicher lösen kann, hole ich zuständige Unterstützung hinzu.')],
        ],
        $this->t('Welche professionelle Beziehung wünschen Sie sich zur assistierten Person?') => [
            ['label' => 'Respektvoll', 'insertText' => $this->t('Ich wünsche mir eine respektvolle und verlässliche Zusammenarbeit.')],
            ['label' => $this->t('Professionelle Grenzen'), 'insertText' => $this->t('Mir sind klare professionelle Grenzen bei Nähe und Distanz wichtig.')],
            ['label' => $this->t('Privatsphäre'), 'insertText' => $this->t('Ich respektiere die Privatsphäre und persönlichen Entscheidungen der assistierten Person.')],
        ],
        $this->t('Wie reagieren Sie auf die Bitte, Alkohol anzureichen?') => [
            ['label' => 'Selbstbestimmung', 'insertText' => $this->t('Ich respektiere den selbstbestimmten Wunsch grundsätzlich.')],
            ['label' => $this->t('Vorgaben prüfen'), 'insertText' => $this->t('Ich berücksichtige bekannte medizinische Vorgaben und prüfe, ob eine sichere Anleitung möglich ist.')],
            ['label' => $this->t('Unsicherheit klären'), 'insertText' => $this->t('Bei Unsicherheit kläre ich die Situation vor der Handlung mit der zuständigen Unterstützung.')],
        ],
        $this->t('Wie reagieren Sie auf die Bitte, illegale Rauschmittel zu beschaffen oder anzureichen?') => [
            ['label' => 'Ablehnen', 'insertText' => $this->t('Ich lehne die Beschaffung oder das Anreichen illegaler Rauschmittel ab.')],
            ['label' => $this->t('Grenze erklären'), 'insertText' => $this->t('Ich erkläre die rechtliche und professionelle Grenze ruhig und eindeutig.')],
            ['label' => $this->t('Unterstützung hinzuziehen'), 'insertText' => $this->t('Ich ziehe zur weiteren Klärung die zuständige Unterstützung hinzu.')],
        ],
        $this->t('Wie gehen Sie mit einer Assistenzaufgabe um, die Ihren persönlichen Ernährungsgewohnheiten widerspricht?') => [
            ['label' => $this->t('Vegan – kein Fleisch'), 'insertText' => $this->t('Als vegan lebende Person kann ich keine Speisen mit Fleisch zubereiten.')],
            ['label' => $this->t('ASN entscheidet selbst'), 'insertText' => $this->t('Die assistierte Person entscheidet selbst, was sie essen möchte. Ich richte mein Handeln danach aus.')],
            ['label' => $this->t('Vorher abstimmen'), 'insertText' => $this->t('Ich spreche einen persönlichen Konflikt frühzeitig an und suche vor dem Einsatz eine verlässliche Abstimmung.')],
        ],
        ];
    }

    public function __construct(private RecruitmentUseCaseService $useCases, private ?IL10N $l10n = null) {}

    /** Lokalisierung fachlicher Texte; im Test ohne IL10N identity. */
    private function t(string $text): string
    {
        return $this->l10n?->t($text) ?? $text;
    }

    /** @return array{jobs:int,people:int,applications:int,basisQualifications:int,templates:int} */
    public function install(): array {
        foreach ($this->jobs() as $fixture) $this->ensureJob($fixture);
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
        $this->ensureInterview($applicationIds['nuri.neutral@demo.invalid'], $templateIds[$this->t('Telefoninterview Assistenz (Demo)')]);

        return [
            'jobs' => count($this->jobs()),
            'people' => count(self::PEOPLE),
            'applications' => count(self::APPLICATIONS),
            'basisQualifications' => 1,
            'templates' => count($this->interview_templates()),
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
            'flz-demo-persref',
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
        foreach ($this->interview_templates() as $fixture) {
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
                    $this->demo_answer_bubbles()[$question['prompt']],
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
