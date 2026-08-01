# Roadmap – AD Recruitment

Diese Datei enthält ausschließlich zukünftige Ziele und freigegebene
Umsetzungsaufgaben. Geltende Fach-, Rechte-, Sicherheits- und
Architekturregeln stehen in `AGENTS.md`.

## Freigegebene Umsetzungsaufgaben

### RECR-L10N – AD Recruitment vollständig lokalisieren

Status: bereit nach festgelegtem l10n-Pilotvertrag

- Oberfläche, Interviewvorlagenverwaltung, Status-, Validierungs- und
  Fehlermeldungen auf aktive Nextcloud-Locale und Nextcloud-l10n umstellen.
- App-ID, technischer PHP-Namespace, Tabellenpräfix, Bewerbungsstatus,
  Revisions- und API-Schlüssel sowie gespeicherte Freitexte unverändert
  lassen.
- Deutsche Ausgabe, eine weitere Locale, Fallback, Pluralformen,
  Platzhalter, Escaping und unveränderte Interview-Snapshots testen.
- Erst nach vollständiger Migration einen Rohtext-Check für AD Recruitment
  verbindlich schalten.

## Weitere geplante Arbeiten

- Die manuellen Prüfungen werden im ausfüllbaren
  [`docs/manual-acceptance.md`](docs/manual-acceptance.md) dokumentiert.
- Den bestehenden Stellen-, Personen-, Bewerbungs- und Interviewprozess auf
  einem realitätsnahen Staging fachlich, sicherheitlich und
  datenschutzbezogen abnehmen.
- Dokumentablage, Mailimport, Mailversand und öffentliche Fragebögen nur nach
  jeweils eigener Architektur-, Rechte- und Datenschutzfreigabe planen.
