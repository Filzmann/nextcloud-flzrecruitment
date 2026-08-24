# Architektur des ersten AD-Recruitment-Durchstichs

## Technische Identität

Die Nextcloud-App-ID, Route, Asset-ID und DDEV-Mount verwenden
`adrecruitment`. Der bestehende PHP-Namespace `OCA\Recruitment` und die
app-eigenen Tabellen mit Präfix `rec_` bleiben bewusst stabil. Dadurch kann
eine bereits angelegte lokale Installation unter der neuen App-ID dieselben
Fachdaten idempotent weiterverwenden, ohne Tabellen zu kopieren oder
umzubenennen.

## Schichten

Die App trennt Domänen- und Anwendungslogik, Datenzugriff, serverseitige
Berechtigungen, Controller und Browseroberfläche. `RecruitmentRepository`
bindet alle QueryBuilder-Werte. Controller übersetzen HTTP-Anfragen und
Fehler, entscheiden aber keine Fachregeln.

Der Browserarbeitsplatz verwendet für Tabelle und Karten dieselbe bereits
serverseitig berechtigte Bootstrap-Menge. Das reine Workbench-Modul filtert
und gruppiert diese Daten, erteilt aber keine Rechte. Anlageformulare werden
über native `dialog`-Overlays geöffnet; Schließen, Escape und Fokus-Rückgabe
sind zentral gekapselt. Die manuelle Personen- und Bewerbungsanlage bleibt als
Ausnahme sichtbar, bis der geplante Postfacheingang diese Datensätze im
Regelfall erzeugt.

Der Workbench-Bootstrap ergänzt jede bereits berechtigte Bewerbung um ihre
serverseitig ermittelten Zielstati. Die Browserlogik prüft diese Liste vor
Drag-and-drop oder Tastaturaktion; der schreibende API-Endpunkt prüft
Statuskante, Version, Akteur und gegebenenfalls Bürobereich erneut und bleibt
die einzige Berechtigungs- und Fachwahrheit.

Der lokale Demo-Seed orchestriert ausschließlich vorhandene Fachservices und
verwendet stabile `demo-*`-Zuordnungsschlüssel sowie `@demo.invalid`-Adressen.
Er ist idempotent, importiert keine fremden Daten und überschreibt keine
bereits bearbeiteten Demo-Zustände.

Die Stellenmaske bezieht ihre auswählbaren Beteiligungsgruppen aus den
LocalBase-Rollen des kanonischen AD-Organisationsmodells und begrenzt sie
app-lokal je Berufsgruppe. Die Personensuche verwendet ausschließlich die
native Nextcloud-Gruppensuche innerhalb der ausgewählten Gruppen. Der Server
prüft sowohl die Gruppenauswahl als auch jede übermittelte Mitgliedschaft
erneut; Browseroptionen erteilen keine Berechtigung.

Selten benötigte Pflegeoberflächen für Interviewfragen, Mailvorlagen und
Berechtigungen werden unter einem gemeinsamen Haupttab `Einstellungen`
gebündelt. Die dortige Unter-Navigation wird ausschließlich aus den bereits
serverseitig gelieferten Capabilities abgeleitet; sie ist keine zusätzliche
Berechtigungsquelle und verändert die Prüfungen der Endpunkte nicht.

## Zustände und Nebenläufigkeit

Bewerbungen starten in `received`. Zulässige Übergänge werden zentral
festgelegt und atomar mit einem Statusprotokolleintrag gespeichert.
Der Übergang nach `approved_for_hire` verlangt einen gültigen Bürobereich und
aktiviert in derselben versionsgeschützten Änderung den Zugriff passender
Erstbegleitungen. Dieser Zugriff kann später manuell beendet und wieder
freigegeben werden.
Für jede Assistenz-Stelle führt eine eigene, atomare Zuordnung aus
`decision_pending` in `basis_qualification`. Die BQ-Pflicht wird aus der
Stellenkategorie abgeleitet und kann nicht unabhängig gesetzt oder entfernt
werden. Das
sichtbare `BQ MM/YY` stammt aus einem stabil identifizierten Durchlauf und
nicht aus einem dynamischen Statusschlüssel. Die einfachen Ergebnisse
`suitable`, `not_suitable`, `cancelled` und `no_show` sind separat
versionsgeschützt. Eine Einstellungsfreigabe ist bei vorhandener
BQ-Zuordnung nur nach `suitable` zulässig; das Ergebnis löst sie nicht selbst
aus.
Interviewinstanzen beginnen als `not_started`, wechseln beim ersten Entwurf
nach `in_progress` und werden nach erfolgreicher Pflichtfeldprüfung
`completed`. Jede Entwurfsänderung benötigt die gelesene Versionsnummer;
abweichende Versionen werden als Konflikt abgewiesen.

