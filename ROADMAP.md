# Roadmap – AD Recruitment

Diese Datei enthält ausschließlich zukünftige Ziele und freigegebene
Umsetzungsaufgaben. Geltende Fach-, Rechte-, Sicherheits- und
Architekturregeln stehen in `AGENTS.md`.

## Freigegebene Umsetzungsaufgaben

### RECR-AD-CATALOG – Recruitment an den Produktkatalog anbinden

Status: bereit nach `LB-AD-CATALOG`

- Stabile App-ID `adrecruitment`, Produkttyp, Reihenfolge und technische
  Einstiegsroute durch einen Consumer-Contract gegen den LocalBase-Katalog
  absichern.
- Standalone-Navigation und OrgSuite-Menüintegration gemäß den
  Katalogeigenschaften charakterisieren; sichtbare Labels appbezogen
  lokalisieren.
- Die Katalogaufnahme erweitert ohne separate Entscheidung weder
  Fachberechtigungen noch bestehende AD-Produkt- oder Suitearchive.
- Aktivierte/deaktivierte App, Standalone, OrgSuite, ungültige Route,
  fehlender Katalogprovider und direkte serverseitige Zielberechtigung testen.
- Gemeinsam mit `PARENT-AD-CATALOG`, `LB-AD-CATALOG`,
  `ORGS-AD-CATALOG` und `ADS-AD-CATALOG-DOCS` abnehmen.

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

- Den bestehenden Stellen-, Personen-, Bewerbungs- und Interviewprozess auf
  einem realitätsnahen Staging fachlich, sicherheitlich und
  datenschutzbezogen abnehmen.
- Dokumentablage, Mailimport, Mailversand und öffentliche Fragebögen nur nach
  jeweils eigener Architektur-, Rechte- und Datenschutzfreigabe planen.
