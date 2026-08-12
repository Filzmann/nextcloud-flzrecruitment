# Fachliche Funktions- und Prozessbeschreibung

## Zielbild

AD Recruitment bildet den vollständigen Bewerbungsprozess innerhalb der
AD-Suite ab. Das fachliche Vorbild ist die arbeitsorientierte Bedienung eines
Bewerbermanagements wie in Odoo: Neue Vorgänge landen in einem gemeinsamen
Eingang, werden einer Person und einer Stelle zugeordnet und anschließend als
Karten oder Tabelle durch einen konfigurierbaren Prozess geführt.

Die Ähnlichkeit zu Odoo beschreibt den Prozess und die Bedienlogik. AD
Recruitment bleibt eine eigenständige Nextcloud-App mit eigener Datenhaltung,
eigenen Berechtigungen und ohne technische Abhängigkeit von Odoo.

## Zentrale Fachobjekte

- Ein **Postfach** ist ein administrativ konfigurierter Eingang für
  Bewerberkommunikation. Mehrere Postfächer und mehrere Stellen je Postfach
  sind zulässig.
- Eine **Nachricht** ist ein unveränderlich übernommener Ein- oder Ausgang mit
  Absendern, Empfängern, Betreff, Zeit, Text, Anhängen und technischer
  Herkunft. Wiederholter Abruf darf keine Duplikate erzeugen.
- Eine **Person** hält personenbezogene Stammdaten und kann mehrere
  Bewerbungen besitzen.
- Eine **Bewerbung** verbindet genau eine Person mit einer Stelle. Status,
  Zuständigkeit, Kommunikation, Unterlagen, Termine, Interviews,
  Fragebögen, Notizen und Verlauf gehören zur Bewerbung.
- Eine **Stelle** beziehungsweise Ausschreibung bündelt Bezeichnung,
  Zuständigkeiten, Bürobereich, Eingangskanäle und optional einen eigenen
  Prozess.
- Ein **Prozessstatus** ist eine administrativ konfigurierte Phase mit
  stabiler technischer ID, sichtbarem Namen, Reihenfolge, zulässigen
  Übergängen und fachlicher Kategorie.
- Eine **Aktivität** ist eine zeitlich begrenzte Aufgabe innerhalb einer
  Bewerbung, zum Beispiel Kurzfragebogen, Telefoninterview oder
  Vorstellungsgespräch.
- Eine **Basisqualifikation (BQ)** ist ein ungefähr zehntägiger Lehrgang für
  Assistenz-Bewerber*innen, in dem die erforderlichen Grundfähigkeiten
  vermittelt und anschließend bewertet werden. Pro Jahr werden ungefähr
  zwölf BQ-Durchläufe geplant. Andere Berufsgruppen durchlaufen diesen
  Prozessschritt nicht.

Person und Bewerbung bleiben getrennt. Eine erneute Bewerbung derselben
Person erzeugt keine zweite Person, sondern eine weitere Bewerbung mit
eigenem Verlauf und eigenen Berechtigungen.

## Durchgängiger Sollprozess

### 1. Bewerbungseingang

1. Die Administration richtet ein oder mehrere Bewerbungspostfächer ein.
2. Eingehende Nachrichten werden regelmäßig abgerufen und zunächst in einem
   unbearbeiteten Eingang angezeigt. Der technische Empfang erfolgt über
   einen dafür freigegebenen Empfangsadapter, beispielsweise IMAP oder eine
   Provider-API; SMTP ist für ausgehenden Versand vorgesehen.
3. Jede Nachricht erhält einen nachvollziehbaren Importzustand:
   `neu`, `zugeordnet`, `unklar`, `fehlerhaft` oder `ignoriert`.
4. Technische Nachrichtenkennungen und Inhaltsfingerprints verhindern eine
   doppelte Übernahme. Fehlerhafte oder nicht sicher lesbare Nachrichten
   bleiben sichtbar in einer Quarantäne und gehen nicht verloren.
5. Die Originalnachricht und ihre Anhänge bleiben nach der Übernahme
   unverändert nachvollziehbar. Eine Löschung richtet sich nach den noch
   festzulegenden Aufbewahrungsregeln.
