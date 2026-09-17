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
  Prozess. Nach Auswahl der Berufsgruppe stehen nur fachlich passende Gruppen
  aus dem kanonischen AD-Organisationsmodell bereit. Verantwortliche Personen
  werden in den ausgewählten Gruppen über die native Nextcloud-Benutzersuche
  gefunden; frei eingegebene Gruppen- oder Benutzerkennungen sind unzulässig.
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

Fachlich verantwortlich sind Personalreferent*innen. Von ihnen ausdrücklich
benannte Vertretungen bearbeiten ausschließlich ihre fest zugewiesenen
Bereiche und Fähigkeiten. Für jede Ansicht und Aktion gilt durchgehend das
Least-to-know-Prinzip; eine allgemeine Sichtbarkeit der Recruiting-App oder
eine technische Administrationsrolle erteilt keinen fachlichen Zugriff.

Eine Fünf-Sterne-Bewertung bleibt eine ausschließlich manuell eingetragene
Auswahlhilfe für berechtigte Personalreferent*innen und ihre fest
bereichsgebundenen Vertretungen. Sie wird weder berechnet noch für
automatische Sortierung, Filterung, Ranking, Profiling, Empfehlung oder
Entscheidung verwendet, löst keine automatische Folge aus und wird nicht
extern offengelegt. Die abschließende Auswahl trifft immer ein Mensch.

Das getrennte Stammdatum `m/w/d` bleibt für Assistenz-Bewerber*innen erhalten,
weil es später die selbstbestimmte Personalauswahl unterstützen kann. Es wird
ausschließlich manuell durch Personalreferat oder fest bereichsgebundene
Vertretungen gepflegt, niemals abgeleitet, bewertet oder für automatische
Entscheidungen genutzt und nur intern nach Least-to-know angezeigt.
Assistenznehmer*innen erhalten in diesem Recruitingprozess keine Information
daraus. Eine spätere Übergabe an einen noch zu modellierenden Folgeprozess
wird hier nur als Systemgrenze benannt; AD Recruitment bleibt bis dahin die
kanonische Quelle.

Eine optionale respektvolle Wunschanrede ist fachlich und technisch von
`m/w/d` getrennt. Sie wird weder abgeleitet noch bewertet. Das Feld wird erst
aktiviert, nachdem eine gegebenenfalls erforderliche Beteiligung des
Betriebsrats außerhalb des Systems abgeschlossen ist; in den Bewerberpool
wird es nur mit ausdrücklicher Pool-Einwilligung übernommen.

Erkennbare Stammdaten aus Nachrichtentexten und Anhängen werden als
Feldvorschläge dargestellt. Jeder Vorschlag zeigt seine Quelle und wird vor
der Übernahme durch eine berechtigte Person bestätigt oder korrigiert. Die
Extraktion überschreibt niemals still vorhandene Daten und schreibt keine
unsicheren Werte automatisch in besonders sensible Felder wie Bank,
Krankenkasse, Steuer- oder Sozialversicherungsdaten.

Textbasierte PDF-Lebensläufe werden beim Import ausschließlich lokal
ausgelesen und ohne KI nach konservativen Stichworten für Standardfelder
durchsucht. Bildbasierte PDFs bleiben bis zu einer getrennten OCR-Entscheidung
uninterpretiert. Nextcloud-Administrationen sehen das aktive lokale Verfahren
und sein Laufzeitwerkzeug in den Einstellungen. Ein späteres, lokal auf dem
Server installiertes Modell ist dort bereits als noch nicht angebundene und
nicht aktivierbare Alternative sichtbar; eine Übertragung an externe
KI-Dienste findet nicht statt.

Mit der bestätigten Zuordnung werden Anrede, Titel, private E-Mail,
Telefonnummer, geplanter Eintritt und Ort in bislang leere Vertragsfelder
vorbelegt. Vorhandene Werte bleiben unverändert. Der Vertragsbereich ist im
Normalzustand eine kompakte Leseansicht; Eingabefelder erscheinen erst nach
einer expliziten Bearbeitungsaktion. Anrede, Titel, Versicherungsart und
Steuerklasse verwenden kontrollierte Auswahllisten, übrige Felder zu ihrem
Inhalt passende Typen und Längen.

