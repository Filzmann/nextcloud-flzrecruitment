# Roadmap – AD Recruitment

Diese Datei enthält ausschließlich zukünftige Ziele und freigegebene
Umsetzungsaufgaben. Geltende Fach-, Rechte-, Sicherheits- und
Architekturregeln stehen in `AGENTS.md`. Das fachliche Zielbild und der
durchgängige Sollprozess stehen in
[`docs/product-process.md`](docs/product-process.md). Die folgenden Pakete
setzen dieses Zielbild um; die Reihenfolge darf nach Abhängigkeiten angepasst,
der fachliche Umfang aber nicht still entfernt werden.

## Zukunftsplanung – nicht freigegeben

### RECR-L10N – AD Recruitment vollständig lokalisieren

Status: später, nicht freigegeben; Pilot-App, Reihenfolge und Rohtext-Gate
werden vor jeder Umsetzung appübergreifend separat freigegeben

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

### RECR-PROCESS-CONFIG – Konfigurierbare Prozessstati

Status: fachlich beschrieben, Umsetzung ausstehend

- Fest codierte sichtbare Stati durch administrativ konfigurierbare
  Statusdefinitionen mit stabiler ID, Name, Reihenfolge, aktiver Verwendung
  und zulässigen Übergängen ablösen.
- Geschützte technische Kategorien für Eingang, aktiven Prozess,
  Basisqualifikation, Warteposition, Ablehnung/Rückzug,
  Einstellungsfreigabe, Einstellung und Archiv beibehalten.
- Sichtbare Stati wie `BQ MM/YY` an einen stabil identifizierten
  BQ-Durchlauf binden, ohne Monat und Jahr in technische Statusschlüssel
  einzubauen.
- Bestandsstatus und -verläufe verlustfrei migrieren; deaktivierte oder
  umbenannte Stati in historischen Vorgängen weiterhin verständlich anzeigen.

### RECR-WORKBENCH – Karten- und Tabellenarbeitsplatz

Status: Anzeige, Grundfilter und Statusinteraktion umgesetzt; weitere Filter offen

- Umgesetzt: eine Kartenansicht nach Status und eine gleichwertige Tabellenansicht auf
  derselben serverseitig berechtigten Datenbasis bereitstellen.
- Teilweise umgesetzt: Freitext, Stelle, Status, Bürobereich, Zuständigkeit und
  inklusiven Eingangszeitraum filtern sowie nach Eingang, Person, Stelle oder
  Prozessreihenfolge sortieren. Letzte Aktivität und nächste Aufgabe bleiben
  bis zur Umsetzung der zugehörigen Fachobjekte offen.
- Umgesetzt: Statuswechsel in der Kartenansicht per Drag-and-drop und über eine
  vollständige Tastaturaktion; beide Wege verwenden dieselben vom Server
  gelieferten Ziele und den bestehenden versionsgeschützten Statusendpunkt.
- Umgesetzt: explizite Statusaktion unmittelbar in der Tabellenansicht; sie
  verwendet denselben zentralen Übergangspfad und dieselbe Bürobereichswahl
  wie die Kartenansicht.
- Beibehalten: optimistische Sperren, verständliche Konflikte und vollständige
  Statusprotokollierung beibehalten.

### RECR-DOCUMENTS – Sichere Bewerbungsakte und Unterlagen

Status: private Importablage und geschützte PDF-Vorschau umgesetzt;
vollständige Akte offen

- Besitzmodell, Nextcloud-Dateispeicher, Ordnergrenzen, besonders geschützte
  Dokumente, Shares, Virenprüfung, Vorschau, Export, Backup und
  Wiederherstellung festlegen.
- Aufbewahrungs-, Archivierungs- und Löschregeln einschließlich
  Rechtsgrundlage, Sperren und Nachweisen entscheiden.
- Umgesetzt: importierte Original-Mailtexte unverändert in der App-Datenbank
  und bis zu fünf validierte PDFs duplikatfrei unter serverseitig erzeugten
  Hashpfaden im privaten Nextcloud-AppData ablegen. Mailtext und Metadaten sind
  nach Zuordnung nur im erlaubten Bewerbungsscope sichtbar.
- Umgesetzt: berechtigter Inline-Abruf der PDF-Originale mit Integritätsprüfung
  unmittelbar vor der Ausgabe und ohne öffentlichen Nextcloud-Share.