6. Der primäre Website-Eingang ist eine per E-Mail zugestellte Bewerbung. Das
   derzeitige Formular liefert Name, E-Mail-Adresse, Telefonnummer,
   Berufsrichtung (`Assistenz`, `Pflegefachkraft`, `Sozialarbeiter:in` oder
   `Verwaltung`), einen optionalen Nachrichtentext und bis zu fünf PDFs als
   echte Mailanhänge. Die Textfelder werden beschriftet zeilenweise im
   Mailbody übertragen.
7. Daneben bleiben frei formulierte Bewerbungs-E-Mails zulässig. Für sie ist
   lediglich eine gültige Kontakt-E-Mail-Adresse zwingend; Name, Telefon,
   konkrete Stelle und weitere Angaben können fehlen und werden dann später
   ergänzt.

### 2. Manuelle Zuordnung

1. Personalreferent*innen ordnen eine neue Nachricht zunächst manuell einer
   bestehenden Person und Bewerbung oder einer neu anzulegenden Person,
   Bewerbung und Stelle zu.
2. Die Zuordnung kann korrigiert werden. Jede Änderung wird protokolliert;
   Nachricht oder Anhänge werden dabei nicht dupliziert.
3. Ist keine eindeutige Stelle erkennbar, bleibt die Nachricht im Eingang.
   Sie darf nicht still einer Stelle zugeordnet werden.
4. Automatische Zuordnungsregeln anhand von Empfängeradresse, Stellenkennung,
   Betreff oder anderen eindeutigen Merkmalen sind eine spätere Erweiterung.
   Ein unsicherer Treffer bleibt ein Vorschlag und erfordert Bestätigung.
5. Die im Websiteformular gewählte Berufsrichtung ist nur ein
   Zuordnungsvorschlag. Sie darf insbesondere bei mehreren passenden
   Ausschreibungen keine konkrete Stelle still festlegen.

### 3. Anlage und Ergänzung der Bewerbungsakte

Nach der Zuordnung entsteht beziehungsweise ergänzt sich die Bewerbungsakte.
Die Detailansicht umfasst mindestens:

- Person- und Kontaktdaten,
- gewünschte Wochenstunden als unverbindlicher Circa-Einzelwert oder
  Von-bis-Bereich der Bewerber*innen,
- Stelle, Quelle, Eingangsdatum, Zuständigkeit und Bürobereich,
- aktuellen Status und vollständigen Statusverlauf,
- ein- und ausgehende Kommunikation,
- Bewerbungsunterlagen und weitere Dokumente,
- interne Anmerkungen der Personalreferent*innen,
- Kurzfragebögen, Telefoninterviews und Vorstellungsgespräche,
- Entscheidungen, Absage- oder Rückzugsgrund,
- bei BQ-Zuordnung beziehungsweise Einstellungsfreigabe die getrennten
  Vertragsstammdaten.

Erkennbare Stammdaten aus Nachrichtentexten und Anhängen werden als
Feldvorschläge dargestellt. Jeder Vorschlag zeigt seine Quelle und wird vor
der Übernahme durch eine berechtigte Person bestätigt oder korrigiert. Die
Extraktion überschreibt niemals still vorhandene Daten und schreibt keine
unsicheren Werte automatisch in besonders sensible Felder wie Bank,
Krankenkasse, Steuer- oder Sozialversicherungsdaten.

Die gewünschte Wochenarbeitszeit gehört zum Bewerbungswunsch und ist keine
Vertragszusage. Sie darf als Einzelwert oder Bereich angegeben werden; für
einen Bereich gilt `0 < von ≤ bis ≤ 80`. Verbindliche Wochenstunden werden erst im Vertragsbereich
erfasst. Für Assistenz kann dort KAPOVAZ vereinbart werden; die Anwendung
behandelt Vollzeit für Assistenz nicht als Standard.

Beschriftete Formularfelder gelten dabei als nachvollziehbare
Mailbody-Vorschläge. In freien Nachrichtentexten darf genau eine eindeutig
erkennbare E-Mail-Adresse vorgeschlagen werden. Mehrere unterschiedliche
Adressen bleiben mehrdeutig; die Anwendung wählt keine davon still aus.

### 4. Steuerung in Karten- und Tabellenansicht

Alle Bewerbungen werden wahlweise in zwei gleichwertigen Sichten bearbeitet:

- Die **Kartenansicht** gruppiert Bewerbungen nach Prozessstatus. Ein
  Statuswechsel kann per Tastatur und, ergänzend, durch Verschieben einer
  Karte ausgelöst werden.
- Die **Tabellenansicht** zeigt dieselben Bewerbungen und ermöglicht den
  Statuswechsel über eine explizite Aktion. Spalten, Sortierung und Filter
  unterstützen insbesondere Stelle, Status, Zuständigkeit, Bereich,
  Eingang, letzte Aktivität und nächste Aufgabe.

Beide Sichten verwenden dieselben serverseitigen Filter, Berechtigungen und
Übergangsregeln. Ein Verschieben in der Oberfläche umgeht keine Fachregel.
Änderungen konkurrierender Bearbeiter*innen dürfen sich nicht still
überschreiben.

Eine Karte darf nur in einen vom Server für genau diese Bewerbung gelieferten
Zielstatus verschoben werden. Manipulierte Karten- oder Spaltenkennungen
erteilen keine Rechte. Für die Einstellungsfreigabe ist auch im
Kartenarbeitsplatz ein gültiger Bürobereich erforderlich.

### Vertragsbereich und Tarifgrundlage

Der Vertragsbereich ist von der Bewerbungsakte fachlich getrennt. Bankdaten,
Steuer-ID, Krankenkasse, Sozialversicherungsnummer und verbindliche
Vertragsbedingungen werden ausschließlich dort verarbeitet. Familienstand
gehört weder zum Bewerbungsprozess noch zur aktiven Vertragsdatenerfassung.

Die Beschäftigungsform bietet zunächst `geringfügig`,
`sozialversicherungspflichtig`, `studentisch` und `sonstige`. Vertragsdauer
(`unbefristet` oder `befristet mit Sachgrund`) und Arbeitszeitmodell (`feste
Arbeitszeit` oder für Assistenz `KAPOVAZ`) bleiben getrennte Merkmale.

Fachliche Tarifgrundlage ist der bereitgestellte „Haustarifvertrag inkl.
Änderungen zum 1. Oktober 2023“ mit den enthaltenen Änderungen beziehungsweise
Tabellen bis 2024/2025. Entgeltgruppe und Tarifstufe werden getrennt erfasst.
Da die bereitgestellte Fassung eine mögliche spätere Kündigung zulässt, werden
zeitabhängige Tabellenwerte, Urlaub oder weitere Ansprüche nicht ohne
bestätigte aktuelle Tarifversion automatisch berechnet.

### 5. Konfigurierbare Bewerbungsstati

Die Administration kann sichtbare Statusnamen, Reihenfolge, aktive Stati und
zulässige Übergänge konfigurieren. Typische Stati sind beispielsweise:

- `Eingang`,
- `Vorprüfung`,
- `Kurzfragebogen`,
- `Telefoninterview`,
- `Vorstellungsgespräch`,
- `Entscheidung`,
- `BQ MM/YY`,
- `Abgelehnt`,
- `Zur Einstellung freigegeben`,
- `Eingestellt` und
- `Archiviert`.

`BQ MM/YY` bezeichnet die Zuordnung zu einem konkreten Durchlauf der
Basisqualifikation. Die Monats-/Jahresangabe ist dessen sichtbare
Kurzbezeichnung und darf nicht als fest codierter Statusschlüssel enden. Die
Bewerbung verweist auf einen stabil identifizierbaren BQ-Durchlauf, damit
gleichnamige oder verschobene Lehrgänge nicht verwechselt werden.

Trotz frei benennbarer Prozessschritte bleiben wenige technische Kategorien
geschützt, weil Berechtigungen, Aufbewahrung oder Übergaben davon abhängen:
Eingang, aktiver Prozess, Basisqualifikation, vorläufige Warteposition,
Ablehnung/Rückzug, Einstellungsfreigabe, Einstellung und Archiv. Ein Status
verweist auf eine solche Kategorie, statt Sicherheitsregeln aus seinem
sichtbaren Namen abzuleiten. Deaktivierte oder umbenannte Stati verändern
historische Verläufe nicht.

### 6. Temporär aktivierbare Zwischenstati und Aktivitäten

Personalreferent*innen können für eine einzelne Bewerbung eine vorbereitete
Aktivität aktivieren. Dazu gehören insbesondere:

