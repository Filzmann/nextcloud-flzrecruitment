# AD Recruitment

## Staging-Kompatibilität

- Nextcloud 33 bis 34
- PHP 8.3 oder neuer innerhalb des von Nextcloud 33 bis 34 unterstützten Bereichs
- Laufzeitbasis: `localbase`; `orgsuite` ist ab zwei AD-Fachprodukten optional aktiv
- App-ID und Installationsordner: `adrecruitment`

`adrecruitment` ist eine eigenständige Nextcloud-App für einen strukturierten,
nachvollziehbaren Bewerbungsprozess. Der vorhandene vertikale Ausschnitt
umfasst Stellen, Personen, Bewerbungen, versionierte Interviewvorlagen und
-instanzen, kontrollierte Bewerbungsstatus, Vertragsstammdaten,
bereichsgebundene Erstbegleitungen und einen ersten manuellen
Basisqualifikationsprozess. Hinzu kommt ein erster app-privater
Bewerbungsposteingang: bereits empfangene Website- oder freie Bewerbungs-Mails
werden duplikatfrei mit validierten PDF-Anhängen importiert, manuell einer
Bewerbung zugeordnet und anschließend im berechtigten Bewerbungsscope gelesen.
PDF-Unterlagen öffnen dort in einer großen Lightbox. Textstellen oder
grafische Bereiche lassen sich mit Vorerfahrung, Deutschniveau, Geburtsdatum,
Geburtsort oder dem freien Kommentar der Bewerbung verbinden. Herkunft und
Zielwert bleiben getrennt vom unveränderten Original nachvollziehbar.
Bewerbungen werden im ersten Workbench-Schnitt standardmäßig als nach Status
gruppierte Karten angezeigt, können alternativ als Tabelle geöffnet und nach
Text, Stelle sowie Status gefiltert werden. Neue Datensätze werden über
kontextbezogene Buttons in Dialog-Overlays angelegt.
Die Stellenanlage zeigt nach Wahl der Berufsgruppe nur fachlich passende
AD-Organisationsgruppen. Verantwortliche Personen werden per Suche
ausschließlich unter den Mitgliedern der ausgewählten Gruppen angeboten.
Für Assistenz-Stellen gilt die Basisqualifikation automatisch und immer; für
andere Berufsgruppen wird sie nicht angeboten.

Die Hauptnavigation hält die tägliche Recruiting-Arbeit bewusst knapp.
Interviewfragen und -vorlagen, Mailvorlagen, Bewerberpool-Grundkonfiguration
sowie Berechtigungen liegen
capability-abhängig gemeinsam unter `Einstellungen` und sind dort über eine
tastaturbedienbare Unter-Navigation erreichbar.
Systemweite Grundkonfigurationen liegen getrennt unter
`Nextcloud-Einstellungen → Verwaltung → AD Recruitment`: Mail-Testmodus,
Lebenslaufextraktion sowie die strukturelle Erstbegleitungsgruppe. Dieser
Abschnitt ist ausschließlich für Nextcloud-Admins sichtbar.

Personalreferent*innen verwalten versionierte Mailvorlagen, Textblöcke und
ein- oder ausschaltbare Regeln je Statusübergang. Ein Statuswechsel erzeugt
gegebenenfalls nur einen bearbeitbaren Entwurf. Betreff, Text und Zieladresse
werden vor der ausdrücklichen Versandfreigabe nochmals geprüft; ursprüngliche,
freigegebene und im zentralen Testmodus tatsächlich verwendete Adresse bleiben
unterscheidbar. Versand ist sofort, zu einem freien Zukunftszeitpunkt oder am
kommenden Montag möglich und läuft über die Nextcloud-Mailkonfiguration.
Alle erlaubten Statusübergänge besitzen eine vorbereitete, zunächst
deaktivierte Standardregel. Eine Mailvorlage kann mehreren Übergängen
zugeordnet werden; insbesondere verwenden alle Rückzüge dieselbe gemeinsame
Vorlage. Die Verwaltung ist nach Ausgangsstatus gruppiert; Aktivierung,
Versandplanung und Vorlagenwahl liegen direkt an der jeweiligen Statuskante.
Ein kleiner Rich-Text-Editor unterstützt
Absätze, Hervorhebungen, Listen und sichere Links. Der Server bereinigt HTML
und erzeugt beim Versand zusätzlich eine Klartextalternative.

