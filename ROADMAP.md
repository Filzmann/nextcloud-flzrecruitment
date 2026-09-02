# Roadmap – AD Recruitment

Diese Datei enthält ausschließlich zukünftige Ziele und freigegebene
Umsetzungsaufgaben. Geltende Fach-, Rechte-, Sicherheits- und
Architekturregeln stehen in `AGENTS.md`. Das fachliche Zielbild und der
durchgängige Sollprozess stehen in
[`docs/product-process.md`](docs/product-process.md). Die folgenden Pakete
setzen dieses Zielbild um; die Reihenfolge darf nach Abhängigkeiten angepasst,
der fachliche Umfang aber nicht still entfernt werden.

## Nextcloud-Kompatibilitätsgate

### RECR-NC-COMPAT – OpenDesk-Boden 33 und künftige Majors nachweisen

Status: `info.xml` bleibt bei 34/34; die verwendeten OCP-Schnittstellen sind
auf NC 33.0.7 nur statisch geprüft. Vor `min-version="33"` müssen Fresh
Install/Upgrade, DI, alle Migrationen, Rechte- und Statuspfade,
AppData/PDF-Ablage und kontrollierter Engine-Ausfall, Import, Mail-Outbox,
Jobs, Privacy-/PermissionProvider, Assets und sichtbare Workbench grün sein.
Die Obergrenze wird nur aus einer lückenlosen app-lokalen
`verify-nextcloud-future-compatibility`-Matrix erweitert.

## Bewerberpool-Paket – technisch umgesetzt, Aktivierung ausstehend

- additive Tabellen für reduzierte Poolprofile, unveränderliche Einwilligungsereignisse und nachvollziehbare Stellenhinweise
- standardmäßig deaktiviert; Aktivierung erst mit versioniertem Datenschutzhinweis
- zwölf Monate Einwilligungsdauer und interne Erinnerung 30 Tage vor Ablauf als konfigurierbare Standards
- sofortiger Widerruf und automatisches Ende weiterer Vorschläge bei Ablauf
- keine Interviewnotizen oder automatisierten Entscheidungen als Matching
- offen vor produktiver Aktivierung: Textfreigabe, allgemeine Löschregel samt Löschprozess und externer Self-Service für Einwilligung/Widerruf

## Systemweit gegatete app-lokale Aufgabe

### RECR-L10N – Oberfläche und Vorlagenverwaltung lokalisieren

Aktivierung ausschließlich nach Freigabe des Root-Vorhabens `ZM-06`.
Sichtbare Texte der Oberfläche und Interviewvorlagenverwaltung wechseln
app-lokal auf Nextcloud-l10n. App-ID, Namespace, Tabellenpräfix,
Bewerbungsstatus, Revisions-/API-Schlüssel und gespeicherte Freitexte bleiben
sprachneutral; bestehende Interview-Snapshots werden nicht umgedeutet.

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
- Umgesetzt: Kurzfragebogen auf definierten regulären Kanten überspringen und
  weitere Prozesssprünge ausschließlich als Personalreferat/Admin über das
  Dropdown nach Sicherheitsabfrage durchführen; Ausnahmefähigkeit,
  Serverprüfung und gesondertes Audit sind nicht delegierbar, harte BQ- und
  Einstellungsgrenzen bleiben bestehen.
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

Status: Importkern, privater Eingang, lokale PDF-Textextraktion, manuelle
Zuordnung und kontrollierte Neuanlage umgesetzt; realer Empfangsadapter und
Postfachadministration offen

- Mehrere administrativ konfigurierte Bewerbungspostfächer über einen
  freigegebenen Empfangsadapter anbinden.
- Umgesetzt:
  beschriftete Formularzeilen und eindeutige Kontakt-E-Mail-Adressen aus
  freien Klartextmails als quellmarkierte Vorschläge auswerten; mehrere
  Body-Adressen und unbekannte Berufsrichtungen bleiben ungeklärt.
- Umgesetzt: vom Empfangsadapter bereits normalisierten Lebenslauftext mit
  Mailtext kombinieren und Wunschstunden, Verfügbarkeit, Erfahrung,
  Deutschniveau und Wohnort mit getrennter Herkunft vorschlagen.
- Umgesetzt: textbasierte PDF-Anhänge ohne Netzwerk oder KI bevorzugt über
  lokales `pdftotext`, ersatzweise Ghostscript mit Größen- und Zeitgrenze
  auslesen und Name, Telefon, Wohnort, Wunschstunden, Verfügbarkeit,
  Berufserfahrung und Deutschniveau nach konservativen Stichworten vorschlagen.