- Offen: nutzerverwaltete weitere Aktenunterlagen, Shares, Virenprüfung,
  Export sowie Aufbewahrung und Löschung.

### RECR-DOCUMENT-REVIEW – PDF-Lightbox und Feldverknüpfungen

Status: erster Durchstich umgesetzt; Ausbau offen

- Umgesetzt: Lebensläufe und sonstige PDF-Bewerbungsunterlagen innerhalb des
  jeweiligen Bewerbungsscope in einer großen, tastaturbedienbaren Lightbox
  anzeigen, ohne das unveränderliche Original zu bearbeiten oder einen
  Nextcloud-Share zu erzeugen.
- Umgesetzt: app-lokal gebündeltes PDF.js rendert Canvas und auswählbare
  Textschicht. Fundstellen entstehen aus einer Textauswahl, einem grafisch
  aufgezogenen Bereich oder barrierearm ersatzweise aus manuell angegebener
  Seite und Quelltext.
- Umgesetzt: Seite, normierte Rechtecke, Quelltext, Zielwert, Zielbewerbung,
  ausführende Person und Zeitpunkt getrennt vom PDF als Herkunftsnachweis
  speichern. Markierungen werden niemals in das Original-PDF eingebrannt.
- Umgesetzt: Fundstellen mit `Vorerfahrung`, `Deutschniveau`, `Geburtsdatum`,
  `Geburtsort` oder dem freien Kommentar der Bewerbung verbinden.
  Deutschniveaus verwenden A1 bis C2 sowie `muttersprachlich` und
  `nicht bewertet`.
- Umgesetzt: strukturierte bestehende Werte nur nach ausdrücklicher
  Bestätigung ersetzen. Neue Inhalte des freien Kommentars immer mit
  Zeilenumbruch anhängen und vorhandenen Inhalt vollständig erhalten.
- Umgesetzt: Leserechte folgen der Bewerbungsakte. Schreiben benötigt
  `manage_documents` und zusätzlich das zum Zielfeld passende Bewerbungs-
  beziehungsweise Vertragsstammdatenrecht; Lohn und rein lesende
  Erstbegleitungen dürfen keine Verknüpfungen ergänzen.
- Umgesetzt: unzugeordnete oder fremde Dokumente, ungültige Zielwerte,
  Seiten, Rechtecke und alte Feldversionen serverseitig ohne Mutation
  abweisen. Wiederholte Schreibrequests sind über einen Client-Schlüssel
  idempotent.

### RECR-MAIL-INBOX – Postfacheingang und manuelle Zuordnung

Status: Importkern, privater Eingang und manuelle Zuordnung umgesetzt; realer
Empfangsadapter und Postfachadministration offen

- Mehrere administrativ konfigurierte Bewerbungspostfächer über einen
  freigegebenen Empfangsadapter anbinden.
- Umgesetzt:
  beschriftete Formularzeilen und eindeutige Kontakt-E-Mail-Adressen aus
  freien Klartextmails als quellmarkierte Vorschläge auswerten; mehrere
  Body-Adressen und unbekannte Berufsrichtungen bleiben ungeklärt.
- Umgesetzt: privaten Eingang sowie die technischen Importzustände `neu`,
  `zugeordnet`, `unklar`, `fehlerhaft` und `ignoriert` persistieren; neue,
  zugeordnete, fehlerhafte und ignorierte Vorgänge sind im ersten Ablauf
  erreichbar. Die automatische Einstufung als `unklar` folgt mit dem Adapter.
- Umgesetzt: normalisierte Nachrichten anhand Message-ID und Inhaltsfingerprint
  idempotent importieren und manuell einer bestehenden Bewerbung zuordnen,
  korrigieren oder als Nicht-Bewerbung schließen.
- Offen: Person und Bewerbung kontrolliert direkt aus dem Eingang neu anlegen,
  Postfachabruf und Quarantäne-Retry als Hintergrundprozess sowie administrative
  Postfachkonfiguration.
- Zugangsdaten und Tokens ausschließlich über geeignete Nextcloud-native
  Secret-/Konfigurationsmechanismen verwalten.
- Automatische Zuordnungsregeln erst in einem späteren Paket ergänzen;
  unsichere Treffer bleiben bestätigungspflichtige Vorschläge.
- Das bestätigte Websiteformat mit zeilenweisen Kontaktfeldern, ausgewählter
  Berufsrichtung, optionaler Nachricht und bis zu fünf PDF-Mailanhängen als
  ersten realen Eingangskanal abnehmen; freie Bewerbungs-E-Mails bleiben
  gleichwertig zulässig.