Die gewünschte Wochenarbeitszeit gehört zum Bewerbungswunsch und ist keine
Vertragszusage. Sie darf als Einzelwert oder Bereich angegeben werden; für
einen Bereich gilt `0 < von ≤ bis ≤ 80`. Verbindliche Wochenstunden werden erst im Vertragsbereich
erfasst. Für Assistenz kann dort KAPOVAZ vereinbart werden; die Anwendung
behandelt Vollzeit für Assistenz nicht als Standard.

Beschriftete Formularfelder gelten dabei als nachvollziehbare
Mailbody-Vorschläge. In freien Nachrichtentexten darf genau eine eindeutig
erkennbare E-Mail-Adresse vorgeschlagen werden. Mehrere unterschiedliche
Adressen bleiben mehrdeutig; die Anwendung wählt keine davon still aus.
Fehlt ein beschrifteter Name, dürfen eine explizite Selbstvorstellung, eine
übliche Grußsignatur, der Absender-Anzeigename oder eine eindeutig aus Vor-
und Nachnamen aufgebaute Absenderadresse in dieser Reihenfolge als
quellmarkierter Vorschlag dienen. Rollenadressen, Ziffern und unklare
Einzelbegriffe bleiben ohne Vorschlag.

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

Der Kurzfragebogen ist ein regulär überspringbarer Prozessschritt. Die
angebotenen Folgestati erlauben deshalb auch den direkten Wechsel von der
Vorprüfung beziehungsweise einem noch offenen Kurzfragebogen zu Telefon,
Vorstellung, Entscheidung oder Ablehnung. Personalreferat und
Nextcloud-Administration dürfen ausschließlich über das Status-Dropdown nach
einer ausdrücklichen Sicherheitsabfrage weitere Prozesssprünge ausführen. Die
dafür erforderliche Fähigkeit ist nicht delegierbar, wird serverseitig erneut
geprüft und der Ausnahmeweg gesondert auditiert. Drag-and-drop bleibt auf
reguläre Kanten beschränkt. Unbekannte und identische Stati, eine Einstellung
ohne vorherige Einstellungsfreigabe sowie eine Einstellungsfreigabe trotz
ungeeignetem oder offenem BQ-Ergebnis bleiben auch als Ausnahme verboten.

Eine Karte darf nur in einen vom Server für genau diese Bewerbung gelieferten
Zielstatus verschoben werden. Manipulierte Karten- oder Spaltenkennungen
erteilen keine Rechte. Für die Einstellungsfreigabe ist auch im
Kartenarbeitsplatz ein gültiger Bürobereich erforderlich.

`Zurückgezogen` ist kein laufender Arbeitsvorrat und erhält deshalb keine
Kartenspalte. Der Abschluss erfolgt über eine deutlich als destruktiv
erkennbare rote Aktion mit Bestätigungsabfrage; der Statuswechsel selbst
bleibt serverseitig kontrolliert und auditiert.

### Vertragsbereich und Tarifgrundlage

Der Vertragsbereich ist von der Bewerbungsakte fachlich getrennt. Bankdaten,
Steuer-ID, Krankenkasse und Sozialversicherungsnummer werden erst ab
`approved_for_hire` für LoBu sichtbar und ausschließlich dort bearbeitet.
Personalreferat und BQ sehen diese Felder vorher nicht. Familienstand gehört
weder zum Bewerbungsprozess noch zur aktiven Vertragsdatenerfassung.

Die Stelle ist die kanonische Quelle für Vertragsdauer, Entgeltgruppe,
ausgeschriebene Wochenstunden, tarifliche Vollzeitstunden, Urlaub und
Arbeitsort. Berlin ist der Standardarbeitsort. Das Arbeitszeitmodell wird
serverseitig abgeleitet: Assistenz verwendet `KAPOVAZ`, alle anderen
Berufsgruppen `Festgehalt`. Ein individuelles Gehalts- oder Währungsfeld wird
nicht geführt.

Fachliche Tarifgrundlage ist der bereitgestellte „Haustarifvertrag inkl.
Änderungen zum 1. Oktober 2023“ mit den enthaltenen Änderungen beziehungsweise
Tabellen bis 2024/2025. Die Ausschreibung referenziert die daraus fachlich
bestätigten Werte; die Bewerbungsakte kopiert oder überschreibt sie nicht.

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