- Online-Kurzfragebogen,
- Telefoninterview,
- Vorstellungsgespräch und
- weitere administrativ angelegte Zwischenaktivitäten.

Eine Aktivierung speichert Vorlage und Revision, Verantwortliche, Start,
Frist, Zustand und Ergebnis. Der zugehörige Zwischenstatus ist nur so lange
aktiv, wie die Aktivität offen ist. Abschluss, Abbruch, Ablauf und erneute
Freigabe bleiben im Verlauf sichtbar.

Ein Online-Kurzfragebogen erhält einen zufälligen, zeitlich befristeten und
widerrufbaren Einmalzugang. Bewerber*innen benötigen dafür kein
Nextcloud-Konto. Der Link gewährt ausschließlich Zugriff auf genau den
freigegebenen Fragebogen, nicht auf Bewerbungsakte, Notizen oder andere
Bewerbungen. Antworten werden erst nach einer ausdrücklichen Abgabe als
eingegangen markiert; abgelaufene oder widerrufene Links sind wirkungslos.

### 7. Basisqualifikation für Assistenz-Bewerber*innen

Die Basisqualifikation ist ausschließlich für Bewerbungen auf entsprechend
gekennzeichnete Assistenz-Stellen zulässig. Für andere Berufsgruppen darf
dieser Prozessschritt weder angeboten noch über einen direkten Request
gesetzt werden.

1. Eine Personalreferent*in merkt eine grundsätzlich geeignete Bewerbung für
   einen konkreten BQ-Durchlauf vor.
2. Die Bewerbung wechselt in den sichtbaren Status des Durchlaufs,
   beispielsweise `BQ 03/27`. Sie ist damit zur möglichen Einstellung
   vorgemerkt, aber weiterhin eine Bewerbung.
3. Mit dieser Zuordnung erhält Lohn sofort den eng begrenzten Lesezugriff auf
   die Vertragsstammdaten und kann den Vertrag während der laufenden BQ
   vorbereiten.
4. Während des ungefähr zehntägigen Lehrgangs werden die erforderlichen
   Grundfähigkeiten vermittelt. Teilnahme und Bewertung gehören zum
   BQ-Durchlauf und bleiben von Interviewnotizen sowie Vertragsstammdaten
   getrennt.
5. Nach Abschluss wird das fachlich freigegebene Bewertungsergebnis der
   Bewerbung zugeordnet. Die konkrete Bewertung ersetzt keine
   Einstellungsentscheidung.
6. Erst die anschließende ausdrückliche Entscheidung der Personalreferent*in
   führt entweder in die Einstellungsfreigabe oder in einen anderen
   zulässigen Bewerbungsstatus.

Die BQ-Zuordnung löst die Vertragsvorbereitung durch Lohn aus, aber keinen
Zugriff einer Erstbegleitung. Die Person ist während der BQ nicht eingestellt.
Lohn sieht nur die Vertragsstammdaten und weder Bewerbungsunterlagen,
Interviews, interne Anmerkungen noch BQ-Bewertungen. Abbruch, Nichtteilnahme,
Verschiebung in einen anderen Durchlauf und eine ausstehende Bewertung müssen
ohne Verlust des bisherigen Verlaufs abbildbar sein. Endet die
BQ-Zuordnung ohne Einstellungsfreigabe oder Einstellung, endet auch der daraus
abgeleitete Lohnzugriff.

Zunächst beginnt der Lohnzugriff unmittelbar mit der Zuordnung zum
BQ-Durchlauf. Als spätere konfigurierbare Regel ist vorzusehen, den Zugriff
erst am hinterlegten BQ-Beginn zu aktivieren. Diese Terminregel darf den
Zugriff nicht vor dem Start erteilen und muss Verschiebungen, Abbruch und
manuelle Korrekturen nachvollziehbar behandeln.

Bis zu einem eigenen BQ-Modul verwaltet AD Recruitment Durchlauf,
Teilnahmezustand und freigegebenes Bewertungsergebnis selbst. Ein späteres
Modul kann Terminplanung, Durchführung und ausführlichere Bewertung
übernehmen. Die optionale Integration verwendet einen kleinen versionierten
Capability-/Event-Vertrag und niemals direkte Zugriffe auf Tabellen,
Controller oder Assets des anderen Moduls. Ohne dieses Modul bleibt der
manuelle BQ-Prozess vollständig nutzbar.

