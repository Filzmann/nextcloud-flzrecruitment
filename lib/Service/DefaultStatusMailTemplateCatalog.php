<?php

declare(strict_types=1);

namespace OCA\FlzRecruitment\Service;

/** Liefert neutrale, standardmäßig deaktivierte Vorlagen für jede erlaubte Statuskante. */
final class DefaultStatusMailTemplateCatalog {
    private const LABELS = [
        'received' => 'Eingegangen', 'screening' => 'Vorauswahl', 'questionnaire_pending' => 'Fragebogen ausstehend',
        'questionnaire_received' => 'Fragebogen eingegangen', 'phone_planned' => 'Telefoninterview geplant',
        'phone_completed' => 'Telefoninterview abgeschlossen', 'live_planned' => 'Vorstellungsgespräch geplant',
        'decision_pending' => 'Entscheidung ausstehend', 'basis_qualification' => 'Basisqualifikation',
        'approved_for_hire' => 'Zur Einstellung freigegeben', 'accepted' => 'Zusage', 'rejected' => 'Absage',
        'withdrawn' => 'Zurückgezogen', 'hired' => 'Eingestellt', 'archived' => 'Archiviert',
    ];

    private const MESSAGES = [
        'screening' => 'vielen Dank für Ihre Bewerbung. Wir prüfen Ihre Unterlagen nun sorgfältig.',
        'questionnaire_pending' => 'wir möchten Sie bitten, den angekündigten Fragebogen zu bearbeiten. Die weiteren Informationen erhalten Sie gesondert.',
        'questionnaire_received' => 'vielen Dank. Ihr ausgefüllter Fragebogen ist bei uns eingegangen.',
        'phone_planned' => 'wir möchten Sie zu einem Telefoninterview einladen. Den konkreten Termin stimmen wir mit Ihnen ab.',
        'phone_completed' => 'vielen Dank für das freundliche Telefoninterview. Wir melden uns nach unserer weiteren Abstimmung bei Ihnen.',
        'live_planned' => 'wir möchten Sie zu einem persönlichen Vorstellungsgespräch einladen. Die Termindetails stimmen wir mit Ihnen ab.',
        'decision_pending' => 'vielen Dank für das Gespräch. Ihre Bewerbung befindet sich nun in unserer abschließenden Entscheidung.',
        'basis_qualification' => 'wir möchten Sie für die nächste Basisqualifikation vormerken. Die konkreten Termine und Informationen stimmen wir mit Ihnen ab.',
        'approved_for_hire' => 'wir freuen uns, Ihnen mitteilen zu können, dass Ihre Einstellung fachlich freigegeben wurde. Die nächsten Schritte stimmen wir mit Ihnen ab.',
        'accepted' => 'wir freuen uns über Ihre Zusage und melden uns zu den nächsten Schritten.',
        'rejected' => 'vielen Dank für Ihr Interesse und das entgegengebrachte Vertrauen. Leider können wir Ihre Bewerbung im aktuellen Verfahren nicht weiter berücksichtigen.',
        'withdrawn' => 'wir bestätigen, dass Ihre Bewerbung aus dem laufenden Verfahren zurückgezogen wurde.',
        'hired' => 'herzlich willkommen. Wir freuen uns auf die Zusammenarbeit und informieren Sie über die nächsten organisatorischen Schritte.',
        'archived' => 'das Bewerbungsverfahren ist abgeschlossen. Vielen Dank für den bisherigen Austausch.',
    ];

    /**
     * @param array<string,list<string>> $transitions
     * @return list<array{fromStatus:string,toStatus:string,name:string,subject:string,body:string,bodyFormat:string,enabled:bool,defaultTiming:string}>
     */
    public function templates(array $transitions): array {
        $result = [];
        foreach ($transitions as $fromStatus => $targets) {
            foreach ($targets as $toStatus) {
                $fromLabel = self::LABELS[$fromStatus] ?? $fromStatus;
                $toLabel = self::LABELS[$toStatus] ?? $toStatus;
                $message = self::MESSAGES[$toStatus] ?? 'wir möchten Sie über den aktuellen Stand Ihrer Bewerbung informieren.';
                $result[] = [
                    'fromStatus' => $fromStatus,
                    'toStatus' => $toStatus,
                    'name' => $toStatus === 'withdrawn' ? 'Standard · Bewerbung zurückgezogen' : "Standard · {$fromLabel} → {$toLabel}",
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