Die Basisqualifikation ist für jede Assistenz-Stelle verpflichtend und wird
allein aus deren Berufsgruppe abgeleitet. Sie ist nicht separat ein- oder
abschaltbar. Für andere Berufsgruppen darf dieser Prozessschritt weder
angeboten noch über einen direkten Request gesetzt werden.

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

Bis zur Anbindung der eigenständigen BQ-Planer-App verwaltet AD Recruitment Durchlauf,
Teilnahmezustand und freigegebenes Bewertungsergebnis selbst. Ein späteres
BQ-Planer-Modul kann Terminplanung, Durchführung und ausführlichere Bewertung
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
- Scoring, algorithmisches Ranking, Profiling und automatische
  Bewerbungsentscheidungen sind ausgeschlossen. Manuelle Bewertungen und
  Validierungen unterstützen berechtigte Personen, ersetzen ihre Entscheidung
  aber nicht.

### 9. Kommunikation

Ein- und ausgehende Nachrichten bilden eine chronologische Kommunikation in
der Bewerbungsakte. Ausgehende Nachrichten können aus versionierten Vorlagen
erstellt, vor Versand bearbeitet und erst nach erfolgreichem Versand als
gesendet markiert werden. Antworten sollen über technische Kennungen wieder
derselben Bewerbung zugeordnet werden können.

Für zulässige Statusübergänge kann das Personalreferat die Entwurfserzeugung
je Kante ein- oder ausschalten und eine Standardplanung wählen. Der
Statuswechsel sendet nie unbeaufsichtigt. Vor der Freigabe bleiben Zieladresse,
Betreff und Text bearbeitbar; die ursprüngliche Personenadresse wird bei einer
Korrektur weiterhin angezeigt und gespeichert. Neben sofortiger und frei
terminierter Zustellung kann gesammelt der kommende Montag um 09:00 Uhr
gewählt werden. Ein zentraler administrativer Testmodus leitet alle derzeit
ausgehenden Recruitment-Mails serverseitig an eine Standard-Testadresse um.

Für jeden fachlich zulässigen Statusübergang steht eine vorbereitete Regel
bereit. Die zugehörige Regel ist zunächst deaktiviert und wird durch
Personalreferent*innen bewusst je Übergang eingeschaltet. Eine Vorlage kann
mehreren Kanten zugeordnet werden; alle Übergänge nach `withdrawn` verwenden
standardmäßig dieselbe Rückzugsvorlage. Vorlagen sind
versioniert und unterstützen einen kleinen bereinigten HTML-Wortschatz für
Absätze, Fett, Kursiv, Listen und sichere Links. Vor der Versandfreigabe bleibt
der erzeugte Entwurf mit demselben Editor änderbar; zu jeder HTML-Mail wird
eine Klartextalternative erzeugt. Aufgelöste Bewerberwerte werden im HTML
escaped und können keine eigene Formatierung oder Links einschleusen.

Mailabruf und Versand müssen wiederholbar sein: Ein erneuter Hintergrundlauf
erzeugt weder doppelte Nachrichten noch einen doppelten Versand. Nicht
eindeutig zustellbare, zuordenbare oder versendbare Vorgänge erhalten einen
sichtbaren Fehlerzustand zur manuellen Bearbeitung.

### 10. Einstellungsfreigabe und Übergabe

Bei der Freigabe zur Einstellung wird die Bewerbung verpflichtend einem
Bürobereich zugeordnet. Dadurch werden die zuständigen Erstbegleitungen
ermittelt. Die Freigabe aktiviert deren lesenden Zugriff; das Ende erfolgt
vorerst manuell.

LoBu erhält für alle Berufsgruppen erst ab der Einstellungsfreigabe lesenden
und für LoBu-only Felder schreibenden Zugriff auf die für die
Vertragserstellung vorgesehene Projektion. Vertragsdauer, Arbeitszeitmodell,
Entgeltgruppe, Stunden, Urlaub und Arbeitsort werden aus der Stelle abgeleitet.
LoBu erhält dadurch keinen
Zugriff auf Interviews, BQ-Bewertungen, Bewerbungsunterlagen oder interne
Anmerkungen.

### 11. Abschluss und Aufbewahrung