- Umgesetzt: privaten Eingang sowie die technischen Importzustände `neu`,
  `zugeordnet`, `unklar`, `fehlerhaft` und `ignoriert` persistieren; neue,
  zugeordnete, fehlerhafte und ignorierte Vorgänge sind im ersten Ablauf
  erreichbar. Die automatische Einstufung als `unklar` folgt mit dem Adapter.
- Umgesetzt: normalisierte Nachrichten anhand Message-ID und Inhaltsfingerprint
  idempotent importieren und manuell einer bestehenden Bewerbung zuordnen,
  korrigieren oder als Nicht-Bewerbung schließen.
- Umgesetzt: bei der bestätigten Zuordnung sichere Kontakt- und
  Eintrittsvorschläge ausschließlich in leere Vertragsfelder übernehmen.
- Umgesetzt: Person und Bewerbung aus einem neuen oder unklaren Eingang nach
  Wahl einer aktiven Stelle und Korrektur der Personendaten atomar anlegen;
  nur einzeln bestätigte Vorschläge werden übernommen, und ein Konflikt lässt
  weder Teilobjekte noch eine Zuordnung zurück.
- Offen: Postfachabruf und Quarantäne-Retry als Hintergrundprozess sowie
  administrative Postfachkonfiguration.
- Zugangsdaten und Tokens ausschließlich über geeignete Nextcloud-native
  Secret-/Konfigurationsmechanismen verwalten.
- Automatische Zuordnungsregeln erst in einem späteren Paket ergänzen;
  unsichere Treffer bleiben bestätigungspflichtige Vorschläge.
- Das bestätigte Websiteformat mit zeilenweisen Kontaktfeldern, ausgewählter
  Berufsrichtung, optionaler Nachricht und bis zu fünf PDF-Mailanhängen als
  ersten realen Eingangskanal abnehmen; freie Bewerbungs-E-Mails bleiben
  gleichwertig zulässig.

### RECR-DATA-EXTRACTION – Stammdatenvorschläge

Status: regelbasierte Mail-/Lebenslaufvorschläge, lokale Text-PDF-Extraktion
und sichere Vorbelegung umgesetzt; Einzelauswahl und OCR offen

- Umgesetzt: Stammdaten aus Nachrichtentexten, lokal ausgelesenen Text-PDFs
  und vom Adapter normalisiertem Lebenslauftext als Vorschläge mit Quelle
  bereitstellen.
- Umgesetzt: die beim Zuordnen übernehmbaren Kontakt- und Eintrittsvorschläge
  einzeln bestätigen, vor der Übernahme korrigieren oder durch Nichtauswahl
  verwerfen; vorhandene Werte werden weiterhin niemals still überschrieben.
- Umgesetzt: Vorschläge für Vorerfahrung und Deutschniveau ebenfalls einzeln,
  korrigierbar und ausschließlich in leere Bewerbungsfelder übernehmen.
- Umgesetzt: den Wunschstunden-Vorschlag kontrolliert und korrigierbar als
  Einzelwert oder Bereich mit derselben Fachregel wie bei der
  Bewerbungsanlage in ein leeres Bewerbungsfeld übernehmen.
- Besonders sensible Vertragsdaten nicht automatisch aus unsicheren Quellen
  übernehmen.
- Unterstützte Dateiformate, OCR-Bedarf, Qualitätsgrenzen und Umgang mit
  manipulierten Dokumenten vor der technischen Auswahl entscheiden.
- Umgesetzt: Im Adminbereich zwischen verfügbaren Verfahren wählen und deren
  lokale Laufzeitverfügbarkeit anzeigen. Die Stichworterkennung ist aktiv;
  `Lokales Server-Modell` bleibt sichtbar, deaktiviert und nicht speicherbar,
  bis ein datenschutzgeprüfter lokaler Provider tatsächlich installiert und
  über einen getrennt getesteten Vertrag angebunden ist.

### RECR-TEMP-ACTIVITIES – Kurzfragebögen und Zwischenaktivitäten

Status: fachlich beschrieben, Sicherheitsentscheidung ausstehend

- Kurzfragebogen, Telefoninterview, Vorstellungsgespräch und weitere
  administrativ definierte Aktivitäten je Bewerbung temporär aktivieren.
- Vorlage und Revision, Verantwortliche, Frist, Status, Abschluss, Abbruch,
  Ablauf und erneute Freigabe nachvollziehbar speichern.
- Für Interviews einen QR-Code anzeigen, der einen sicheren Gerätewechsel auf
  ein anderes Endgerät, beispielsweise ein Tablet, ermöglicht, damit das
  Interview dort ausgeführt und gespeichert werden kann. Der Zugang bleibt
  befristet, widerrufbar, serverseitig berechtigungsgeprüft und eng auf genau
  diese Interviewinstanz begrenzt; der QR-Code enthält keine Bewerbungs- oder
  Interviewinhalte.