Im Bewerbungsprozess werden gewünschte Wochenstunden nur als unverbindlicher
Circa-Einzelwert oder Von-bis-Bereich geführt. Die Stelle ist die kanonische
Quelle für Vertragsdauer, Entgeltgruppe, ausgeschriebene und tarifliche
Vollzeitstunden, Urlaub sowie Arbeitsort (standardmäßig Berlin). Das
Arbeitszeitmodell wird daraus abgeleitet: Assistenz ist KAPOVAZ, alle anderen
Stellen werden als Festgehalt geführt. Ein frei erfasstes Gehalt und eine
Währung gehören nicht zur Bewerbungsakte. Karten können per Drag-and-drop
oder gleichwertiger Tastaturaktion ausschließlich in erlaubte Zielstati
verschoben werden. `Zurückgezogen` ist keine Kartenspalte, sondern eine
bestätigungspflichtige rote Abschlussaktion.

Das verbindliche Ziel ist ein durchgängiges, an einem Odoo-artigen
Bewerbermanagement orientiertes Verfahren: Postfacheingang und Zuordnung,
Karten- und Tabellenbearbeitung, konfigurierbare Prozessstati,
Stammdatenvorschläge aus Bewerbernachrichten und Unterlagen sowie temporär
aktivierbare Kurzfragebögen und Interviewaktivitäten. Die vollständige
[Funktions- und Prozessbeschreibung](docs/product-process.md) unterscheidet
den vorhandenen ersten Durchstich ausdrücklich vom geplanten Produktumfang.

Für Assistenz-Bewerber*innen gehört außerdem die Basisqualifikation zum
vorhandenen Prozess: Die ungefähr zehntägigen, etwa zwölfmal jährlich geplanten
Lehrgänge liegen vor der Einstellung und enden mit einer Bewertung. Eine
Zuordnung zu `BQ MM/YY` ist deshalb eine Vormerkung, aber noch keine
Einstellungsfreigabe. Die LoBu-Vertragserstellung beginnt erst mit
`approved_for_hire`; erst dann erhält LoBu die dafür bestimmte Projektion und
kann Bank-, Krankenkassen-, Steuer- und Sozialversicherungsdaten bearbeiten.
Personalreferent*innen verwalten die Durchläufe,
Zuordnungen und zunächst nur die einfachen Ergebnisse „geeignet“, „nicht
geeignet“, „abgebrochen“ und „nicht teilgenommen“.
Diese lokale BQ-Verwaltung ist eine Übergangslösung, bis die eigenständige
BQ-Planer-App über einen kleinen versionierten Vertrag angebunden ist.

Die Berechtigungen stammen aus dem gemeinsamen LocalBase-Organisationsvertrag:
Personalreferent*innen besitzen operativen Vollzugriff, sehen und bearbeiten
vor der Einstellungsfreigabe aber keine LoBu-only Abrechnungsdaten. LoBu sieht
ab der Einstellungsfreigabe ausschließlich die für die Vertragserstellung
bestimmten Stammdaten. Vertretungen werden nach Fähigkeit sowie globalem,
Bereichs- oder Einzelbewerbungsscope konfiguriert. Erstbegleitungen benötigen
EB-Rolle, Erstbegleitungsgruppe und denselben Bürobereich wie die freigegebene
Bewerbung.

Beim Upgrade wird die bisherige kombinierte Bestandsgruppe sicher der Rolle
Finanzen zugeordnet. Lohn-Mitarbeitende müssen durch die Administration
bewusst in die neue LocalBase-Lohn-Gruppe übernommen werden; Mitgliedschaften
werden wegen der sensiblen Vertragsstammdaten nicht automatisch kopiert.

Die App bleibt eigenständig installierbar. Ab zwei aktivierten AD-Fachprodukten
ersetzt OrgSuite den einzelnen Nextcloud-Einstieg durch das gemeinsame AD-Menü.
AD Recruitment ist Bestandteil des vollständigen AD-Suite-Pakets und wird
zusätzlich als eigenes Produktpaket mit LocalBase und OrgSuite gebaut.

## Lokale Entwicklung