### RECR-DATA-EXTRACTION – Stammdatenvorschläge

Status: fachlich beschrieben, abhängig von Bewerbungsakte und Mailimport

- Stammdaten aus Nachrichtentexten und geeigneten Anhängen als Vorschläge mit
  Quelle und nachvollziehbarem Erkennungsstatus bereitstellen.
- Vorschläge einzeln bestätigen, korrigieren oder verwerfen lassen und
  vorhandene Werte niemals still überschreiben.
- Besonders sensible Vertragsdaten nicht automatisch aus unsicheren Quellen
  übernehmen.
- Unterstützte Dateiformate, OCR-Bedarf, Qualitätsgrenzen und Umgang mit
  manipulierten Dokumenten vor der technischen Auswahl entscheiden.

### RECR-TEMP-ACTIVITIES – Kurzfragebögen und Zwischenaktivitäten

Status: fachlich beschrieben, Sicherheitsentscheidung ausstehend

- Kurzfragebogen, Telefoninterview, Vorstellungsgespräch und weitere
  administrativ definierte Aktivitäten je Bewerbung temporär aktivieren.
- Vorlage und Revision, Verantwortliche, Frist, Status, Abschluss, Abbruch,
  Ablauf und erneute Freigabe nachvollziehbar speichern.
- Für Online-Kurzfragebögen einen zufälligen, befristeten, widerrufbaren und
  eng auf genau einen Fragebogen begrenzten Zugang ohne Nextcloud-Konto
  bereitstellen.
- Antworten erst nach ausdrücklicher Abgabe als eingegangen behandeln und
  abgelaufene beziehungsweise widerrufene Zugänge serverseitig sperren.

### RECR-COMMUNICATION – Vorlagen und Mailversand

Status: fachlich beschrieben, Sicherheitsentscheidung ausstehend

- Versionierte Nachrichtenvorlagen, bearbeitbare Entwürfe und kontrollierten
  Versand aus der Bewerbung bereitstellen.
- Ausgehende Nachrichten erst nach bestätigtem Versand als gesendet markieren
  und über eine idempotente Outbox mit kontrollierten Wiederholungen senden.
- Antworten anhand technischer Kennungen zuordnen; unklare Antworten in den
  manuellen Eingang geben.
- Ein- und Ausgang chronologisch in der Bewerbungsakte darstellen, ohne
  interne Notizen offenzulegen.

### RECR-STATUS-MAIL-DRAFTS – Bearbeitbare Mails bei Statuswechseln

Status: fachlich freigegeben; nach Dokumentenreview und vor realem Mailversand

- Für jeden zulässigen Statuswechsel optional konfigurieren, ob ein
  Mailentwurf für die Bewerber*innen erzeugt wird; ein Statuswechsel selbst
  darf wegen eines Mailfehlers nicht doppelt ausgeführt werden.
- Personalreferent*innen konfigurieren je Ausgangs-/Zielstatus eine
  versionierte Mailvorlage mit Betreff, Grundtext und verfügbaren Platzhaltern.
  Bestehende Entwürfe behalten den Snapshot der verwendeten Vorlagenrevision.
- Vor jedem Versand den vollständig aufgelösten Betreff und Nachrichtentext
  als bearbeitbaren Entwurf anzeigen. Es gibt keinen unbeaufsichtigten
  Direktversand allein durch den Statuswechsel.
- Im Entwurf vorbereitete, durch Personalreferent*innen verwaltete Textblöcke
  an der Cursorposition einfügen und zusätzlich beliebigen Freitext ergänzen;
  vorhandener Text wird dabei nicht still überschrieben.
- Empfängeradresse, Vorlage, Textblöcke, Freitextänderung, ausführende Person,
  Freigabe und Versandzustand nachvollziehbar halten, ohne Mailinhalte in
  technische Logs zu schreiben.
- Versand über eine idempotente Outbox mit eindeutiger Auftrags-ID,
  CSRF-geschützter Freigabe und kontrolliertem Retry umsetzen. Entwurf,
  freigegeben, versendet, fehlgeschlagen und abgebrochen bleiben getrennte
  Zustände.

### RECR-AUTO-ASSIGN – Optionale Zuordnungsregeln

Status: später, nach stabilem manuellen Mailprozess