Interviewinstanzen speichern den vollständigen JSON-Snapshot der verwendeten
Vorlagenrevision. Spätere Änderungen an Vorlage, Fragen oder Bubbles ändern
weder Snapshot noch Antworten bestehender Interviews.

## Berechtigungen

Nextcloud-Admins und die LocalBase-Rolle `staff_hr` besitzen alle App-Rechte.
`finance` erhält keine Recruitment-Rechte. `payroll` darf für
`approved_for_hire` und `hired` sowie während einer laufenden BQ mit
ausstehendem oder geeignetem Ergebnis ausschließlich die Vertragsstammdaten
lesen; Bewerberakte, Interviews, Anmerkungen und BQ-Bewertungen gehören nicht
zu dieser Projektion. `not_suitable`, `cancelled` und `no_show` beenden den
BQ-begründeten Zugriff sofort. Zunächst gilt der Zuordnungszeitpunkt; später
kann eine konfigurierbare Regel den Beginn auf das Startdatum des
BQ-Durchlaufs verschieben. Der Erstbegleitungszugriff bleibt bis zur
Einstellungsfreigabe gesperrt.

Die nicht delegierbare Fähigkeit `manage_basis_qualification` ist nur für
Personalreferat und Nextcloud-Admins verfügbar. Sie schützt Durchlauf-,
Zuordnungs- und Bewertungsendpunkte vor jedem Objektzugriff.

Personalreferent*innen konfigurieren Vertretungskräfte nach einzelnen
Fähigkeiten. Jeder Eintrag gilt entweder global, für ausgewählte LocalBase-
Bürobereiche oder für einzelne Bewerbungs-IDs. Die Verwaltung weiterer
Vertretungen ist selbst nicht delegierbar. Nur Nextcloud-Admins ändern die
strukturelle Erstbegleitungsgruppe.

Diese strukturelle Gruppe sowie Mail-Testmodus und
Lebenslaufextraktion werden über einen app-eigenen `ISettings`-Abschnitt im
nativen Nextcloud-Adminbereich dargestellt. Die dortigen Formulare verwenden
weiterhin die CSRF-geschützten, serverseitig erneut auf Nextcloud-Adminstatus
prüfenden API-Endpunkte. Der operative Recruitment-Einstellungsbereich bleibt
die capability-gebundene Oberfläche für PersRef-Katalogpflege und erteilt
keine administrativen Systemrechte.

Die fachlichen Bewerberpool-Grundwerte bleiben dagegen Teil des
Recruitment-Moduls. `manage_candidate_pool` schützt Anzeige und Änderung
serverseitig und bleibt Personalreferat und Nextcloud-Admins vorbehalten sowie
nicht delegierbar. BQ-Durchläufe und -Bewertungen sind ebenfalls app-lokal,
aber ausdrücklich nur als Übergangsimplementierung bis zum versionierten
Capability-/Event-Vertrag einer eigenständigen BQ-Planer-App.

Erstbegleitungen benötigen zugleich die LocalBase-Rolle `eb`, Mitgliedschaft
in der konfigurierten Erstbegleitungsgruppe und Mitgliedschaft im Bereich der
Bewerbung. Sie lesen ausschließlich freigegebene Akten ihres Bereichs. Ein
ungültiger oder nicht persistierter LocalBase-Snapshot führt für alle
Nicht-Admins zu deny by default. Jeder API-Pfad prüft Fähigkeit, Akteur und
konkreten Bewerbungsscope serverseitig.

## Vertragsstammdaten

