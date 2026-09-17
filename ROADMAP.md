# Roadmap – AD Recruitment

## Offene suiteweite Admin-Freigabe

Nur Mitglieder von `Datenschutzbeauftragte` dürfen pro App und aktivem Nextcloud-Administrationskonto eine Freigabe erteilen oder widerrufen. Die Freigabe bleibt auf höchstens 24 Stunden begrenzt und app-lokal auditierbar; native Administration allein genügt nicht. Ohne Freigabe gilt eine aussagekräftige sichere Meldung, ein direkter Freigabelink erscheint nur bei gleichzeitiger Datenschutzbeauftragten- und Admin-Rolle. Runtime-, UI-, Controller- und Allow-/Deny-Tests bleiben offen.

Diese Datei enthält ausschließlich offene Arbeit und Freigabegates. Der
aktuelle Funktionsumfang steht in `README.md`, erledigte Änderungen in
`CHANGELOG.md`, der Sollprozess in `docs/product-process.md` und die
geltende Architektur in `docs/architecture.md`.

## Freigabegates

### RECR-POOL-ACTIVATION – Bewerberpool produktiv aktivieren

- Versionierten Datenschutzhinweis freigeben.
- Den konfigurierbaren Zwölfmonats-Standardwert, seine ausschließlich der
  Nextcloud-Gruppe `Datenschutzbeauftragte` erlaubte Pflege, die rückwirkende
  Neuberechnung vorhandener Poolakten sowie die vollständige Löschung von
  Poolprofil, Unterlagen und personenbezogenem Einwilligungsnachweis bei
  Widerruf technisch umsetzen. Nach Ablauf gilt eine zehn Tage lange, rein der
  Erneuerung dienende Sperrphase ohne Matching, Kontakt oder Hinweise; ohne
  Erneuerung wird danach vollständig gelöscht. Vierzehn Tage vor Ablauf darf
  genau eine inhaltsarme Erinnerung versandt werden.
- Externen Self-Service für Erteilung, Erneuerung und Widerruf der
  versionierten Einwilligung bereitstellen.
- Transparente, nicht wertende Regelvorschläge auf Berufsgruppe,
  Stundenkorridor und Region begrenzen. Jeder Hinweis auf eine passende neue
  Ausschreibung benötigt eine ausdrückliche manuelle Bestätigung und
  Versandauslösung durch Personalreferat oder bereichsgebundene Vertretung.
- Neue Bewerbungen als eigenständige Akten anlegen. Poolfelder und Unterlagen
  dürfen erst nach ausdrücklicher Bestätigung der Bewerberperson als
  bearbeitbare Vorlage übernommen werden; eine automatische Reaktivierung ist
  ausgeschlossen.

## Prozess und Arbeitsoberfläche

### RECR-DELEGATE-SCOPES – Vertretungen auf feste Bereiche begrenzen

- Bestehende globale oder nur bewerbungsbezogene Vertretungsfreigaben auf den
  verbindlichen Zielvertrag aus ausdrücklich zugewiesenen festen Bereichen
  überführen. Personalreferent*innen bleiben fachlich verantwortlich.
- Für jede Liste, Suche, Detailansicht, Bewertung, Poolaktion, Kommunikation,
  Dokument- und Exportoperation Allow-, Deny-, Fremdobjekt- und
  Nebenwirkungsfreiheit serverseitig nachweisen.

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

### RECR-APPLICANT-RATING – manuelle Bewertung und Geschlechtskennzeichnung

- Eine zentrale, intuitive Fünf-Sterne-Bewertung für Bewerber*innen ergänzen
  und in allen Ansichten derselben Person verfügbar machen. Sie wird
  ausschließlich manuell durch Personalreferent*innen oder fest
  bereichsgebundene Vertretungen gesetzt und berücksichtigt. Sie löst weder
  algorithmisches Scoring, Ranking, Profiling, Empfehlungen, Filter noch eine
  automatische Entscheidung oder sonstige Folge aus und wird nicht extern
  offengelegt. Die endgültige Auswahl bleibt immer eine menschliche
  Entscheidung.
- Die Kennzeichnung unmittelbar an das kanonische Stammdatum `Geschlecht`
  binden: Anzeigen oder Ändern von `m/w/d` liest beziehungsweise aktualisiert
  immer denselben Wert. Das Feld wird nur für Assistenz-Bewerber*innen manuell
  durch Personalreferat oder fest bereichsgebundene Vertretungen gepflegt,
  nicht abgeleitet oder bewertet und ausschließlich intern nach
  Least-to-know verwendet. Assistenznehmer*innen erhalten daraus keine
  Information. Die spätere Übergabe ist nur als Systemgrenze festzuhalten und
  wird in diesem Paket nicht modelliert.

