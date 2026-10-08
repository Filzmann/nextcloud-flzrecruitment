# AGENTS.md – Filzmann Recruitment

## Projekt

Nextcloud-App `flzrecruitment` für strukturierte Bewerbungs- und Recruitingprozesse.

Lokale App-URL:

    https://nextcloud-dev.ddev.site/apps/flzrecruitment/

Nextcloud-App-ID:

    flzrecruitment

Die priorisierte Produktplanung und offene Entscheidungen stehen in
`ROADMAP.md`; verbindliche Fach-, Sicherheits- und Architekturregeln bleiben
in dieser Datei. Die vollständige verbindliche Funktions- und
Prozessbeschreibung steht in `docs/product-process.md`; Roadmap und
Implementierung dürfen dieses Zielbild nur nach ausdrücklicher fachlicher
Entscheidung verkürzen oder verändern.

## Produktziel

Filzmann Recruitment bildet einen durchgängigen, an einem Odoo-artigen
Bewerbermanagement orientierten Prozess innerhalb der Filzmann Nextcloud Plugins ab:

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
- Filzmann Recruitment muss BQ-Zuordnung und Bewertung zunächst eigenständig
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

- Filzmann Recruitment bezieht seine Standalone-Navigation und seine
  OrgSuite-Menüposition aus dem versionierten LocalBase-Produktkatalog. Der
  wirkungslose OrgSuite-Host lädt keine fremden Assets direkt; sichtbare
  Navigation erteilt keine Fachberechtigung.
- Native Nextcloud-Administration erteilt keinen fachlichen Recruitment-Vollzugriff. Er setzt pro Administrationskonto eine aktive, app-lokale Freigabe von höchstens 24 Stunden voraus; Beginn, geplantes Ende und Widerruf bleiben historisch protokolliert. Ausschließlich Mitglieder der nativen Gruppe `Datenschutzbeauftragte` lesen die Historie und erteilen oder widerrufen Freigaben in der Recruitment-Fachoberfläche.
- Technische Systemeinstellungen bleiben native Administration. Ein fehlender Vollzugriff wird nur dem betroffenen Administrationskonto angezeigt; ein direkter Link zur Freigabesteuerung erscheint ausschließlich, wenn dasselbe Konto zugleich Mitglied von `Datenschutzbeauftragte` ist. Änderungen an Freigabehistorie oder Recruitment-Rechten werden gleichzeitig im PersonalDataProvider und PermissionProvider nachgeführt.
- Controller bleiben dünn. Fachregeln liegen in Services, Datenzugriff im `RecruitmentRepository`, Berechtigungen im `RecruitmentAccessService` und Browserlogik in getrennten JavaScript-Modulen.
- Rechte werden serverseitig und deny by default über eine gegebenenfalls
  aktive app-lokale Adminfreigabe, den unveränderlichen LocalBase-
  Organisationssnapshot und app-eigene granulare Vertretungsfreigaben
  geprüft. Der Organisationssnapshot wird ausschließlich lazy über den
  öffentlichen LocalBase-Vertrag `OCA\\LocalBase\\PublicApi\\V1` konsumiert;
  Recruitment greift weder auf interne LocalBase-Services noch auf deren
  AppConfig zu. Der native Adminstatus allein genügt nicht. Ein fehlender,
  deaktivierter, inkompatibler, ungültiger oder nicht verfügbarer Provider
  erteilt keine organisationsabgeleiteten Rechte. Davon unabhängige aktive
  app-lokale Adminfreigaben sowie globale oder einzelaktenbezogene
  Vertretungsfreigaben bleiben wirksam; bereichsgebundene Freigaben benötigen
  einen gültigen Organisationssnapshot. UI-Sichtbarkeit erteilt keine Rechte.
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
- Der technische PHP-Namespace `OCA\FlzRecruitment` und das bestehende
  Tabellenpräfix `flz_recruitment_` bleiben bei der App-ID-Umbenennung stabil, damit
  bestehende Installationen ihre Klassen und Fachdaten ohne Tabellenkopie
  weiterverwenden.