### 8. Interviews und Entscheidungen

- Telefon- und Vorstellungsgespräche basieren auf versionierten Vorlagen.
- Die konkrete Interviewinstanz behält einen unveränderlichen Snapshot der
  verwendeten Vorlage.
- Entwürfe sind gegen paralleles Überschreiben geschützt; abgeschlossene
  Interviews werden nicht still verändert.
- Pflichtfragen müssen vor dem Abschluss beantwortet sein.
- Interne Bewertungen und Anmerkungen sind von Bewerberkommunikation und
  Vertragsstammdaten getrennt berechtigt.
- Eine Entscheidung führt über einen zulässigen Statusübergang. Absage,
  Rückzug, BQ-Zuordnung, BQ-Ergebnis und Einstellungsfreigabe bleiben mit
  Zeitpunkt und ausführender Person nachvollziehbar.

### 9. Kommunikation

Ein- und ausgehende Nachrichten bilden eine chronologische Kommunikation in
der Bewerbungsakte. Ausgehende Nachrichten können aus versionierten Vorlagen
erstellt, vor Versand bearbeitet und erst nach erfolgreichem Versand als
gesendet markiert werden. Antworten sollen über technische Kennungen wieder
derselben Bewerbung zugeordnet werden können.

Mailabruf und Versand müssen wiederholbar sein: Ein erneuter Hintergrundlauf
erzeugt weder doppelte Nachrichten noch einen doppelten Versand. Nicht
eindeutig zustellbare, zuordenbare oder versendbare Vorgänge erhalten einen
sichtbaren Fehlerzustand zur manuellen Bearbeitung.

### 10. Einstellungsfreigabe und Übergabe

Bei der Freigabe zur Einstellung wird die Bewerbung verpflichtend einem
Bürobereich zugeordnet. Dadurch werden die zuständigen Erstbegleitungen
ermittelt. Die Freigabe aktiviert deren lesenden Zugriff; das Ende erfolgt
vorerst manuell.

Lohn erhält bei Assistenz-Bewerbungen ab der Zuordnung zu einem BQ-Durchlauf,
bei anderen Berufsgruppen spätestens ab der Einstellungsfreigabe lesenden
Zugriff auf alle für die Vertragsvorbereitung vorgesehenen Stammdaten,
insbesondere Adresse, Geburtsdaten, Bankverbindung, Krankenkasse, Steuer- und
Sozialversicherungsdaten sowie Vertragsparameter. Lohn erhält dadurch keinen
Zugriff auf Interviews, BQ-Bewertungen, Bewerbungsunterlagen oder interne
Anmerkungen.

### 11. Abschluss und Aufbewahrung

Abgelehnte, zurückgezogene und eingestellte Bewerbungen werden nicht durch
eine bloße Statusänderung gelöscht. Statusabschluss, operative
Archivierung, gesetzliche Aufbewahrung und endgültige Löschung sind getrennte
Vorgänge. Die Teilnahme an einer Basisqualifikation ist keine Einwilligung in
eine verlängerte Aufbewahrung. Fristen, Rechtsgrundlagen, Ausnahmen, Sperren
und Nachweise müssen vor der automatischen Löschung verbindlich festgelegt
werden.

## Administration

Der Adminbereich bündelt mindestens:

- Postfächer und technische Empfangs-/Versandverbindungen,
- Stellen und optionale stellenbezogene Eingangskanäle,
- Prozessstati, Kategorien, Reihenfolge und Übergänge,
- Vorlagen für Nachrichten, Fragebögen und Interviews,
- Kennzeichnung der Assistenz-Stellen, lokale BQ-Durchläufe und deren
  Zuordnungen, solange kein separates BQ-Modul angebunden ist,
- Felder und Extraktionszuordnungen für Stammdaten,
- granulare Fähigkeiten und Vertretungsscope,
- Erstbegleitungsgruppe und später deren automatische Endregeln,
- Aufbewahrungs-, Archivierungs- und Löschregeln sowie
- Fehler-/Quarantäneübersicht der Hintergrundverarbeitung.