Die app-eigene Tabelle `rec_hiring_data` hält eine explizite Feldliste für
Personenstammdaten sowie die erst ab `approved_for_hire` durch LoBu
bearbeitbaren Bank-, Krankenkassen-, Steuer- und Sozialversicherungsdaten.
Unbekannte JSON-Felder,
ungültige Datums-, IBAN-, BIC- oder Zahlenwerte werden abgelehnt. Änderungen
sind optimistisch versioniert; das Berechtigungsaudit enthält nur Aktion,
Akteur, Scope und Feldnamen, niemals die sensiblen Feldwerte.

Vertragsdauer, Entgeltgruppe, ausgeschriebene und Vollzeit-Wochenstunden,
Urlaub sowie Arbeitsort liegen kanonisch an `rec_jobs`. Der
`HiringMasterDataService` erzeugt daraus die LoBu-Projektion und leitet
KAPOVAZ für Assistenz beziehungsweise Festgehalt für andere Berufsgruppen ab.
Gehalt und Währung sind kein aktiver Vertragseingang. Familienstand wird für
neue Writes abgelehnt; ein veröffentlichter Altstand kann beim Lesen
datensparsam ausgeblendet werden, ohne die gespeicherte Historie beiläufig zu
löschen.

## Migration

Die erste Migration erstellt ausschließlich app-eigene Tabellen. Bei einer
frischen Installation legt sie diese neu an; bei der Umbenennung von
`recruitment` auf `adrecruitment` erkennt sie die bestehenden `rec_`-Tabellen
und übernimmt sie ohne Transformation. Die Migration ist additiv und
wiederholbar, weil jede Tabelle vor der Anlage geprüft wird.
Die zweite additive Migration ergänzt Bereich und Erstbegleitungsfreigabe an
Bewerbungen sowie getrennte Tabellen für Vertragsstammdaten und
Berechtigungsaudit. Veröffentlichte Migration 1 bleibt unverändert.
Die dritte additive Migration führte die damalige BQ-Kennzeichnung und
getrennte Tabellen für BQ-Durchläufe und Bewerbungszuordnungen ein. Bestehende
Bewerbungen werden nicht
umgeschrieben. Veröffentlichte Migrationen 1 und 2 bleiben unverändert.
Die vierte additive Migration ergänzt `desired_weekly_hours` nullable an
Bewerbungen und `profession_category` mit neutralem Bestandswert an Stellen.
Bestehende Stellen mit gesetztem historischem BQ-Merkmal werden im Mapper
weiterhin als Assistenz erkannt, weil
BQ fachlich ausschließlich dort zulässig ist. Bestehende Bewerbungen erhalten
keine erfundene Wunschstundenzahl. Veröffentlichte Migrationen 1 bis 3 bleiben
unverändert.
Die fünfte additive Migration ergänzt ausschließlich die nullable Spalte
`desired_weekly_hours_max`. Der vorhandene Wert bleibt Untergrenze oder
Einzelwert; Bestandsdaten bleiben ohne Obergrenze unverändert gültig. Die
Anwendung erzwingt `0 < von ≤ bis ≤ 80` und normalisiert gleiche Grenzen zu
einem Einzelwert. Die Migration ist durch Tabellen- und Spaltenprüfung
wiederholbar; nach Anwendung kann der Code zurückgebaut werden, die dann
ungenutzte nullable Spalte bleibt jedoch bestehen.
Die sechste additive Migration ergänzt getrennte Tabellen für Postfächer,
unveränderliche Eingangsnachrichten, Anhangsmetadaten und Zustandsaudit.
Fremdschlüssel begrenzen Zuordnung und Löschverhalten; eindeutige Indizes aus
Postfach plus externer Message-ID beziehungsweise Inhaltsfingerprint sichern
die Idempotenz auch auf Datenbankebene. Bestehende Personen, Bewerbungen und
Unterlagen werden nicht umgeschrieben. Veröffentlichte Migrationen 1 bis 5
bleiben unverändert.
Ein Rollback nach produktiver Datennutzung ist nicht automatisch möglich; die
Tabellen dürfen nur nach gesonderter Datenexport- und Löschentscheidung
entfernt werden.
Die siebte additive Migration ergänzt append-only Dokumentkommentare mit
Fremdschlüssel zum Anhang, optionalem Seiten- und Spaltenanker sowie einem je
Anhang eindeutigen Client-Schlüssel. Bestehende Originale und Zuordnungen
werden nicht verändert. Nach produktiver Kommentarnutzung darf auch diese
Tabelle nur nach einer gesonderten Export- und Löschentscheidung entfernt
werden.
Die achte additive Migration ergänzt die zuvor nicht vorhandenen
Bewerbungsfelder `previous_experience`, `german_language_level` und
`free_comment` mit leeren Standardwerten. Bestehende Bewerbungen werden nicht
inhaltlich transformiert. Eine neue Tabelle hält PDF-Fundstelle,
Zielbewerbung, Zielfeld, ausgewählten Text, angewandten und resultierenden
Wert, normierte Rechtecke, Akteur und Client-Schlüssel. Fremdschlüssel sichern
Anhang und Bewerbung; der eindeutige Client-Schlüssel pro Anhang verhindert
doppelte Requestausführung. Bereits in `rec_hiring_data` vorhandene
Geburtsdaten bleiben unverändert und werden nicht dupliziert. Die Migration
ist additiv und wiederaufnehmbar; nach Datennutzung bleiben Spalten und
Nachweistabelle auch bei einem Code-Rollback bestehen.