Abgelehnte, zurückgezogene und eingestellte Bewerbungen werden nicht durch
eine bloße Statusänderung gelöscht. Statusabschluss, operative
Archivierung und endgültige Löschung sind getrennte Vorgänge. Reguläre
Bewerbungsakten einschließlich ihrer Unterlagen und ein- sowie ausgehenden
Kommunikation werden spätestens sechs Monate nach dem fachlichen Abschluss
des Bewerbungsverfahrens gelöscht, sofern keine gesonderte Einwilligung oder
aktive rechtliche beziehungsweise datenschutzrechtliche Sperre entgegensteht.
Sechs Monate sind zugleich der administrativ konfigurierbare Standardwert.
Mitglieder der Nextcloud-Gruppe
`Datenschutzbeauftragte` dürfen die Frist verkürzen oder verlängern; eine
Änderung gilt anhand des ursprünglichen Abschlusszeitpunkts auch für bereits
vorhandene Akten.
Zur Einstellung freigegebene Akten dürfen ab der Freigabe höchstens sechs
Monate für einen selektiven manuellen Export oder die erforderliche
Weiterbearbeitung vorgehalten und schon vorher manuell gelöscht werden. Ohne
aktive Sperre erfolgt danach die automatische Löschung. Der spätere
Vertragsvorbereitungs- und Stammdatenprozess ist eine eigene, noch zu
modellierende Verarbeitung und wird nicht durch eine pauschale Übernahme der
Bewerbungsakte vorweggenommen.
Eine gesonderte Pool-Einwilligung verlängert diese Frist nicht, sondern
begründet die nachfolgend getrennt beschriebene Poolverarbeitung. Technische
Policyversion und Wirksamkeitszeitpunkt, Löschreihenfolge, Sperren,
Nebenläufigkeit, Wiederholungsverhalten, Fehlernachweise und
Backup-/Restore-Neuplanung aus dem ursprünglichen Trigger müssen vor der
automatischen Ausführung verbindlich umgesetzt und abgenommen werden. Bis
dahin behauptet AD Recruitment keine ausführende Retention-Funktion.

### Datenschutzgerechte Rückstellung im Bewerberpool

Eine Rückstellung ist für aktuell nicht angenommene Bewerber*innen sowie für
Initiativbewerber*innen ohne aktuell passende Ausschreibung vorgesehen. Sie
betrifft in erster Linie andere Berufsgruppen als Assistenz. Die Teilnahme,
Zuordnung oder Bewertung in einer Basisqualifikation ist weder Voraussetzung
noch Auswahlmerkmal und verlängert keine Poolfrist. Die Rückstellung ist von
der ursprünglichen Bewerbung und von jeder BQ-Verarbeitung getrennt,
freiwillig, versioniert nachweisbar und jederzeit widerrufbar.

Der Pool hält Personbezug, Ausgangsbewerbung, Berufsgruppe, gewünschten
Stundenkorridor, gewählte Bereiche und die für eine spätere Bewerbung
relevanten Bewerbungsunterlagen vor. Unterlagen bleiben Teil der Poolakte,
werden aber nicht automatisch als Matchingmerkmale ausgewertet.
Interviewantworten, Freitextnotizen und BQ-Daten werden nicht in die
Poolverarbeitung übernommen.

Die Einwilligungs- und Aufbewahrungsdauer verwendet zwölf Monate ab Erteilung
oder ausdrücklicher Erneuerung als administrativ konfigurierbaren Standardwert.
Mitglieder der Gruppe `Datenschutzbeauftragte` dürfen diese Frist verkürzen
oder verlängern; Änderungen werden auch für vorhandene Poolakten aus ihrem
ursprünglichen Einwilligungs- oder Erneuerungszeitpunkt neu berechnet. Eine
rückwirkende Verlängerung ersetzt keine erforderliche passende Einwilligung
und hebt einen Widerruf nicht auf. Ein Widerruf beendet Matching, Kontakt und
aktive Poolnutzung sofort und löscht das gesamte Poolprofil, die
Poolunterlagen und jeden personenbezogenen Einwilligungsnachweis. Mit
Fristablauf beginnt eine zehn Tage lange Übergangsfrist, in der das Profil
vollständig inaktiv ist und ausschließlich eine Erneuerung der Einwilligung
zulässig bleibt. Ohne Erneuerung werden nach zehn Tagen ebenfalls Profil,
Unterlagen und personenbezogener Einwilligungsnachweis vollständig gelöscht.
Genau eine datensparsame Erinnerung darf vierzehn Tage vor Ablauf versandt
werden; sie enthält keine Stellen-, Bewerbungs- oder sonstigen Akteninhalte.