### RECR-SBV-PARTICIPATION – optionale Angabe und SBV-Beteiligung

- Ausschließlich die freiwillige Angabe `schwerbehindert oder gleichgestellt`
  vorsehen; GdB-Zahl, Diagnose, medizinische Details sowie automatische OCR,
  Extraktion oder Bewertung sind ausgeschlossen.
- Die app-spezifische native Nextcloud-Gruppe
  `schwerbehindertenvertretung` und ihren eng begrenzten Zugriff umsetzen.
  Fehlender oder ungeklärter Organisationsstatus wirkt sperrend.
- Bei Angabe die SBV mit einer inhaltsarmen Fallreferenz benachrichtigen und
  die Einstellungsentscheidung bis zur dokumentierten Beteiligung sperren.
  Eine ausdrückliche Ablehnung der Beteiligung durch die Bewerberperson ist
  bis zur Einstellungsentscheidung widerrufbar.
- Die Betriebsratsbeteiligung bleibt vorerst vollständig außerhalb der App.
  Eine optionale respektvolle Wunschanrede getrennt von `m/w/d` erst nach
  gegebenenfalls erforderlicher externer BR-Freigabe aktivieren; keine
  Ableitung, Bewertung oder Auswahlwirkung.

## Dokumente und Eingang

### RECR-DOCUMENTS – vollständige Bewerbungsakte

- Besitzmodell, Nextcloud-Dateispeicher, Ordnergrenzen, besonders geschützte
  Dokumente, Shares, Virenprüfung, Export, Backup und Wiederherstellung
  festlegen.
- Den konfigurierbaren Sechsmonats-Standardwert nach Verfahrensabschluss für
  reguläre Bewerbungsakten, Unterlagen und Kommunikation koordiniert in
  Datenbank und AppData umsetzen. Für zur Einstellung freigegebene Akten gilt
  derselbe Höchstzeitraum ab Freigabe zur manuellen Weiterverarbeitung und zum
  selektiven Export; eine frühere manuelle Löschung bleibt möglich.
  Friständerungen dürfen nur Mitglieder der Nextcloud-Gruppe
  `Datenschutzbeauftragte` vornehmen und gelten anhand des ursprünglichen
  Abschlusszeitpunkts auch für vorhandene Daten.
- Vor jeder Retention-Ausführung Policyversion und Wirksamkeitszeitpunkt,
  sichere rückwirkende Neuberechnung, rechtliche beziehungsweise
  datenschutzrechtliche Sperren, Löschreihenfolge, Nebenläufigkeit,
  Wiederanlauf, Restore-Neuplanung aus dem ursprünglichen Trigger,
  Fehlernachweis und Roll-forward entscheiden, implementieren und abnehmen.
  Technische Löschfehlernachweise enthalten keine Bewerbungsinhalte und werden
  nach dreißig Tagen gelöscht; nach automatischen Retries erhält
  `Datenschutzbeauftragte` nur App, Datenklasse, Zeitpunkt und technische
  Referenz.
  Bis dahin findet keine automatische Retention-Ausführung statt.

### RECR-DATA-SUBJECT-RIGHTS – manueller Betroffenenrechteprozess

- Identität außerhalb der App prüfen; Personalreferat übergibt dem
  Datenschutz-Center ausschließlich eine stabile Bewerber-ID. Eine Suche nach
  Name oder E-Mail-Adresse ist ausgeschlossen.
- Daten nutzerbezogen als prüfbare Vorschau zusammenstellen; Versand erfolgt
  erst nach manueller Datenschutzprüfung über einen verifizierten externen
  Kanal.
- Berichtigungen durch Personalreferat oder fest bereichsgebundene
  Vertretungen ausführen und im Datenschutzvorgang bestätigen. Lösch- und
  Einschränkungswünsche unter Wahrung rechtlicher Sperren technisch
  durchsetzen.
- Rechtevorgang und Ergebnis sechs Monate nach Abschluss löschen. Manuelle
  Exporte protokollieren nur Empfänger, Umfang und Zeitpunkt, keine Datenkopie,
  und werden ebenfalls sechs Monate aufbewahrt.

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