## Posteingang und Dokumente

Der erste Eingangs-Durchstich verwendet Nextcloud `IAppData` als private,
nicht teilbare Importablage für validierte PDF-Originale. Der Pfad
`mail-inbox/message-{id}/{sha256}.pdf` wird ausschließlich serverseitig aus
persistierter Nachrichten-ID und Inhaltsfingerprint erzeugt; Originalnamen
werden nur als Metadatum gespeichert und nie zu einem Dateipfad. Vor der
ersten Mutation werden MIME-Typ, PDF-Signatur, Anzahl, Einzel- und Gesamtgröße
validiert. Ein identischer Import wird über externe Message-ID oder
Inhaltsfingerprint erkannt und schreibt weder Datenbank noch Datei erneut.

AppData ist hier die unveränderliche interne Eingangsquelle, nicht die
nutzergesteuerte, teilbare Hauptakte. Der geschützte Dokumentendpunkt nimmt
keinen Dateipfad entgegen, sondern löst eine numerische Anhangs-ID über die
Datenbank auf. Bei unzugeordneten Nachrichten verlangt er die globale
Posteingangsberechtigung, nach Zuordnung das Leserecht auf genau diese
Bewerbungsakte. Erst danach wird der ausschließlich serverseitig erzeugte
Pfad gelesen, der MIME-Typ erneut begrenzt und der Inhalt gegen den
persistierten SHA-256-Wert geprüft. Die Antwort ist `application/pdf`, inline,
privat, nicht cachebar, mit `nosniff` und einer Sandbox-CSP.

Die Oberfläche rendert PDFs über die app-lokal gebündelte und gepinnte
PDF.js-Version 6.2.108 in einer modalen Lightbox. Es gibt keine Laufzeitabfrage
an ein CDN und keine Abhängigkeit von internen Assets der Nextcloud-
PDF-Viewer-App. Canvas und Textschicht erlauben Textauswahl; eine eigene
Overlay-Schicht erfasst grafische Bereiche. Eine manuelle Seiten- und
Quelltexteingabe bleibt als tastaturbedienbarer Alternativweg verfügbar.

Eine Feldverknüpfung friert Anhang, Bewerbung, Seite, normierte Rechtecke,
Quelltext, Zielfeld und angewandten Wert ein. Zulässige Ziele sind
Vorerfahrung, Deutschniveau, Geburtsdatum, Geburtsort und freier
Bewerbungskommentar. Anwendung und Herkunftsnachweis werden in einer
Datenbanktransaktion geschrieben. Strukturierte vorhandene Werte verlangen
eine ausdrückliche Ersatzbestätigung; der freie Kommentar wird serverseitig
mit Zeilenumbruch an den unveränderten Bestand angehängt. Optimistische
Versionen verhindern den Ersatz zwischenzeitlich geänderter Werte, und ein
Client-Schlüssel macht Wiederholungen idempotent.

