# Architektur des ersten AD-Recruitment-Durchstichs

## Technische Identität

Die Nextcloud-App-ID, Route, Asset-ID, DDEV-Mount und Rollengruppen verwenden
`adrecruitment`. Der bestehende PHP-Namespace `OCA\Recruitment` und die
app-eigenen Tabellen mit Präfix `rec_` bleiben bewusst stabil. Dadurch kann
eine bereits angelegte lokale Installation unter der neuen App-ID dieselben
Fachdaten idempotent weiterverwenden, ohne Tabellen zu kopieren oder
umzubenennen.

## Schichten

Die App trennt Domänen- und Anwendungslogik, Datenzugriff, serverseitige
Berechtigungen, Controller und Browseroberfläche. `RecruitmentRepository`
bindet alle QueryBuilder-Werte. Controller übersetzen HTTP-Anfragen und
Fehler, entscheiden aber keine Fachregeln.

## Zustände und Nebenläufigkeit

Bewerbungen starten in `received`. Zulässige Übergänge werden zentral
festgelegt und atomar mit einem Statusprotokolleintrag gespeichert.
Interviewinstanzen beginnen als `not_started`, wechseln beim ersten Entwurf
nach `in_progress` und werden nach erfolgreicher Pflichtfeldprüfung
`completed`. Jede Entwurfsänderung benötigt die gelesene Versionsnummer;
abweichende Versionen werden als Konflikt abgewiesen.

Interviewinstanzen speichern den vollständigen JSON-Snapshot der verwendeten
Vorlagenrevision. Spätere Änderungen an Vorlage, Fragen oder Bubbles ändern
weder Snapshot noch Antworten bestehender Interviews.

## Berechtigungen

Nextcloud-Admins besitzen alle App-Rechte. Zusätzlich sind folgende
Nextcloud-Gruppen vorgesehen:

| Gruppe | Recht im ersten Durchstich |
| --- | --- |
| `adrecruitment-admin` | alle App-Rechte |
| `adrecruitment-managers` | Stellen und Vorlagen verwalten |
| `adrecruitment-editors` | Personen und Bewerbungen bearbeiten |
| `adrecruitment-interviewers` | Interviews durchführen |
| `adrecruitment-readers` | ausschließlich lesen |
| `adrecruitment-documents` | für spätere besonders geschützte Dokumente reserviert |
| `adrecruitment-communication` | für spätere Kommunikationsfreigabe reserviert |

Die beiden reservierten Gruppen erhalten noch keine ausführbare Funktion.
Jeder API-Pfad prüft das für den Anwendungsfall erforderliche Recht
serverseitig.

## Migration

Die erste Migration erstellt ausschließlich app-eigene Tabellen. Bei einer
frischen Installation legt sie diese neu an; bei der Umbenennung von
`recruitment` auf `adrecruitment` erkennt sie die bestehenden `rec_`-Tabellen
und übernimmt sie ohne Transformation. Die Migration ist additiv und
wiederholbar, weil jede Tabelle vor der Anlage geprüft wird.
Ein Rollback nach produktiver Datennutzung ist nicht automatisch möglich; die
Tabellen dürfen nur nach gesonderter Datenexport- und Löschentscheidung
entfernt werden.

## Dokumente

Dokumentablage wird im ersten Durchstich nicht implementiert. Bevorzugte
spätere Strategie ist Nextcloud-Dateispeicher in einem klar abgegrenzten,
serverseitig berechtigten App-Ordner, weil Freigaben, Backup und
Wiederherstellung dann Nextcloud-nativ bleiben. AppData wäre für interne,
nicht direkt nutzerverwaltete Ableitungen geeignet, aber nicht als beiläufige
Hauptakte. Vor Umsetzung sind Besitzmodell, besonders geschützte Dokumente,
Lösch- und Aufbewahrungsregeln, Shares, Backup und Wiederherstellung anhand
eines eigenen Sicherheitsentscheids zu klären.

## Spätere Integrationen

Ein IMAP-Adapter darf nur öffentliche Protokollschnittstellen verwenden und
keine Tabellen anderer Apps lesen. Ein späterer Import speichert eine
normalisierte Message-ID als Idempotenzschlüssel und besitzt Quarantäne- und
Fehlerzustände. Benachrichtigungen werden später als transaktionale Outbox mit
eindeutigem Versandauftrag, Hintergrundjob und kontrollierten Wiederholungen
modelliert. Beides ist geplant, aber noch nicht umgesetzt.
