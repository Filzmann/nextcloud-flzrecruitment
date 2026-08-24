# AGENTS.md – AD Recruitment

## Projekt

Nextcloud-App `adrecruitment` für strukturierte Bewerbungs- und Recruitingprozesse.

Lokale App-URL:

    https://nextcloud-dev.ddev.site/apps/adrecruitment/

Nextcloud-App-ID:

    adrecruitment

Die priorisierte Produktplanung und offene Entscheidungen stehen in
`ROADMAP.md`; verbindliche Fach-, Sicherheits- und Architekturregeln bleiben
in dieser Datei. Die vollständige verbindliche Funktions- und
Prozessbeschreibung steht in `docs/product-process.md`; Roadmap und
Implementierung dürfen dieses Zielbild nur nach ausdrücklicher fachlicher
Entscheidung verkürzen oder verändern.

## Produktziel

AD Recruitment bildet einen durchgängigen, an einem Odoo-artigen
Bewerbermanagement orientierten Prozess innerhalb der AD-Suite ab:

- Bewerbernachrichten aus konfigurierten Postfächern landen in einem
  unbearbeiteten Eingang und werden einer Person, Bewerbung und Stelle
  zugeordnet. Die Zuordnung erfolgt zunächst manuell; spätere automatische
  Regeln bleiben bestätigungspflichtig, wenn sie nicht eindeutig sind.
- Bewerbungen werden wahlweise als Karten nach Prozessstatus oder als Tabelle
  bearbeitet. Beide Sichten verwenden dieselben serverseitigen Rechte,
  Filter und Übergangsregeln.
- Prozessstati, Reihenfolge und zulässige Übergänge sind administrativ
  konfigurierbar. Sichtbare Namen wie `Abgelehnt`, `BQ MM/YY`,
  `Telefoninterview` oder `Vorstellungsgespräch` werden nicht als technische
  Sicherheitsmerkmale missbraucht.
- `BQ MM/YY` bezeichnet eine konkrete Basisqualifikation für
  Assistenz-Bewerber*innen. Die ungefähr zehntägige Qualifizierung mit
  anschließender Bewertung ist eine vorgelagerte Auswahlphase und weder
  Beschäftigung noch Einstellungsfreigabe. LoBu-Vertragserstellung und
  Erstbegleitungszugriff beginnen erst mit der Einstellungsfreigabe.
- Die Bewerbungsakte bündelt Stammdaten, Kommunikation, Unterlagen, Verlauf,
  interne Anmerkungen, Fragebögen und Interviews. Aus Nachrichten oder
  Unterlagen erkannte Stammdaten werden nur als nachvollziehbare Vorschläge
  übernommen und überschreiben keine vorhandenen Werte still.
- Kurzfragebögen und weitere Zwischenaktivitäten können je Bewerbung
  temporär aktiviert, befristet, beendet, abgebrochen oder erneut
  freigegeben werden. Öffentliche Zugänge sind eng begrenzte, widerrufbare
  Einmalzugänge ohne Zugriff auf die übrige Bewerbungsakte.
- Die Einstellungsfreigabe übergibt die erforderlichen Vertragsstammdaten an
  LoBu und aktiviert bereichsgebunden den Zugriff der Erstbegleitung.
- Kommunikation, Zuordnung, Statuswechsel, Bearbeitung und Berechtigungen
  bleiben nachvollziehbar; Aufbewahrung, Archivierung und Löschung werden
  fachlich getrennt behandelt.

## Fachvertrag

- Person und Bewerbung sind getrennte Entitäten; eine Person kann mehrere Bewerbungen besitzen.
- Eine Bewerbung gehört genau einer Person und einer Stelle beziehungsweise Ausschreibung.
- Gewünschte Wochenstunden sind im Bewerbungsprozess ein unverbindlicher
  einzelner Circa-Wert oder ein Von-bis-Bereich. Für Bereiche gilt
  `0 < von ≤ bis ≤ 80`; gleiche Grenzen werden als Einzelwert normalisiert.
  Verbindliche Vertragsdauer, Entgeltgruppe, Stunden, Urlaub und Arbeitsort
  stammen kanonisch aus der Stelle. Das Arbeitszeitmodell wird als KAPOVAZ für
  Assistenz und Festgehalt für andere Berufsgruppen abgeleitet.