Lesen folgt dem Dokumentenscope. Schreiben verlangt zusätzlich
`manage_documents` sowie für Bewerbungsfelder `edit_applications` oder für
Geburtsdaten `edit_hiring_data`. Unzugeordnete Dokumente können betrachtet,
aber nicht mit einem Datensatz verknüpft werden. Die frühere Tabelle für freie
und spaltenbezogene Dokumentnotizen bleibt aus Rückwärtskompatibilität
erhalten; die aktuelle Oberfläche erzeugt daraus keine neuen Einträge.
Weitere
Aktenunterlagen, Shares, Virenprüfung, Export, Backup-/Restore-Abnahme und
Aufbewahrung bleiben Folgeentscheidungen.

## Statusmail-Entwürfe und Versand

Eine aktivierte Regel je zulässiger Statuskante verweist auf genau eine
versionierte Mailvorlage; dieselbe Vorlage darf von beliebig vielen Regeln
referenziert werden. Alle Rückzugskanten werden beim Upgrade auf eine
gemeinsame Vorlage konsolidiert. Der Statusservice löst die aktuelle Revision und die
kanonische Personenadresse vor der Mutation auf und speichert Statuswechsel,
Audit und den vollständig gerenderten Entwurf in einer Transaktion. Der
Client-Schlüssel verhindert doppelte Entwürfe bei Requestwiederholung; spätere
Vorlagenänderungen verändern vorhandene Snapshots nicht.

Reguläre Statuskanten können den optionalen Kurzfragebogen überspringen. Ein
darüber hinausgehender Prozesssprung verlangt neben `edit_applications` die
nicht delegierbare Fähigkeit `override_status_transitions`, die ausschließlich
Personalreferat und Nextcloud-Administration erhalten. Der Server prüft diese
Grenze unabhängig von der bestätigenden Dropdown-Oberfläche und schreibt das
Auditereignis `status_transition_overridden` in derselben Transaktion. Die
fachlichen Schutzgrenzen für BQ, Einstellungsfreigabe, gültigen Bürobereich,
bekannte Stati und optimistische Versionen sind nicht übersteuerbar.

Die Versandfreigabe ist eine zweite, CSRF-geschützte und an `communicate` plus
Bewerbungsscope gebundene Aktion. Sie friert Betreff, Text, bewusst
freigegebene Zieladresse, Testmodus und Zeitpunkt ein. Eine zentrale
AppConfig-Einstellung kann ausschließlich durch Nextcloud-Admins geändert
werden und ersetzt serverseitig den Zustellempfänger durch eine validierte
Testadresse. Ursprüngliche, freigegebene und tatsächliche Adresse bleiben
getrennt; Mailinhalte werden nicht in technische Logs geschrieben.

Ein registrierter `TimedJob` beansprucht fällige Outbox-Einträge atomar,
versendet über das öffentliche Nextcloud-`IMailer`-Interface und markiert erst
danach Entwurf und Auftrag als versendet. Fehler führen zu begrenztem
exponentiellem Retry; nach 15 Minuten werden verwaiste Worker-Claims wieder
freigegeben. Die eindeutigen Entwurfs- und Auftragskennungen verhindern interne
Doppelaufträge. Weil Mailtransport und Datenbank keine gemeinsame Transaktion
besitzen, bleibt bei einem Prozessabbruch genau zwischen externem SMTP-Erfolg
und lokaler Quittierung das allgemeine At-least-once-Risiko eines erneuten
Transportversuchs bestehen.

Die additive Migration 11 ergänzt an Vorlagenrevisionen und Entwürfen einen
Formatsnapshot. Vorhandene Datensätze bleiben ausdrücklich `plain`; nur neu
gespeicherte Rich-Text-Inhalte tragen `html`. Außerdem ergänzt sie für jede
noch unbelegte Kante der kanonischen Statusmatrix genau eine neutrale,
deaktivierte Standardregel samt eigener Vorlage. Bereits vorhandene Regeln
werden weder umgehängt noch überschrieben; ein abgebrochener Lauf ist pro
Statuskante fortsetzbar.