In einer Nextcloud-Installation liegt der Einstieg unter
`/index.php/apps/adrecruitment/`. Lokale Mount- und DDEV-Details werden nur im
internen Parent-Workspace gepflegt und sind kein Produktionsvertrag.

Schnelle Prüfungen:

```bash
php tests/run.php
node tests/run-js.mjs
```

Ein ausschließlich synthetischer, wiederholbarer Demo-Datensatz kann in einer
bewusst gewählten lokalen Demo- oder Abnahmeumgebung installiert werden:

```bash
php occ adrecruitment:demo:seed
```

Die erste Demo-Stelle ist eine BQ-pflichtige Assistenz-Stelle. Der Seed legt
außerdem zwei neutralisierte Interviewvorlagen mit 10 Fragen für das
Telefoninterview und 13 Fragen für das Vorstellungsgespräch an. Jede dieser
Freitextfragen erhält drei aktive, inhaltlich passende Antwort-Bubbles für eine schnelle,
weiterhin editierbare Gesprächsdokumentation. Drei der vier
Demo-Bewerbungen tragen den Eingangskanal `email_import`; damit zeigt die
Oberfläche den vorgesehenen E-Mail-Regelprozess, obwohl der echte
Postfachadapter noch nicht implementiert ist. Der Befehl verwendet stabile
Demo-Kennungen und erzeugt bei Wiederholung keine Duplikate. Er darf nicht
ungeprüft in einer Produktivumgebung ausgeführt werden.

Alle vier Demo-Bewerbungen besitzen außerdem die Mail-Vorbelegung des
Vertragsbereichs: Anrede, gegebenenfalls Titel, private E-Mail,
Telefonnummer, geplanter Eintritt und Ort. Eine Wiederholung ergänzt nur noch
leere Felder und überschreibt keine bereits bearbeiteten Demo-Werte.

Der Bewerberpool ist als datenschutzgerechtes Rückstellungspaket integriert
und standardmäßig deaktiviert. Er speichert nur ein reduziertes Suchprofil,
verlangt einen referenzierten Einwilligungsnachweis und beendet Vorschläge bei
Widerruf oder Fristablauf automatisch. Vorschläge lösen weder Kontakt noch
Bewerbungsentscheidung aus.

Für den Posteingangs-Durchstich stehen zwei neutrale Originalmails mit je
einem synthetischen PDF bereit:

```bash
php occ adrecruitment:inbox:seed
```

Der Befehl verwendet keine Zugangsdaten und ist idempotent. Originaltexte und
PDFs werden app-privat gespeichert; Dateipfade enthalten ausschließlich
Nachrichten-ID und Inhaltsfingerprint. Die Inline-Vorschau prüft den
gespeicherten Hash vor jeder Ausgabe und folgt dem Posteingangs- oder
Bewerbungsscope. PDF.js 6.2.108 wird dafür vollständig app-lokal geladen.
Markierungen und Feldverknüpfungen bleiben außerhalb des Original-PDFs; das
Ergänzen erfordert `manage_documents` und das jeweilige Feldrecht. Bestehende
strukturierte Werte werden nicht still überschrieben, und der freie
Bewerbungskommentar wird ausschließlich ergänzt. Ein realer
Postfachadapter ist noch nicht Bestandteil dieses Schnitts.

Der Posteingang wertet zeilenweise beschriftete Kontaktfelder des bestätigten
Website-Mailformulars sowie eine eindeutige E-Mail-Adresse in freien
Klartextmails als quellmarkierte Vorschläge aus. Textbasierte PDF-Lebensläufe
werden beim Import vollständig lokal über ein vorhandenes `pdftotext` oder
Ghostscript ausgelesen. Alternativ kann ein Empfangsadapter bereits
normalisierten Text übergeben. Name, Telefon, Wunschstunden, Verfügbarkeit,
Erfahrung, Deutschniveau und Wohnort werden konservativ nach bekannten
Stichworten mit ihrer Quelle vorgeschlagen. Bild-PDFs erhalten noch keine OCR.
Im Admin-Einstellungsbereich ist die lokale Stichworterkennung samt
verwendetem Laufzeitwerkzeug sichtbar; ein künftiges lokales Server-Modell
wird als noch nicht angebundene, deaktivierte Auswahl gezeigt. Es findet keine
Übertragung an KI-Dienste statt. Bei der bestätigten Zuordnung werden
Anrede, Titel, private E-Mail, Telefon, Eintrittsdatum und Ort ausschließlich
in bislang leere Vertragsfelder übernommen; bestehende Werte bleiben erhalten.