- Der `PersonalDataProvider` registriert sich für Nextcloud-Nutzer*innen lazy
  über den öffentlichen Standalone-V1-Vertrag von
  `flz_data_protection` und umfasst alle internen
  UID-Bezüge in Zuständigkeiten, Status-, Interview-, BQ-, Posteingangs-,
  Berechtigungs-, Dokument- und Statusmail-Bearbeitungsnachweisen. Bewerberakten werden
  diesem Subject-Typ nicht über eine bloße E-Mail-Übereinstimmung zugeordnet;
  sie benötigen einen eigenen authentifizierten Subject-Vertrag.
- Filzmann Recruitment registriert zusätzlich einen `ProcessingMetadataProvider`
  lazy über den öffentlichen V1-Vertrag des Datenschutz-Centers. Seine
  einzige fachliche Policyquelle ist `resources/privacy-processing.json`; sie
  enthält keine personenbezogenen Laufzeitdaten. Der beschlossene manuelle
  Bewerberrechteprozess verwendet nach externer Identitätsprüfung nur eine
  stabile Bewerber-ID und eine durch Datenschutzbeauftragte geprüfte
  app-eigene Vorschau; seine Provider- und Laufzeitumsetzung bleibt offen und
  darf nicht durch eine Suche nach Name oder E-Mail-Adresse ersetzt werden.

## Git, DDEV und Tests

- Eigenständiges Git-Repository. Diese Datei und die lokal referenzierten Skills bilden beim direkten Start die vollständige Repository-Steuerung.
- Für Git-, Sandbox-, DDEV-/`occ`-Sicherheit, Verifikation und Learning Candidates gilt der lokal mitgeführte Skill `work-in-nextcloud-app`.
- Jede Verhaltensänderung folgt dem lokalen Skill `test-driven-change`.
- DDEV-Mount: `/var/www/html/html/custom_apps/flzrecruitment`.
- Ausschließlich lokale Test- und Demokonten verwenden ihre UID zugleich als
  Passwort (`username=password`); dieser Vertrag gilt niemals für produktive
  Konten oder Zugangsdaten.
- Schnelle Tests: `php tests/run.php` und `node tests/run-js.mjs`.
- Controller-, Dependency-Injection-, Migrations- und echte Persistenzänderungen werden zusätzlich in DDEV geprüft.

## Dokumentenverantwortung

- `README.md` beschreibt ausschließlich den aktuellen nutzbaren Stand,
  Installation, Betrieb, Tests und den Dokumentationsindex.
- `ROADMAP.md` enthält ausschließlich offene, zurückgestellte oder
  freigabepflichtige Arbeit und Entscheidungen.
- `CHANGELOG.md` dokumentiert erledigte Änderungen releasebezogen; erledigte
  Checklisten verbleiben nicht in der Roadmap.
- `docs/architecture.md` ist die ausführliche Quelle für geltende fachliche
  und technische Architekturverträge.
- `docs/manual-acceptance.md` enthält wiederholbare manuelle Prüfungen und
  keine Produktplanung.
- `AGENTS.md` enthält ausschließlich verbindliche Arbeits-, Sicherheits-,
  Architektur- und Prüfregeln. Zusätzliche Dokumente werden in `README.md`
  mit eindeutiger Zuständigkeit eingeordnet.

## Parent-Governance-Vertrag: 2

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

### Entwicklungsphase und Kompatibilitätsbedarf

Entscheidung vom 5. September 2026: Das Gesamtprojekt befindet sich vollständig
in der Entwicklung. Es gibt kein PROD, keinen produktiven Datenbestand und
keinen bereits betriebenen Bestand mit zu erhaltendem Upgradepfad. STAGING
ist eine wegwerfbare Entwicklungs- und Integrationsumgebung und darf im
konkret beauftragten Reinstall vollständig neu aufgebaut werden. Wenige
externe Testnutzer ändern diese Einordnung nicht.

Vor einer Datenmigration, Legacy-Unterstützung, Compatibility Layer,
Deprecated API, Dual-Read/Dual-Write, einem Altschema-Fallback, Übergangsformat
oder der Unterstützung historischer Entwicklungsstände wird geprüft:

1. Wurde der betroffene Zustand jemals produktiv eingesetzt?
2. Benötigen reale Daten oder Nutzer seine Erhaltung?
3. Gibt es einen anderen konkreten technischen Erhaltungsgrund, insbesondere
   einen geltenden Plattform- oder externen API-Vertrag?

Sind alle relevanten Antworten nein, ist die saubere Breaking-Change-/
Reinstall-Lösung der Standard. Frühere rein interne Entwicklungsstände
begründen weder Abwärtskompatibilität noch eine Deprecationfrist.
Entwicklungsschemata dürfen durch ein kanonisches Installationsschema ersetzt,
alte interne APIs und Konfigurationsformate samt ausschließlich dafür
benötigten Adaptern und Tests entfernt werden. Architekturqualität und der
saubere Zielzustand haben Vorrang. Nextclouds nötige Installationsmigrationen
bleiben erhalten; ein Verzeichnisname `Migration` beweist keine Altlast.

Breaking Changes werden im selben Änderungskontext vollständig durchgezogen:
betroffene Provider, Consumer, standardisierte APIs, Vertragsversionen,
Metadaten, Tests und Dokumentation müssen zusammenpassen. Unterstützte
Nextcloud-/openDesk-Plattformverträge, externe Standards, Autorisierung und
Datenschutz gelten unverändert. Fehlende oder inkompatible optionale Provider
bleiben kontrolliert sichtbar. Ein Reinstall erlaubt keine privaten
Fremdtabellenzugriffe oder parallel erfundenen Plattformmechanismen.

Vor destruktiver Arbeit werden die tatsächlich benötigten externen
Testidentitäten, Gruppen, Rollen und nicht reproduzierbaren Testdaten gezielt
gesichert oder über bestehende native Setup-Strukturen reproduzierbar gemacht.
Echte Personen- und Zugangsdaten bleiben außerhalb von Git. Diese begrenzte
Sicherung begründet keine allgemeine Legacy-Unterstützung. Ein Reinstall
bleibt ein normaler unterstützter Entwicklungsweg; der vorhandene
Compatibility-Workflow besitzt den Fresh-Install-Nachweis, dessen aktueller
Belegstatus in `docs/workspace.md` beschrieben ist.

Diese Phase endet ausschließlich durch einen ausdrücklich dokumentierten,
von Simon freigegebenen **Production-Readiness-/Production-Freeze-Entscheid**.
Ein Release Candidate, eine Versionsnummer, ein Staging-Deployment oder ein
externer Testzugang lösen den Wechsel nicht aus. Der Entscheid wird in dieser
kanonischen Lifecycle-Quelle mit Datum, Geltungsbereich und betroffenem
Versions-/Datenstand festgehalten und in die lokale Steuerung projiziert.
Dann werden Upgradepfade, Datenbankmigrationen, Persistenz, Backup/Restore,
Rollback, Release-/API-Kompatibilitätszusagen, Deployment-/Freigabeprozess und
PROD→STAGING/COPY-Strategie neu bewertet. Eine vollständige PROD-Governance
wird jetzt nicht vorweggenommen.

Diese Regel entscheidet den Kompatibilitätsbedarf, erweitert aber keinen
Repository-Schreibauftrag und ersetzt keine Freigabe für eine konkrete
destruktive Aktion. Lokale Regelprojektionen folgen dem bestehenden
`docs/parent-governance-contract.md`; ein unsynchronisierter Einzel-Checkout
darf keinen abweichenden Phasenstand stillschweigend annehmen.

### Prüfaufwand

- Vor einem neuen Test, Scan, Linter, Architektur- oder Systemcheck wird
  geprüft, welcher bestehende Check dieselbe Eigenschaft bereits nachweist.
  Diesen erweitern oder sein nachweislich passendes Ergebnis wiederverwenden;
  ein zusätzlicher Check braucht eine benannte zusätzliche Fehlerklasse oder
  Vertrauensgrenze. Gleicher Input, gleiche Prüfung, gleiche Fehlerklasse und
  gleiche Phase begründen keinen zweiten Lauf. Gestaffelte Unit-, Contract-
  und Runtime-Nachweise bleiben erhalten. Die dokumentierten lokalen
  Prüfeinstiege bestimmen Umfang und Ergebnisgültigkeit.