Migration 13 konsolidiert ausschließlich die Rückzugskanten auf die älteste
dort bereits verwendete Vorlage. Migration 14 ergänzt die Stellen additiv um
Tarif- und Vertragsfakten; bestehende Einstellungsdaten werden dabei weder
kopiert noch umgeschrieben.

HTML wird vor Revision, Entwurfsspeicherung, Freigabe und Transport auf
`p`, `br`, `strong`, `em`, `ul`, `ol`, `li` und `a` begrenzt. Links erlauben
nur `https`, `http` und `mailto`; aktive Inhalte, Styles und Eventattribute
werden entfernt. Der Transport erhält den bereinigten HTML-Body und eine
daraus erzeugte Klartextalternative. Legacy-Klartext wird vor einer
Rich-Text-Bearbeitung escaped und niemals als historisches HTML interpretiert.
Auch aufgelöste Werte aus Personen- und Stellendaten werden kontextgerecht als
Text escaped, damit sie keine erlaubten HTML-Elemente oder Linkziele
einschleusen können.

## Spätere Integrationen

Ein IMAP-Adapter darf nur öffentliche Protokollschnittstellen verwenden und
keine Tabellen anderer Apps lesen. Ein späterer Adapter übergibt normalisierte
Nachrichten an den vorhandenen `MailInboxService`; Message-ID,
Inhaltsfingerprint und persistente Fehlerzustände sind bereits Teil dieser
Grenze. Zugangsdaten, Hintergrundabruf, Retry und Postfachadministration sind
noch nicht umgesetzt. Der Statuswechsel-Versand nutzt bereits die oben
beschriebene Outbox; Antwortzuordnung und weitere Nachrichtentypen bleiben
getrennte Folgepakete.

Die `ApplicationMailFieldExtractor`-Komponente arbeitet rein auf bereits
empfangenem Mailtext und normalisiertem Text textbasierter Lebensläufe und
schreibt weder Daten noch Dateien. Der validierende `MailInboxService` liest
PDF-Text zuvor lokal und ohne Netzwerkzugriff bevorzugt über Poppler
`pdftotext`, ersatzweise über Ghostscript `txtwrite`; Eingabe, Ausgabe,
Größenlimit, 15-Sekunden-Grenze und temporäre Dateien bleiben innerhalb der
Importgrenze. Ein Fehlschlag blockiert den unveränderten Mailimport nicht,
liefert aber keine PDF-basierten Vorschläge. Der Feldextraktor
liefert ausschließlich quellmarkierte Vorschläge aus bekannten Feldlabels,
einer eindeutigen unbeschrifteten Body-Adresse oder als Rückfall aus der
normalisierten Absenderadresse. Namen verwenden eine konservative Kette aus
Selbstvorstellung, Grußsignatur, Absender-Anzeigename und eindeutig
segmentierter persönlicher Adresse; Rollen- und Zahlenmuster sind gesperrt.
Die Formular-Berufsrichtung ist eine
technische Kategorie, keine Stelle und keine Berechtigung. PDF-Anhänge und die
Originalnachricht werden nicht vom Feldextraktor, sondern von der
übergeordneten, validierenden Inbox-Grenze verarbeitet. Die admin-geschützte
AppConfig-Auswahl kennt aktuell ausschließlich die regelbasierte Verarbeitung.
Ein lokales Server-Modell ist als nicht aktivierbare Zukunftsoption sichtbar;
seine spätere Aktivierung setzt einen installierten, ausschließlich lokalen
Provider sowie eigene Datenschutz-, Qualitäts-, Ressourcen- und
Fehlergrenzentests voraus. Die bestätigte Mailzuordnung übernimmt nur die
dabei einzeln ausgewählten und gegebenenfalls korrigierten Werte aus einem
eng erlaubten Satz normalisierter Vorschläge transaktional in leere
Vertragsfelder. Nicht ausgewählte, unbekannte, nicht tatsächlich erkannte
oder ungültig korrigierte Werte werden nicht übernommen; manipulierte
Requests scheitern vor jeder Mutation. Die Nachrichtenzuordnung und
Vorbelegung schlagen gemeinsam fehl; protokolliert werden nur die betroffenen
Feldnamen, keine Werte.