Poppler beziehungsweise Ghostscript sind optionale Betriebspakete und werden
weder von der App noch vom Produktinstaller installiert. Die App erkennt
`pdftotext` bevorzugt und `gs` ersatzweise ausschließlich über den `PATH` des
PHP-Laufzeitkontexts. Ohne dort ausführbares Werkzeug zeigt die Administration
„Nicht verfügbar“; Mail und Original-PDF werden weiterhin importiert, nur
PDF-basierte Feldvorschläge entfallen.
Der Vertragsbereich zeigt diese Daten zunächst kompakt und wechselt erst nach
einem Klick in den Bearbeitungsmodus.
Sichtbare E-Mail-Adressen öffnen über `mailto:` in einem neuen Browser-Tab das
registrierte Mailprogramm; der neue Kontext erhält keinen Zugriff auf das
Recruitment-Fenster.
Telefonnummern werden für `tel:` normalisiert und können dadurch unter anderem
an ein entsprechend eingerichtetes AGFEO Dashboard übergeben werden.

Fehlt ein beschrifteter Name, prüft der Extraktor in dieser Reihenfolge eine
explizite Selbstvorstellung im Mailtext, die Zeile nach einer üblichen
Grußformel, den Absender-Anzeigenamen und zuletzt klar getrennte Bestandteile
einer persönlichen Adresse wie `vorname.nachname@…`. Rollenadressen,
Ziffernfolgen und mehrdeutige Einzelwörter erzeugen keinen Namensvorschlag.

Architekturentscheidungen und bewusst noch nicht umgesetzte Integrationen
stehen in `docs/architecture.md`.

## Zeitlich begrenzter Admin-Vollzugriff

Ein Nextcloud-Administrationskonto erhält nicht automatisch Zugriff auf Bewerbungsakten. Der fachliche Vollzugriff wird im Adminbereich von AD Recruitment pro Administrationskonto für 1, 4, 8 oder höchstens 24 Stunden aktiviert und kann vorzeitig widerrufen werden. Beginn, geplantes Ende, Freigabe und Widerruf werden app-lokal protokolliert und in Datenschutz- sowie Berechtigungsprovider einbezogen. Technische Systemeinstellungen bleiben davon getrennt.

## Datenschutz

Der app-eigene Processing-Katalog beschreibt Bewerbungsakten, Interviews und
BQ, Posteingang und Dokumente, Einstellungsfreigaben, Statusmails, den
Bewerberpool sowie temporäre Adminfreigaben über den öffentlichen V1-Vertrag
des Datenschutz-Centers. Er enthält ausschließlich Policy-Metadaten und keine
personenbezogenen Laufzeitdatensätze. Noch offene Rechtsgrundlagen sowie
Retention-, Backup-, Restore-, Mailanbieter- und
Betroffenenrechtsentscheidungen bleiben als `PRIVACY-DECISION-REQUIRED`
sichtbar; beschlossene Teilregeln ersetzen keine noch fehlende technische
Lösch- oder Restore-Ausführung. Der bestehende PersonalDataProvider
liefert nur interne Nextcloud-UID-Bezüge; eine Bewerber-Selbstauskunft bleibt
bis zu einem sicheren authentifizierten Subject-Vertrag ausdrücklich offen.

## Abnahme und Roadmap

Für die fachliche, visuelle, sicherheits- und datenschutzbezogene
Staging-Prüfung steht ein ausfüllbares
[manuelles Abnahmeformular](docs/manual-acceptance.md) bereit. Bewerbungs- und
Interview-Echtdaten werden darin nicht dokumentiert.

Geplante Erweiterungen stehen in der [Roadmap](ROADMAP.md).

## Dokumentation

- [Produktprozess](docs/product-process.md)
- [Architektur](docs/architecture.md)
- [Manuelle Abnahme](docs/manual-acceptance.md)
- [Drittanbieterhinweise](THIRD_PARTY.md)
- [Roadmap](ROADMAP.md)
- [Changelog](CHANGELOG.md)
- [Arbeitsregeln](AGENTS.md)
