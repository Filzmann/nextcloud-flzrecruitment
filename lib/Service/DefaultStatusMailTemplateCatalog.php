<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;
use OCP\IL10N;

/** Liefert neutrale, standardmäßig deaktivierte Vorlagen für jede erlaubte Statuskante. */
final class DefaultStatusMailTemplateCatalog {
    public function __construct(private ?IL10N $l10n = null) {}

    /** Lokalisierung fachlicher Texte; im Test ohne IL10N identity. */
    private function t(string $text): string
    {
        return $this->l10n?->t($text) ?? $text;
    }
    private function labels(): array {
        return [
        'received' => $this->t('Eingegangen'), 'screening' => 'Vorauswahl', 'questionnaire_pending' => $this->t('Fragebogen ausstehend'),
        'questionnaire_received' => $this->t('Fragebogen eingegangen'), 'phone_planned' => $this->t('Telefoninterview geplant'),
        'phone_completed' => $this->t('Telefoninterview abgeschlossen'), 'live_planned' => $this->t('Vorstellungsgespräch geplant'),
        'decision_pending' => $this->t('Entscheidung ausstehend'), 'basis_qualification' => $this->t('Basisqualifikation'),
        'approved_for_hire' => $this->t('Zur Einstellung freigegeben'), 'accepted' => $this->t('Zusage'), 'rejected' => $this->t('Absage'),
        'withdrawn' => $this->t('Zurückgezogen'), 'hired' => $this->t('Eingestellt'), 'archived' => 'Archiviert',
        ];
    }

    private function messages(): array {
        return [
        'screening' => $this->t('vielen Dank für Ihre Bewerbung. Wir prüfen Ihre Unterlagen nun sorgfältig.'),
        'questionnaire_pending' => $this->t('wir möchten Sie bitten, den angekündigten Fragebogen zu bearbeiten. Die weiteren Informationen erhalten Sie gesondert.'),
        'questionnaire_received' => $this->t('vielen Dank. Ihr ausgefüllter Fragebogen ist bei uns eingegangen.'),
        'phone_planned' => $this->t('wir möchten Sie zu einem Telefoninterview einladen. Den konkreten Termin stimmen wir mit Ihnen ab.'),
        'phone_completed' => $this->t('vielen Dank für das freundliche Telefoninterview. Wir melden uns nach unserer weiteren Abstimmung bei Ihnen.'),
        'live_planned' => $this->t('wir möchten Sie zu einem persönlichen Vorstellungsgespräch einladen. Die Termindetails stimmen wir mit Ihnen ab.'),
        'decision_pending' => $this->t('vielen Dank für das Gespräch. Ihre Bewerbung befindet sich nun in unserer abschließenden Entscheidung.'),
        'basis_qualification' => $this->t('wir möchten Sie für die nächste Basisqualifikation vormerken. Die konkreten Termine und Informationen stimmen wir mit Ihnen ab.'),
        'approved_for_hire' => $this->t('wir freuen uns, Ihnen mitteilen zu können, dass Ihre Einstellung fachlich freigegeben wurde. Die nächsten Schritte stimmen wir mit Ihnen ab.'),
        'accepted' => $this->t('wir freuen uns über Ihre Zusage und melden uns zu den nächsten Schritten.'),
        'rejected' => $this->t('vielen Dank für Ihr Interesse und das entgegengebrachte Vertrauen. Leider können wir Ihre Bewerbung im aktuellen Verfahren nicht weiter berücksichtigen.'),
        'withdrawn' => $this->t('wir bestätigen, dass Ihre Bewerbung aus dem laufenden Verfahren zurückgezogen wurde.'),
        'hired' => $this->t('herzlich willkommen. Wir freuen uns auf die Zusammenarbeit und informieren Sie über die nächsten organisatorischen Schritte.'),
        'archived' => $this->t('das Bewerbungsverfahren ist abgeschlossen. Vielen Dank für den bisherigen Austausch.'),
        ];
    }

    /**
     * @param array<string,list<string>> $transitions
     * @return list<array{fromStatus:string,toStatus:string,name:string,subject:string,body:string,bodyFormat:string,enabled:bool,defaultTiming:string}>
     */
    public function templates(array $transitions): array {
        $result = [];
        foreach ($transitions as $fromStatus => $targets) {
            foreach ($targets as $toStatus) {
                $fromLabel = $this->labels()[$fromStatus] ?? $fromStatus;
                $toLabel = $this->labels()[$toStatus] ?? $toStatus;
                $message = $this->messages()[$toStatus] ?? $this->t('wir möchten Sie über den aktuellen Stand Ihrer Bewerbung informieren.');
                $result[] = [
                    'fromStatus' => $fromStatus,
                    'toStatus' => $toStatus,
                    'name' => $toStatus === 'withdrawn' ? $this->t('Standard · Bewerbung zurückgezogen') : "Standard · {$fromLabel} → {$toLabel}",
                    'subject' => "Ihre Bewerbung als {{job_title}} · {$toLabel}",
                    'body' => '<p>Guten Tag {{given_name}} {{family_name}},</p><p>' . $message . '</p><p>Stelle: <strong>{{job_title}}</strong></p><p>Freundliche Grüße<br>Ihr Recruiting-Team</p>',
                    'bodyFormat' => 'html',
                    'enabled' => false,
                    'defaultTiming' => StatusMailWorkflow::TIMING_IMMEDIATE,
                ];
            }
        }
        return $result;
    }
}
