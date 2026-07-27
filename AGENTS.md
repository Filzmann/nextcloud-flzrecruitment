# AGENTS.md – AD Recruitment

## Projekt

Nextcloud-App `adrecruitment` für strukturierte Bewerbungs- und Recruitingprozesse.

Lokale App-URL:

    https://nextcloud-dev.ddev.site/apps/adrecruitment/

Nextcloud-App-ID:

    adrecruitment

## Fachvertrag

- Person und Bewerbung sind getrennte Entitäten; eine Person kann mehrere Bewerbungen besitzen.
- Eine Bewerbung gehört genau einer Person und einer Stelle beziehungsweise Ausschreibung.
- Bewerbungsstatus sind kontrollierte Zustände. Übergänge laufen ausschließlich über den zentralen `ApplicationStatusService` und werden mit Zeitpunkt sowie ausführender Nextcloud-UID protokolliert.
- Interviewvorlagen besitzen Revisionen. Eine Interviewinstanz speichert einen unveränderlichen Snapshot der verwendeten Revision einschließlich Fragen und Antwort-Bubbles.
- Abgeschlossene Interviews werden nicht still verändert. Entwürfe verwenden eine Versionsnummer zur Erkennung konkurrierender Änderungen.
- Antwort-Bubbles unterstützen nur Freitextfragen, überschreiben vorhandenen Text nicht und speichern den resultierenden editierbaren Antworttext.
- Pflichtfragen müssen vor dem Abschluss beantwortet sein.

## Architektur, Rechte und Datenschutz

- Controller bleiben dünn. Fachregeln liegen in Services, Datenzugriff im `RecruitmentRepository`, Berechtigungen im `RecruitmentAccessService` und Browserlogik in getrennten JavaScript-Modulen.
- Rechte werden serverseitig und deny by default über Nextcloud-Adminstatus und app-eigene Nextcloud-Gruppen geprüft. UI-Sichtbarkeit erteilt keine Rechte.
- Personenbezogene Inhalte, Interviewantworten, Dokumentnamen und E-Mail-Inhalte werden nicht in technische Logs geschrieben.
- Dokumentablage, IMAP-Import, Mailversand und öffentliche Fragebogenlinks sind nicht Teil des ersten Durchstichs. Vor ihrer Implementierung ist die jeweilige Architektur- und Sicherheitsentscheidung zu treffen.
- Schreibende Routen verwenden den Nextcloud-CSRF-Schutz. Requestwerte werden validiert; SQL-Werte werden gebunden.
- Der direkte App-Root erfüllt den Nextcloud-Scrollvertrag. Alle Funktionen sind per Tastatur bedienbar, besitzen sichtbaren Fokus und verständliche Fehlerzustände.
- Der technische PHP-Namespace `OCA\Recruitment` und das bestehende
  Tabellenpräfix `rec_` bleiben bei der App-ID-Umbenennung stabil, damit
  bestehende Installationen ihre Klassen und Fachdaten ohne Tabellenkopie
  weiterverwenden.

## Git, DDEV und Tests

- Eigenständiges Git-Repository. Diese Datei und die lokal referenzierten Skills bilden beim direkten Start die vollständige Repository-Steuerung.
- Für Git-, Sandbox-, DDEV-/`occ`-Sicherheit, Verifikation und Learning Candidates gilt der lokal mitgeführte Skill `work-in-nextcloud-app`.
- Jede Verhaltensänderung folgt dem lokalen Skill `test-driven-change`.
- DDEV-Mount: `/var/www/html/html/custom_apps/adrecruitment`.
- Schnelle Tests: `php tests/run.php` und `node tests/run-js.mjs`.
- Controller-, Dependency-Injection-, Migrations- und echte Persistenzänderungen werden zusätzlich in DDEV geprüft.