Für neu veröffentlichte, passende Ausschreibungen darf eine Nachricht nur
auf ausdrückliche Nachfrage beim Veröffentlichen oder durch einen manuellen
Auslöser einer berechtigten Person vorbereitet werden. Regelvorschläge dürfen
nur transparente Merkmale wie Berufsgruppe, Stundenkorridor und Region
verwenden. Personalreferent*innen oder ihre fest bereichsgebundenen
Vertretungen prüfen und bestätigen jede Kontaktaufnahme und lösen den Versand
manuell aus. Der minimale Kontaktverlauf enthält nur Stellenreferenz,
Zeitpunkt und manuellen Auslöser und wird mit der Poolakte gelöscht. Weder
Poolaufnahme noch Kontakt, neue Bewerbung, Zu- oder Absage erfolgen
automatisch; BQ-Daten sind hierfür irrelevant. Die Funktion ist initial
deaktiviert und setzt zur Aktivierung einen freigegebenen, versionierten
Datenschutzhinweis voraus.

Eine spätere Bewerbung ist stets eine neue, eigenständige Bewerbung mit
eigenem Fristbeginn. Alte Poolfelder und -unterlagen dürfen erst nach
ausdrücklicher Bestätigung der Bewerberperson als bearbeitbare Vorlage
übernommen werden. Die Übernahme reaktiviert weder die frühere Bewerbung noch
die Pool-Einwilligung.

### Beteiligung der Schwerbehindertenvertretung

Die freiwillige Selbstauskunft beschränkt sich auf die Angabe
`schwerbehindert oder gleichgestellt`. GdB-Zahl, Diagnosen und medizinische
Details werden nicht als strukturierte Bewerbungsdaten erhoben; aus
Unterlagen werden solche Angaben weder per OCR noch durch andere Extraktion
übernommen, bewertet oder für Scoring verwendet.

Eine positive Angabe löst die Beteiligung der Schwerbehindertenvertretung
aus. Die Einstellungsentscheidung bleibt bis zur dokumentierten Beteiligung
gesperrt. Lehnt die Bewerberperson die Beteiligung ausdrücklich ab, wird dies
nachweisbar festgehalten; die Ablehnung kann bis zur Einstellungsentscheidung
widerrufen werden und die Beteiligung beginnt dann unverzüglich.

Die app-spezifische native Nextcloud-Gruppe
`schwerbehindertenvertretung` erhält zunächst nur eine datensparsame
Benachrichtigung mit Fallreferenz. Nach Anmeldung sieht sie ausschließlich die
entscheidungsrelevanten Teile der konkreten Bewerbung und kann ihre
Beteiligung dokumentieren; allgemeine Personalreferatsnotizen, Pooldaten,
Stammdatenbearbeitung und andere Bewerbungen bleiben ausgeschlossen. Existenz
oder begründetes Nichtbestehen einer SBV werden durch Personalreferent*innen
mit festem Scope gepflegt, auditiert, jährlich und bei Organisationsänderung
überprüft. Ein fehlender oder ungeklärter Status sperrt die Entscheidung.

Für den Betriebsrat werden vorerst weder Gruppe, Dokumente, Fristen noch ein
Workflow in AD Recruitment angelegt. Die Beteiligung erfolgt vollständig
außerhalb des Systems.

### Manuelle Betroffenenrechte

Auskunft, Berichtigung, Löschung und Einschränkung beginnen mit einer
Identitätsprüfung außerhalb der App. Nach erfolgreicher Prüfung übermittelt
Personalreferat dem Datenschutz-Center ausschließlich eine stabile
Bewerber-ID; Name oder E-Mail-Adresse dienen dort nicht als Suchschlüssel.
Die Datenschutzbeauftragten lassen die app-eigene Subject-Projektion als
Vorschau zusammenstellen, prüfen sie manuell und übermitteln das Ergebnis über
einen verifizierten externen Kanal. Ein automatischer Versand findet nicht
statt.

