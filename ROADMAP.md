# Roadmap – AD Recruitment

Diese Datei enthält ausschließlich offene Arbeit und Freigabegates. Der
aktuelle Funktionsumfang steht in `README.md`, erledigte Änderungen in
`CHANGELOG.md`, der Sollprozess in `docs/product-process.md` und die
geltende Architektur in `docs/architecture.md`.

## Freigabegates

### RECR-POOL-ACTIVATION – Bewerberpool produktiv aktivieren

- Versionierten Datenschutzhinweis freigeben.
- Allgemeine Löschregel und Löschprozess festlegen.
- Externen Self-Service für Einwilligung und Widerruf bereitstellen.

## Prozess und Arbeitsoberfläche

### RECR-PROCESS-CONFIG – konfigurierbare Prozessstati

- Sichtbare Stati durch administrierbare Definitionen mit stabiler ID,
  Reihenfolge und zulässigen Übergängen ablösen.
- Geschützte technische Kategorien erhalten.
- BQ-Anzeigen an stabile Durchlauf-IDs binden.
- Bestandsstatus und Verläufe verlustfrei migrieren.

### RECR-WORKBENCH – verbleibende Filter

- Letzte Aktivität und nächste Aufgabe nach Einführung der zugehörigen
  Fachobjekte als Filter und Sortierung ergänzen.
- Optimistische Sperren, Konfliktanzeige und vollständige
  Statusprotokollierung erhalten.

### RECR-APPLICANT-RATING – Bewertung und Geschlechtskennzeichnung

- Eine zentrale, intuitive Fünf-Sterne-Bewertung für Bewerber*innen ergänzen
  und in allen Ansichten derselben Person verfügbar machen.
- Die Kennzeichnung unmittelbar an das kanonische Stammdatum `Geschlecht`
  binden: Anzeigen oder Ändern von `m/w/d` liest beziehungsweise aktualisiert
  immer denselben Wert. In allen Bewerber*innenansichten erscheint er mit
  eindeutigem Icon sowie barrierefreiem Textlabel; ohne Angabe bleibt der Wert
  unbekannt.

## Dokumente und Eingang

### RECR-DOCUMENTS – vollständige Bewerbungsakte

- Besitzmodell, Nextcloud-Dateispeicher, Ordnergrenzen, besonders geschützte
  Dokumente, Shares, Virenprüfung, Export, Backup und Wiederherstellung
  festlegen.
- Aufbewahrung, Archivierung und Löschung einschließlich Rechtsgrundlage,
  Sperren und Nachweisen entscheiden.

### RECR-MAIL-INBOX – reale Postfächer anbinden

- Mehrere administrativ konfigurierte Bewerbungspostfächer über einen
  freigegebenen Empfangsadapter anbinden.
- Postfachabruf und Quarantäne-Retry als Hintergrundprozess ergänzen.
- Secrets ausschließlich über geeignete Nextcloud-native Mechanismen
  verwalten.
- Websiteformat und freie Bewerbungs-E-Mails mit neutralen Testdaten abnehmen.

### RECR-DATA-EXTRACTION – OCR und weitere Dateiformate

- Unterstützte Formate, OCR-Bedarf, Qualitätsgrenzen und manipulierte
  Dokumente vor der technischen Auswahl entscheiden.
- Ein lokales Servermodell nur nach Datenschutzprüfung und über einen
  getrennt getesteten Providervertrag aktivieren.

## Aktivitäten und Kommunikation

### RECR-TEMP-ACTIVITIES – befristete Zwischenaktivitäten

- Aktivitäten mit Vorlage, Revision, Verantwortung, Frist und Zuständen
  modellieren.
- Eng begrenzte, befristete und widerrufbare Interview-/Fragebogenzugänge
  einschließlich sicherem Gerätewechsel umsetzen.
- Ablauf, Widerruf und ausdrückliche Abgabe serverseitig erzwingen.

### RECR-COMMUNICATION – Kommunikationschronik

- Antworten über technische Kennungen zuordnen und unklare Antworten in den
  manuellen Eingang geben.
- Ein- und Ausgang chronologisch darstellen, ohne interne Notizen
  offenzulegen.

### RECR-STATUS-MAIL-ACCEPTANCE – Statusmailpfad abnehmen

- Entwurf, Freigabezeitpunkt, Testumleitung, Outbox, Retry und
  Versandnachweis auf Staging prüfen.
- Ursprünglichen, freigegebenen und tatsächlichen Empfänger getrennt und
  datensparsam nachweisen.

### RECR-AUTO-ASSIGN – optionale Zuordnungsregeln

- Erst nach stabilem manuellem Mailprozess eindeutige Regeln ergänzen.
- Mehrdeutige Treffer bestätigungspflichtig lassen und Verarbeitung
  auditierbar sowie idempotent halten.

## Basisqualifikation

### RECR-BASISQUALIFICATION – verbleibender lokaler Fallback und Consumer

- Verschiebungen zwischen Durchläufen mit erhaltenem Verlauf und
  kontrollierte Wiederzuordnungen ergänzen.
- Einen konfigurierbaren Zugriffsbeginn am Startdatum eines BQ-Durchlaufs
  fachlich entscheiden und auditierbar umsetzen.
- Den eigenständigen BQ-Planer nur optional über einen kleinen versionierten
  Capability-/Event-Vertrag anbinden; der manuelle Standalone-Fallback bleibt
  gültig.

### RECR-FIRST-GUIDE-END – Ende der Erstbegleitung

- Frist, Bezugsereignis, Ausnahmen und Audit fachlich festlegen.
- Automatisches Ende erst danach ergänzen; manuelles Ende und Wiederfreigabe
  bleiben möglich.

## Durchgängige Abnahme

- Das Abnahmeformular je offenem Paket ergänzen.
- Den vollständigen Ablauf vom Eingang bis Abschluss beziehungsweise
  Einstellungsübergabe auf Staging fachlich, sicherheitlich, barrierebezogen
  und datenschutzbezogen prüfen.

## Bewusst zurückgestellt – niedrigste Priorität

### RECR-L10N – Oberfläche und Vorlagen lokalisieren

Status seit 17. September 2026: Die Umsetzung beginnt erst nach allen höher
priorisierten Roadmap-Aufgaben und einer erneuten ausdrücklichen Freigabe des
Root-Vorhabens `ZM-06`. Neue Funktionen und Codeänderungen berücksichtigen
die spätere Lokalisierbarkeit an den jeweils berührten Stellen, lösen aber
keine flächige Umstellung oder Übersetzungsimplementierung aus.

App-ID, Namespace, Tabellenpräfix, Status-, Revisions- und API-Schlüssel sowie
gespeicherte Freitexte bleiben bei der späteren Umsetzung sprachneutral.