- Familienstand ist kein Bewerbungs- oder aktives Vertragsstammdatum. Bank-,
  Steuer-, Krankenkassen- und Sozialversicherungsdaten werden ausschließlich
  im Vertragsbereich verarbeitet.
- Bewerbungsstatus sind kontrollierte Zustände. Übergänge laufen ausschließlich über den zentralen `ApplicationStatusService` und werden mit Zeitpunkt sowie ausführender Nextcloud-UID protokolliert.
- Jede Assistenz-Stelle benötigt ausnahmslos eine Basisqualifikation; für alle
  anderen Berufsgruppen ist sie ausgeschlossen. Die Pflicht wird aus der
  Berufsgruppe abgeleitet und ist nicht separat schaltbar. Eine Bewerbung in
  einer BQ ist zur möglichen Einstellung
  vorgemerkt, bleibt aber bis zur Bewertung im Bewerbungsprozess. Die
  Zuordnung zu einem BQ-Durchlauf erteilt noch keinen LoBu-Zugriff. Erst eine
  anschließende ausdrückliche Einstellungsfreigabe öffnet die getrennte
  LoBu-Projektion und darf Erstbegleitungszugriff auslösen.
- AD Recruitment muss BQ-Zuordnung und Bewertung zunächst eigenständig
  abbilden können. Ein späteres BQ-Modul wird ausschließlich über einen
  kleinen optionalen Capability-/Event-Vertrag angebunden; sein Fehlen bleibt
  ein gültiger Standalone-Zustand.
- Interviewvorlagen besitzen Revisionen. Eine Interviewinstanz speichert einen unveränderlichen Snapshot der verwendeten Revision einschließlich Fragen und Antwort-Bubbles.
- Abgeschlossene Interviews werden nicht still verändert. Entwürfe verwenden eine Versionsnummer zur Erkennung konkurrierender Änderungen.
- Antwort-Bubbles unterstützen nur Freitextfragen, überschreiben vorhandenen Text nicht und speichern den resultierenden editierbaren Antworttext.
- Pflichtfragen müssen vor dem Abschluss beantwortet sein.
- Der bereitgestellte Haustarifvertrag bildet die fachliche Grundlage für
  Entgeltgruppe und Tarifstufe. Zeitabhängige Entgelttabellen oder Ansprüche
  werden nicht ohne bestätigte aktuelle Tarifversion automatisch berechnet.

## Architektur, Rechte und Datenschutz

- AD Recruitment bezieht seine Standalone-Navigation und seine
  OrgSuite-Menüposition aus dem versionierten LocalBase-Produktkatalog. Der
  wirkungslose OrgSuite-Host lädt keine fremden Assets direkt; sichtbare
  Navigation erteilt keine Fachberechtigung.
- Controller bleiben dünn. Fachregeln liegen in Services, Datenzugriff im `RecruitmentRepository`, Berechtigungen im `RecruitmentAccessService` und Browserlogik in getrennten JavaScript-Modulen.
- Rechte werden serverseitig und deny by default über Nextcloud-Adminstatus, den unveränderlichen LocalBase-Organisationssnapshot und app-eigene granulare Vertretungsfreigaben geprüft. Eine ungültige oder nur aus Defaults rekonstruierte Organisation erteilt Nicht-Admins keine Rechte; UI-Sichtbarkeit erteilt keine Rechte.
- Personenbezogene Inhalte, Interviewantworten, Dokumentnamen und E-Mail-Inhalte werden nicht in technische Logs geschrieben.
- Der erste Posteingangs-Durchstich speichert normalisierte Original-Mailtexte
  in der App-Datenbank und ausschließlich validierte PDF-Anhänge unter
  serverseitig erzeugten Hashpfaden im privaten Nextcloud-AppData. Diese
  Importablage ist keine nutzerverwaltete oder teilbare Hauptakte. Der
  geschützte PDF-Abruf prüft Scope und Dateihash. Die app-lokale PDF.js-
  Lightbox verknüpft nachgewiesene Fundstellen kontrolliert mit freigegebenen
  Bewerbungsfeldern; Herkunftsnachweise bleiben getrennt vom Original,
  strukturierte Werte werden nicht still überschrieben und der freie
  Bewerbungskommentar wird nur angehängt. Schreiben erfordert Dokument- und
  passendes Feldrecht. Reale Postfachanbindung, weitergehende
  Dokumentablage, Antwortzuordnung und öffentliche Fragebogenlinks bleiben
  Folgepakete mit eigener Architektur-, Rechte-, Datenschutz- und
  Aufbewahrungsentscheidung. Der vorhandene Statuswechsel-Versand erzeugt
  zunächst einen bearbeitbaren Entwurf und nutzt erst nach ausdrücklicher,
  scope- und CSRF-geschützter Freigabe die Nextcloud-Mailkonfiguration.
  Jede erlaubte Statuskante besitzt eine standardmäßig deaktivierte Regel;
  Vorlagen dürfen von mehreren Kanten gemeinsam verwendet werden. HTML bleibt auf den serverseitig bereinigten kleinen
  Formatwortschatz beschränkt und wird stets mit Klartextalternative versandt.