- Für Online-Kurzfragebögen einen zufälligen, befristeten, widerrufbaren und
  eng auf genau einen Fragebogen begrenzten Zugang ohne Nextcloud-Konto
  bereitstellen.
- Antworten erst nach ausdrücklicher Abgabe als eingegangen behandeln und
  abgelaufene beziehungsweise widerrufene Zugänge serverseitig sperren.

### RECR-COMMUNICATION – Vorlagen und Mailversand

Status: Statuswechsel-Versand umgesetzt; Antwortzuordnung und vollständige
Kommunikationschronik bleiben ausstehend

- Versionierte Nachrichtenvorlagen, bearbeitbare Entwürfe und kontrollierten
  Versand aus der Bewerbung bereitstellen.
- Ausgehende Nachrichten erst nach bestätigtem Versand als gesendet markieren
  und über eine idempotente Outbox mit kontrollierten Wiederholungen senden.
- Antworten anhand technischer Kennungen zuordnen; unklare Antworten in den
  manuellen Eingang geben.
- Ein- und Ausgang chronologisch in der Bewerbungsakte darstellen, ohne
  interne Notizen offenzulegen.

### RECR-STATUS-MAIL-DRAFTS – Bearbeitbare Mails bei Statuswechseln

Status: implementiert und lokal migriert; manuelle Staging-Abnahme ausstehend

- Umgesetzt: jede der 35 erlaubten Statuskanten erhält additiv eine eigene,
  zunächst deaktivierte Standardvorlage; vorhandene Regeln bleiben autoritativ.
- Umgesetzt: kompakte, nach Ausgangsstatus gruppierte Regelverwaltung mit
  kleinem Rich-Text-Editor, serverseitiger HTML-Allowlist und automatischer
  Klartextalternative.

- Für jeden zulässigen Statuswechsel optional konfigurieren, ob ein
  Mailentwurf für die Bewerber*innen erzeugt wird; ein Statuswechsel selbst
  darf wegen eines Mailfehlers nicht doppelt ausgeführt werden.
- Personalreferent*innen konfigurieren je Ausgangs-/Zielstatus eine
  versionierte Mailvorlage mit Betreff, Grundtext und verfügbaren Platzhaltern.
  Bestehende Entwürfe behalten den Snapshot der verwendeten Vorlagenrevision.
- Vor jedem Versand den vollständig aufgelösten Betreff und Nachrichtentext
  als bearbeitbaren Entwurf anzeigen. Es gibt keinen unbeaufsichtigten
  Direktversand allein durch den Statuswechsel.
- Die Empfängeradresse stammt zunächst aus der kanonischen Bewerberadresse der
  Person. Eine bewusste Korrektur im finalen Versanddialog ist zulässig;
  ursprüngliche Adresse, freigegebene Zieladresse und tatsächlicher
  Zustellempfänger bleiben als getrennte Snapshots nachvollziehbar.
- Im Entwurf vorbereitete, durch Personalreferent*innen verwaltete Textblöcke
  an der Cursorposition einfügen und zusätzlich beliebigen Freitext ergänzen;
  vorhandener Text wird dabei nicht still überschrieben.
- Die Versandfreigabe unterstützt sofortigen Versand, einen frei gewählten
  zukünftigen Zeitpunkt und die gebündelte Planung für den kommenden Montag.
  Ein Versandauftrag wird vor seinem freigegebenen Zeitpunkt nicht verarbeitet.
- Eine zentrale administrative Mail-Testeinstellung leitet bei Aktivierung
  sämtliche ausgehenden Recruitment-Mails serverseitig an genau eine
  validierte Standard-Testadresse um. Oberfläche und Versandnachweis zeigen
  Testmodus, ursprünglichen Empfänger und tatsächlichen Zustellempfänger
  getrennt; kein Client darf die Umleitung umgehen.
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

Die Produktplanung der eigenständigen BQ-Planer-App liegt ausschließlich in
`adbqplanung/ROADMAP.md`; der systemweite Zukunftsplan besitzt nur die
optionale Cross-App-Vertragsgrenze. Für AD Recruitment bleiben die folgenden
lokalen Fallback- und Consumeraufgaben maßgeblich.

- Umgesetzt: die Basisqualifikation aus der Berufsgruppe ableiten: für jede
  Assistenz-Stelle verpflichtend und nicht separat schaltbar; alle anderen
  Berufsgruppen serverseitig vom BQ-Prozess ausschließen.
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
- Die spätere eigenständige BQ-Planer-App nur optional über einen kleinen,
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