Geheimnisse von Postfächern oder Providern werden niemals in der
App-Datenbank, in Logs oder in exportierter Konfiguration im Klartext
gespeichert. Wo verfügbar, werden Nextcloud-native Secret- und
Hintergrundjobmechanismen verwendet.

## Berechtigungs- und Nachvollziehbarkeitsgrundsatz

Personalreferent*innen besitzen Vollzugriff. Vertretungen erhalten
konfigurierbare Fähigkeiten mit globalem, bereichsbezogenem oder
bewerbungsbezogenem Scope. Erstbegleitungen und Lohn besitzen die in
`AGENTS.md` eng begrenzten Leserechte. Jede Listen-, Detail-, Such-, Export-,
Mail-, Dokument- und Statusoperation prüft diese Rechte serverseitig.

Statuswechsel, Zuordnungen, Stammdatenübernahmen, Fragebogenfreigaben,
Versand, Berechtigungsänderungen und Einstellungsfreigaben werden mit Akteur,
Zeitpunkt und fachlichem Ergebnis protokolliert. Auditdaten enthalten nur die
für den Nachweis erforderlichen Metadaten und keine unnötigen sensiblen
Inhalte.

## Bereits vorhandener Stand und Abgrenzung

Bereits vorhanden sind die Grundobjekte Stelle, Person und Bewerbung,
kontrollierte fest codierte Statusübergänge, versionierte Interviewvorlagen
und -instanzen, Einstellungsfreigabe, Vertragsstammdaten sowie das granulare
Berechtigungsmodell. Ebenfalls vorhanden ist der erste manuelle
BQ-Durchstich: Kennzeichnung der Stellen, lokale Durchläufe, Zuordnung,
einfache versionierte Bewertung und die davon abhängige, datensparsame
Lohnfreigabe. Ein erster Bewerbungsarbeitsplatz bietet Tabelle, nach Status
gruppierte Karten und grundlegende Filter. Anlageformulare öffnen über
Buttons in Dialog-Overlays; Bewerber*innen und Bewerbungen sind dort
ausdrücklich als manuelle Ausnahme gekennzeichnet.

Ebenfalls umgesetzt ist ein erster privater Posteingang für bereits
normalisierte Nachrichten: Websiteformular und freie Mail werden mit
unverändertem Mailtext, quellmarkierten Feldvorschlägen und bis zu fünf
validierten PDF-Originalen duplikatfrei importiert. Personalreferat und global
vertretende Bearbeitungskräfte können diese Nachrichten bestehenden
Bewerbungen zuordnen, die Zuordnung korrigieren oder Nicht-Bewerbungen
schließen. Jede Zustandsänderung ist versioniert und auditiert. Nach der
Zuordnung folgen Mailtext und Anhangsmetadaten dem serverseitigen
Bewerbungsscope.

Validierte PDF-Anhänge öffnen innerhalb desselben Scopes in einer großen
Lightbox. Vor der Ausgabe wird der Inhalt gegen den beim Import gespeicherten
Hash geprüft. Personalreferent*innen können Textstellen oder grafische
Bereiche markieren und mit Vorerfahrung, Deutschniveau, Geburtsdatum,
Geburtsort oder dem freien Kommentar der Bewerbung verbinden. Seite,
Position, Quelltext und Zielwert bleiben getrennt vom unveränderlichen PDF
nachvollziehbar. Strukturierte bestehende Werte werden nur nach sichtbarer
Bestätigung ersetzt. Beim freien Kommentar wird der neue Inhalt mit
Zeilenumbruch angehängt; vorhandener Inhalt bleibt vollständig erhalten. Das
Lesen folgt der Akte; zum Verknüpfen sind `manage_documents` und das passende
Feldrecht erforderlich.

Noch nicht vorhanden sind insbesondere konfigurierbare Prozessstati,
realer Postfachabruf und Mailversand, die vollständige nutzerverwaltete
Dokumentenakte, Bestätigung einzelner Extraktionsvorschläge,
öffentliche Kurzfragebogenlinks, BQ-Verschiebungen und die optionale
BQ-Modulanbindung. Diese Punkte sind keine verworfenen Ideen,
sondern Bestandteil des verbindlichen Zielbilds und werden in `ROADMAP.md`
als getrennt abnehmbare Arbeitspakete geführt.