Berichtigungen werden durch zuständige Personalreferent*innen oder ihre fest
bereichsgebundenen Vertretungen ausgeführt und von den
Datenschutzbeauftragten im Vorgang bestätigt. Löschwünsche werden umgesetzt,
soweit keine vorrangige rechtliche Aufbewahrung oder aktive Sperre besteht.
Solange eine Sperre gilt, wird die Akte technisch auf den konkret benannten
Rechtszweck beschränkt; Auswahl, Poolnutzung, Kommunikation und Export bleiben
gesperrt. Nur Mitglieder von `Datenschutzbeauftragte` dürfen eine solche
Sperre begründet und auditiert aufheben.

Der Rechtevorgang mit stabiler Bewerber-ID, Zeitpunkten und Ergebnis wird
sechs Monate nach Abschluss gelöscht. Ein manueller Export protokolliert für
sechs Monate nur Empfänger, Umfang und Zeitpunkt, nicht den exportierten
Inhalt. Diese Protokolle ermöglichen erforderliche Folgeinformationen bei
späterer Berichtigung oder Löschung.

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
- Aufbewahrungs-, Archivierungs- und Löschregeln; fachliche
  Datenschutzkonfiguration ist dabei ausschließlich der Nextcloud-Gruppe
  `Datenschutzbeauftragte` vorbehalten,
- Fehler-/Quarantäneübersicht der Hintergrundverarbeitung.

Geheimnisse von Postfächern oder Providern werden niemals in der
App-Datenbank, in Logs oder in exportierter Konfiguration im Klartext
gespeichert. Wo verfügbar, werden Nextcloud-native Secret- und
Hintergrundjobmechanismen verwendet.

## Berechtigungs- und Nachvollziehbarkeitsgrundsatz

Personalreferent*innen besitzen Vollzugriff. Vertretungen erhalten nur die
ausdrücklich freigegebenen Fähigkeiten in ihren fest zugewiesenen Bereichen.
Erstbegleitungen und Lohn besitzen die in
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
Interviewfragen und -vorlagen, Mailvorlagen, Bewerberpool-Grundkonfiguration
sowie die Verwaltung von Vertretungsrechten sind als gelegentliche
PersRef-Pflegeaufgaben unter einem
capability-gebundenen Einstellungsmenü zusammengefasst und belegen keine
eigenen Haupttabs mehr. Ausschließlich systemweite Grundkonfigurationen für
Mail-Testmodus, Datenextraktion und strukturelle
Erstbegleitungsgruppe liegen im nativen, nur für Nextcloud-Admins sichtbaren
Adminabschnitt `AD Recruitment`.

Ebenfalls umgesetzt ist ein erster privater Posteingang für bereits
normalisierte Nachrichten: Websiteformular und freie Mail werden mit
unverändertem Mailtext, quellmarkierten Feldvorschlägen und bis zu fünf
validierten PDF-Originalen duplikatfrei importiert. Personalreferat und global
vertretende Bearbeitungskräfte können diese Nachrichten bestehenden
Bewerbungen zuordnen, die Zuordnung korrigieren oder Nicht-Bewerbungen
schließen. Für neue oder unklare Nachrichten können sie außerdem nach Auswahl
einer aktiven Stelle Person und Bewerbung kontrolliert neu anlegen. Erkannte
Personendaten sind dabei korrigierbare Vorbelegungen; weitere Werte werden nur
einzeln bestätigt übernommen. Neuanlage, Feldvorbelegung, Zuordnung und Audit
sind atomar. Jede Zustandsänderung ist versioniert und auditiert. Nach der
Zuordnung folgen Mailtext und Anhangsmetadaten dem serverseitigen
Bewerbungsscope.
Textbasierte PDFs werden innerhalb der Importgrenze lokal in Text überführt
und zusammen mit Mailtext beziehungsweise bereits normalisiertem Adaptertext
nach quellmarkierten Standardfeldern durchsucht.

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
realer Postfachabruf, Antwortzuordnung und eine vollständige
Kommunikationschronik, die vollständige nutzerverwaltete
Dokumentenakte, öffentliche Kurzfragebogenlinks, BQ-Verschiebungen und die optionale
BQ-Modulanbindung. Diese Punkte sind keine verworfenen Ideen,
sondern Bestandteil des verbindlichen Zielbilds und werden in `ROADMAP.md`
als getrennt abnehmbare Arbeitspakete geführt.