- Regeln anhand eindeutiger Postfach-, Empfänger-, Stellen- oder
  Nachrichtenmerkmale administrativ konfigurierbar machen.
- Trefferqualität sichtbar machen; mehrdeutige oder widersprüchliche Treffer
  nie automatisch fest zuordnen.
- Regeländerungen, manuelle Korrekturen und wiederholte Verarbeitung
  auditierbar und idempotent halten.

### RECR-BASISQUALIFICATION – BQ-Prozess für Assistenz

Status: erster manueller Durchstich umgesetzt; Ausbau offen

- Umgesetzt: Stellen fachlich kennzeichnen, für die eine Basisqualifikation erforderlich
  beziehungsweise zulässig ist; andere Berufsgruppen serverseitig vom
  BQ-Prozess ausschließen.
- Umgesetzt: ungefähr zwölf jährliche, jeweils ungefähr zehntägige BQ-Durchläufe mit
  stabiler ID, sichtbarer Bezeichnung `BQ MM/YY`, Zeitraum und Zustand lokal
  verwalten können.
- Umgesetzt: Assistenz-Bewerbungen einem Durchlauf zuordnen und ausstehende,
  geeignete, nicht geeignete, abgebrochene sowie nicht angetretene Teilnahme
  versioniert und auditierbar abbilden.
- Umgesetzt: BQ als vorgelagerte Auswahlphase modellieren: noch keine Beschäftigung und
  keine Einstellungsfreigabe. Die Zuordnung erteilt Lohn dennoch sofort den
  eng begrenzten Stammdatenzugriff zur Vertragsvorbereitung, aber keinen
  Zugriff auf Bewerbungsakte, Interviews oder BQ-Bewertung.
- Umgesetzt: den BQ-begründeten Lohnzugriff bei Abbruch, Nichtteilnahme oder
  fehlender Eignung sofort entziehen; Einstellungsfreigabe nur nach dem
  Ergebnis „geeignet“ und einer ausdrücklichen Personalentscheidung zulassen.
- Offen: Verschiebungen zwischen Durchläufen mit erhaltenem Verlauf und
  kontrollierte Wiederzuordnungen ergänzen.
- Als spätere konfigurierbare Regel den Zugriffsbeginn statt an die Zuordnung
  an das Startdatum des BQ-Durchlaufs binden und Verschiebungen sowie
  manuelle Korrekturen auditierbar behandeln.
- Umgesetzt: den Erstbegleitungszugriff unabhängig davon weiterhin erst mit der
  Einstellungsfreigabe aktivieren.
- Umgesetzt: nach der Bewertung eine ausdrückliche Personalentscheidung verlangen; das
  Bewertungsergebnis darf die Einstellungsfreigabe nicht automatisch
  auslösen.
- Ein späteres eigenständiges BQ-Modul nur optional über einen kleinen,
  versionierten Capability-/Event-Vertrag anbinden. AD Recruitment bleibt
  ohne Provider vollständig manuell nutzbar und greift niemals direkt auf
  dessen Tabellen, Controller oder Assets zu.
- Umgesetzt: Allow-/Deny-Tests für BQ-Zuordnung, Abbruch, Bewertung,
  Einstellungsfreigabe, Lohnprojektion und Erstbegleitung. Tests für die
  spätere Startdatumsregel folgen mit deren Umsetzung.

### RECR-FIRST-GUIDE-END – Automatisches Ende der Erstbegleitung

Status: später; manuelles Ende ist verbindlicher Zwischenstand

- Frist, Bezugsereignis, Ausnahmen und Auditverhalten fachlich festlegen.
- Automatische Beendigung erst danach ergänzen; manuelle Beendigung und
  Wiederfreigabe bleiben möglich.

### RECR-STAGING-ACCEPTANCE – Durchgängige Abnahme

Status: fortlaufend je Arbeitspaket

- Die manuellen Prüfungen im ausfüllbaren
  [`docs/manual-acceptance.md`](docs/manual-acceptance.md) je umgesetztem
  Paket erweitern.
- Den vollständigen Ablauf von Postfacheingang und Zuordnung über
  Karten-/Tabellenbearbeitung, Fragebogen, Interviews, gegebenenfalls
  Basisqualifikation und Entscheidung bis Einstellungsübergabe beziehungsweise
  Abschluss auf realitätsnahem Staging fachlich, sicherheitlich,
  barrierebezogen und datenschutzbezogen abnehmen.
