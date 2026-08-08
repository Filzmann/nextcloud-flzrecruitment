# AD Recruitment

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
Bewerbungen können im ersten Workbench-Schnitt
wahlweise als Tabelle oder nach Status gruppierte Karten angezeigt und nach
Text, Stelle sowie Status gefiltert werden. Neue Datensätze werden über
kontextbezogene Buttons in Dialog-Overlays angelegt.

Im Bewerbungsprozess werden gewünschte Wochenstunden nur als unverbindlicher
Circa-Einzelwert oder Von-bis-Bereich geführt. Der getrennte Vertragsbereich enthält verbindliche
Wochenstunden, Beschäftigungsform, Vertragsdauer, Arbeitszeitmodell sowie
Entgeltgruppe und Tarifstufe nach dem bereitgestellten Haustarifvertrag.
KAPOVAZ ist serverseitig auf Assistenz-Stellen begrenzt; Familienstand wird
nicht aktiv verarbeitet. Karten können per Drag-and-drop oder gleichwertiger
Tastaturaktion ausschließlich in erlaubte Zielstati verschoben werden.

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
Einstellungsfreigabe. Lohn bereitet während der laufenden BQ bereits den
Vertrag vor und erhält ab der BQ-Zuordnung ausschließlich die dafür
bestimmten Stammdaten. Personalreferent*innen verwalten die Durchläufe,
Zuordnungen und zunächst nur die einfachen Ergebnisse „geeignet“, „nicht
geeignet“, „abgebrochen“ und „nicht teilgenommen“.

Die Berechtigungen stammen aus dem gemeinsamen LocalBase-Organisationsvertrag:
Personalreferent*innen besitzen Vollzugriff. Lohn sieht bei
Assistenz-Bewerbungen ab der BQ-Zuordnung, bei anderen Berufsgruppen ab der
Einstellungsfreigabe ausschließlich die für die Vertragsvorbereitung
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

Die erste Demo-Stelle ist eine BQ-pflichtige Assistenz-Stelle. Drei der vier
Demo-Bewerbungen tragen den Eingangskanal `email_import`; damit zeigt die
Oberfläche den vorgesehenen E-Mail-Regelprozess, obwohl der echte
Postfachadapter noch nicht implementiert ist. Der Befehl verwendet stabile
Demo-Kennungen und erzeugt bei Wiederholung keine Duplikate. Er darf nicht
ungeprüft in einer Produktivumgebung ausgeführt werden.

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
Klartextmails als quellmarkierte Vorschläge aus. Er ruft noch kein Postfach
ab: Ein späterer Adapter übergibt normalisierte Nachrichten an dieselbe
Importgrenze, die derzeit durch den synthetischen Befehl geprüft wird.

Architekturentscheidungen und bewusst noch nicht umgesetzte Integrationen
stehen in `docs/architecture.md`.

## Abnahme und Roadmap

Für die fachliche, visuelle, sicherheits- und datenschutzbezogene
Staging-Prüfung steht ein ausfüllbares
[manuelles Abnahmeformular](docs/manual-acceptance.md) bereit. Bewerbungs- und
Interview-Echtdaten werden darin nicht dokumentiert.

Geplante Erweiterungen stehen in der [Roadmap](ROADMAP.md).