- Schreibende Routen verwenden den Nextcloud-CSRF-Schutz. Requestwerte werden validiert; SQL-Werte werden gebunden.
- Der direkte App-Root erfüllt den Nextcloud-Scrollvertrag. Alle Funktionen sind per Tastatur bedienbar, besitzen sichtbaren Fokus und verständliche Fehlerzustände.
- Der technische PHP-Namespace `OCA\Recruitment` und das bestehende
  Tabellenpräfix `rec_` bleiben bei der App-ID-Umbenennung stabil, damit
  bestehende Installationen ihre Klassen und Fachdaten ohne Tabellenkopie
  weiterverwenden.
- Der `PersonalDataProvider` registriert sich für Nextcloud-Nutzer*innen lazy
  über den öffentlichen Standalone-V1-Vertrag von
  `filzmann_data_protection` und umfasst alle internen
  UID-Bezüge in Zuständigkeiten, Status-, Interview-, BQ-, Posteingangs-,
  Berechtigungs-, Dokument- und Statusmail-Bearbeitungsnachweisen. Bewerberakten werden
  diesem Subject-Typ nicht über eine bloße E-Mail-Übereinstimmung zugeordnet;
  sie benötigen einen eigenen authentifizierten Subject-Vertrag.

## Git, DDEV und Tests

- Eigenständiges Git-Repository. Diese Datei und die lokal referenzierten Skills bilden beim direkten Start die vollständige Repository-Steuerung.
- Für Git-, Sandbox-, DDEV-/`occ`-Sicherheit, Verifikation und Learning Candidates gilt der lokal mitgeführte Skill `work-in-nextcloud-app`.
- Jede Verhaltensänderung folgt dem lokalen Skill `test-driven-change`.
- DDEV-Mount: `/var/www/html/html/custom_apps/adrecruitment`.
- Ausschließlich lokale Test- und Demokonten verwenden ihre UID zugleich als
  Passwort (`username=password`); dieser Vertrag gilt niemals für produktive
  Konten oder Zugangsdaten.
- Schnelle Tests: `php tests/run.php` und `node tests/run-js.mjs`.
- Controller-, Dependency-Injection-, Migrations- und echte Persistenzänderungen werden zusätzlich in DDEV geprüft.

## Parent-Governance-Vertrag: 1

- Die für dieses Subrepository anwendbaren Regeln des Parent-Workspaces sind
  verbindlich. Dazu gehören insbesondere app-übergreifende ADRs und
  öffentliche Verträge, Repositorygrenzen sowie Workspace-, Delivery- und
  Release-Gates.
- Diese lokale `AGENTS.md` und die lokalen Skills bleiben die vollständige,
  ohne Parent-Checkout arbeitsfähige Repository-Steuerung. Die anwendbaren
  Parent-Regeln werden dafür hier oder in den lokalen Skills mitgeführt.
- Repository-lokale Regeln dürfen Parent-Verträge konkretisieren und verschärfen,
  aber nicht abschwächen oder umgehen.
- Bei einem Widerspruch gilt bis zur Klärung die strengere Regel. Die Arbeit
  stoppt, bis die kanonische Quelle bestimmt, die Regelprojektionen
  synchronisiert und eine erforderliche Entscheidung dokumentiert ist.
- Ist der Parent-Workspace nicht verfügbar, bleibt die lokale Steuerung
  wirksam. Vor Cross-App-, Release- oder Delivery-Arbeit muss ein vermuteter
  neuerer Parent-Stand oder eine Regelungslücke zuerst gegen den Parent
  geprüft werden.
