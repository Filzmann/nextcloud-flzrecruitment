# Changelog

## Unreleased

- Einen app-eigenen Processing-Metadata-Katalog für Bewerbungsakte,
  Interviews/BQ, Posteingang/Dokumente, Einstellungsfreigabe, Statusmail,
  Bewerberpool und temporäre Adminfreigaben über den V1-Vertrag des
  Datenschutz-Centers veröffentlicht.
- Nextcloud 33.0.7 bis 34.0.2 durch Fresh Install und Upgrade 33→34 mit
  App-Suiten, DI-/Registrierungs-, API-, Rechte-, HTTPS-, Asset- und UI-Smokes unterstützt.
- Optionale Poppler-/Ghostscript-Textextraktion von festen Hostpfaden gelöst
  und an die tatsächliche `PATH`-Fähigkeit des PHP-Prozesses gebunden.
- Erfolgs-, Ausfall-, Timeout-, Größenlimit- und Temp-Datei-Grenzen sowie den
  unveränderten Mail-/Originalimport ohne PDF-Engine reproduzierbar geprüft.
- Dokumentations- und Steuerungsstruktur vereinheitlicht; abgeschlossene
  Umsetzungspakete aus der Roadmap entfernt.

## 0.11.0-rc.19

- Person und Bewerbung direkt aus einer neuen oder unklaren Eingangsnachricht
  für eine aktive Stelle kontrolliert anlegbar gemacht; Personendaten bleiben
  vor dem Speichern korrigierbar und weitere Vorschläge einzeln bestätigbar.
- Person, Bewerbung, leere bestätigte Zielfelder, Nachrichtenzuordnung und
  Audit in einer Transaktion zusammengeführt; fremde Stellen, unzulässige
  Zustände und veraltete Versionen bleiben ohne Teilobjekte.
- Den neuen CSRF-geschützten Schreibpfad serverseitig gemeinsam durch globale
  Posteingangsberechtigung und `edit_applications` begrenzt.

## 0.11.0-rc.18

- Wunschstunden-Vorschläge beim Zuordnen als korrigierbaren Einzelwert oder
  Von-bis-Bereich auswählbar gemacht und ausschließlich in eine noch leere
  Bewerbungsangabe übernommen.
- Mailübernahme und Bewerbungsanlage auf dieselbe Fachregel für Werte größer
  als null bis höchstens 80 Stunden, geordnete Bereiche und die
  Einzelwertnormalisierung gleicher Grenzen zusammengeführt.

## 0.11.0-rc.17

- Vorerfahrung und Deutschniveau aus Eingangsnachrichten ebenfalls einzeln
  auswählbar, korrigierbar und ausschließlich in leere Bewerbungsfelder
  übernehmbar gemacht.
- Die gemeinsame Wertgrenze für Mail- und PDF-Übernahmen normalisiert
  Deutschniveaus auf A1 bis C2, `native` oder `not_assessed` und weist
  ungültige Werte vor der atomaren Zuordnung ab.

## 0.11.0-rc.16

- Beim Zuordnen einer Eingangsnachricht Kontakt-, Wohnort- und
  Eintrittsvorschläge einzeln auswählbar und vor der Übernahme korrigierbar
  gemacht; nicht markierte Werte werden verworfen.
- Bestätigte Vorschläge serverseitig auf tatsächlich erkannte und für die
  Vertragsvorbereitung freigegebene Felder begrenzt; ungültige oder
  manipulierte Werte werden ohne Zuordnungs- oder Datenmutation abgewiesen.

## 0.11.0-rc.15

- Bewerberpool-Grundkonfiguration wieder in das Recruitment-Modul eingeordnet
  und für Personalreferat sowie Nextcloud-Admins über die nicht delegierbare
  Fähigkeit `manage_candidate_pool` freigegeben.
- Bewerberpool aus dem nativen Nextcloud-Adminabschnitt entfernt; dort bleiben
  ausschließlich Mail-Testmodus, technische Lebenslaufextraktion und
  strukturelle Erstbegleitungsgruppe.
- Lokale Verwaltung der Basisqualifikationen sichtbar als Übergangslösung bis
  zur Anbindung einer eigenständigen BQ-Planer-App gekennzeichnet.

## 0.11.0-rc.14

- Systemweite Grundkonfiguration für Mail-Testmodus, Bewerberpool,
  Lebenslaufextraktion und Erstbegleitungsgruppe in einen eigenen nativen
  Nextcloud-Adminabschnitt „AD Recruitment“ verschoben.
- Den operativen Recruitment-Einstellungsbereich auf die durch das
  Personalreferat pflegbaren Interviewfragen, Mailvorlagen und delegierten
  Berechtigungen reduziert; die bestehenden serverseitigen Admin-Grenzen
  bleiben unverändert.

## 0.11.0-rc.13

- Sichtbare E-Mail-Links öffnen durchgängig in einem neuen Browser-Tab und
  trennen den neuen Kontext mit `noopener noreferrer` vom Recruitment-Fenster.
- Reguläre Statuswege zum Überspringen des Kurzfragebogens ergänzt; die neuen
  Kanten erhalten beim Upgrade deaktivierte Mailregeln mit wiederverwendeten
  Vorlagen.
- Personalreferat und Nextcloud-Administration können im Status-Dropdown nach
  ausdrücklicher Sicherheitsabfrage vom Regelprozess abweichen. Die nicht
  delegierbare Ausnahmeberechtigung wird serverseitig geprüft und der
  abweichende Übergang auditiert; Einstellungsfreigabe und BQ-Schutz bleiben
  verbindlich.

## 0.11.0-rc.12

- Textbasierte PDF-Lebensläufe beim Mailimport lokal und ohne KI über
  `pdftotext` beziehungsweise Ghostscript ausgelesen und konservativ nach
  Standardfeldern durchsucht.
- Datenextraktion als admin-geschützte Einstellung mit sichtbarem
  Laufzeitwerkzeug ergänzt; ein künftiges lokales Server-Modell wird bereits
  als noch nicht verfügbare Alternative ausgewiesen und kann nicht aktiviert
  werden.
- Stichwortvarianten für Name, Telefon, Wohnort, Wunschstunden,
  Verfügbarkeit, Berufserfahrung und Deutschniveau mit getrenntem
  Herkunftsnachweis ergänzt.

## 0.11.0-rc.11

- Sichtbare Bewerber-, Vertrags- und Absender-E-Mail-Adressen als sichere
  `mailto:`-Links sowie Telefonnummern als normalisierte `tel:`-Links für
  AGFEO Dashboard und andere registrierte Telefonsoftware ausgegeben.
- Steuerzeichen in Mailadressen, unplausible Telefonnummern und nicht
  unterstützte Zeichenfolgen von der Protokollverlinkung ausgeschlossen.

## 0.11.0-rc.10

- Fehlende Namensvorschläge in bereits importierten Nachrichten idempotent
  mit derselben konservativen Erkennung nachgezogen; vorhandene Namen bleiben
  unverändert und Nachrichtenrevisionen werden aktualisiert.

## 0.11.0-rc.9

- Fehlende Namen kontrolliert aus Selbstvorstellungen, Grußsignaturen,
  Absender-Anzeigenamen oder eindeutig personenbezogenen Adressbestandteilen
  abgeleitet.
- Rollenadressen, Ziffern, Einzelbegriffe und typische Teambezeichnungen von
  der Namensableitung ausgeschlossen.

## 0.11.0-rc.8

- Alle vier Demo-Bewerbungen idempotent mit den Vertragsfeldern vorbelegt,
  die bei einer Mailzuordnung übernommen werden: Anrede, Titel soweit
  vorhanden, private E-Mail, Telefon, Eintrittsdatum und Ort.

## 0.11.0-rc.7

- Anrede, Titel, private Kontaktadresse, Telefonnummer, Eintrittsdatum und Ort
  beim Zuordnen einer Eingangsmail in bislang leere Vertragsfelder übernommen;
  bestehende Werte werden nicht überschrieben.
- Vertrags- und LoBu-Bereiche auf eine kompakte Leseansicht mit explizitem
  Bearbeitungsmodus umgestellt.
- Anrede, Titel, Versicherungsart und Steuerklasse als validierte Auswahlen
  sowie übrige Eingaben mit inhaltsgerechten Typen und Längen umgesetzt.

## 0.11.0-rc.6

- Alle Übergänge nach `withdrawn` auf eine gemeinsame, wiederverwendbare
  Mailvorlage konsolidiert und die Regelpflege um eine Vorlagenauswahl ergänzt.
- `Zurückgezogen` aus dem Kartenboard entfernt und als rote Aktion mit
  Bestätigungsabfrage umgesetzt.
- Vertragserstellung und sensible LoBu-Daten serverseitig ab
  `approved_for_hire` getrennt; Personalreferat und BQ erhalten keinen
  vorzeitigen Zugriff.
- Vertragsdauer, Entgeltgruppe, Stunden, Urlaub und Arbeitsort an der Stelle
  verankert; KAPOVAZ für Assistenz und Festgehalt für alle übrigen
  Berufsgruppen abgeleitet. Freies Gehalt und Währung aus der Oberfläche
  entfernt.
- Mail- und normalisierten Lebenslauftext gemeinsam in quellmarkierte
  Feldvorschläge für Wunschstunden, Verfügbarkeit, Erfahrung, Deutschniveau
  und Wohnort überführt.

## 0.11.0-rc.5

- Datenschutzgerechtes Bewerberpool-Paket mit dokumentierter Einwilligung, Widerruf, Fristablauf, interner Wiedervorlage und transparenten Stellenhinweisen ergänzt.
- Demo-Antwort-Bubbles auf den Inhalt der jeweiligen Interviewfrage zugeschnitten.

## 0.11.0-rc.4

- Für alle 35 erlaubten Statusübergänge eigene neutrale Standardvorlagen und
  standardmäßig deaktivierte Versandregeln additiv vorbereitet.
- Mailverwaltung nach Ausgangsstatus und konkreter Statuskante gruppiert;
  Aktivierung, Standardplanung und Vorlagenrevision sind direkt am Übergang
  erreichbar.
- Kleinen tastaturbedienbaren Rich-Text-Editor für Vorlagen und letzte
  Entwurfskorrekturen ergänzt.
- HTML-Mails serverseitig auf einen engen Formatwortschatz begrenzt und für
  jeden Versand automatisch um eine Klartextalternative ergänzt.
- Bestehende Klartextvorlagen und -entwürfe durch additive Formatsnapshots
  ohne inhaltliches Überschreiben kompatibel gehalten.
- Alle 23 neutralisierten Demo-Interviewfragen mit je drei aktiven,
  idempotent ergänzten Antwort-Bubbles ausgestattet.
- Interviewfragen, Mailvorlagen und Berechtigungen aus der täglichen
  Hauptnavigation in ein capability-gebundenes Einstellungsmenü verschoben.

## 0.11.0-rc.3

- Stellenanlage auf Berufsgruppe, fachlich passende AD-Organisationsgruppen
  und eine darin begrenzte Nextcloud-Benutzersuche vereinfacht.
- Verantwortliche Personen serverseitig auf Mitglieder der ausgewählten,
  für die Berufsgruppe zulässigen Gruppen beschränkt.
- Basisqualifikation als abgeleitete Pflicht für jede Assistenz-Stelle
  festgelegt und für alle anderen Berufsgruppen ausgeschlossen; die freie
  BQ-Kennzeichnung in Oberfläche und Service entfällt.

## 0.11.0-rc.2

- Kartenansicht als Standard des Bewerbungsarbeitsplatzes festgelegt.
- Versionierte Mailvorlagen, Textblöcke und aktivierbare Regeln je zulässigem
  Statusübergang für Personalreferent*innen ergänzt.
- Bearbeitbare Entwürfe atomar mit Statuswechseln erzeugt und Betreff, Text
  sowie Zieladresse bis zur ausdrücklichen Freigabe korrigierbar gemacht.
- Sofortversand, frei terminierte Zustellung und Planung für den kommenden
  Montag über eine eindeutige Outbox mit kontrollierten Wiederholungen ergänzt.
- Zentralen administrativen Testmodus mit serverseitiger Umleitung an eine
  validierte Standardadresse und getrennten Empfänger-Snapshots ergänzt.
- Neue Kommunikationsdaten in den bestehenden Datenschutz-Provider aufgenommen
  und PHP-Tests vollständig auf den zentralen App-Autoloader umgestellt.
- Versandjob bei Upgrades bestehender Installationen zusätzlich idempotent über
  Nextclouds öffentliche Jobliste registriert.

## 0.10.0-rc.1

- Subjectgebundene persönliche Datenauskunft für sämtliche internen Nextcloud-UID-Bezüge in Recruiting-Aktivitäten ergänzt.
- Bewerberakten ohne sicher authentifizierte Zuordnung bewusst ausgeschlossen; eine bloße E-Mail-Übereinstimmung wird nicht als Identitätsnachweis verwendet.
- PHP-Tests auf einen zentralen app-lokalen Autoload-Bootstrap umgestellt.

## 0.9.0

- Eingebettete Browser-PDF-Vorschau durch eine große, tastaturbedienbare
  Lightbox mit app-lokal gebündeltem PDF.js 6.2.108 ersetzt.
- Textauswahl, grafische Bereichsmarkierung und manuelle Fundstellenerfassung
  mit Seite, normierten Rechtecken und Quelltext ergänzt.
- PDF-Fundstellen kontrolliert mit Vorerfahrung, Deutschniveau, Geburtsdatum,
  Geburtsort oder dem freien Bewerbungskommentar verknüpfbar gemacht.
- Bestehende strukturierte Werte gegen stilles Überschreiben geschützt und
  Inhalte des freien Kommentars verlustfrei angehängt.
- Feldänderung und unveränderlichen Herkunftsnachweis atomar, versioniert und
  bei Requestwiederholung idempotent persistiert.
- Feldverknüpfungen zusätzlich zu Bewerbungsscope und `manage_documents` mit
  dem jeweiligen Bewerbungs- oder Vertragsstammdatenrecht abgesichert.

## 0.8.0

- Geschützte Inline-Vorschau validierter PDF-Bewerbungsunterlagen innerhalb
  des jeweiligen Posteingangs- oder Bewerbungsscope ergänzt.
- PDF-Inhalt vor jeder Ausgabe gegen den beim Import gespeicherten SHA-256-Wert
  geprüft und mit privaten, nicht cachebaren Antwortheadern ausgeliefert.
- Append-only Dokumentkommentare als freie Notiz oder mit Seiten- und
  Spaltenanker ergänzt; Original-PDFs bleiben unverändert.
- Kommentarzugriff serverseitig nach Bewerbungsakte und eigener
  `manage_documents`-Fähigkeit getrennt; reine Leseberechtigte können keine
  Kommentare schreiben.
- Wiederholte Kommentarrequests über einen Client-Schlüssel idempotent
  gemacht und ungültige Dokumentpfade, Seiten und Spalten abgewiesen.
- Bearbeitbare, vorlagenbasierte Mailentwürfe bei Statuswechseln einschließlich
  Textblöcken und Freitext als nächstes Kommunikationspaket präzisiert.

## 0.7.0

- App-privaten Bewerbungsposteingang mit unveränderlichen Original-Mailtexten, PDF-Anhangsmetadaten, stabilen Importzuständen und Zuordnungsaudit ergänzt.
- Nachrichten anhand externer Message-ID und Inhaltsfingerprint wiederholbar importierbar gemacht; identische Importe erzeugen weder zweite Nachricht noch zweite Datei.
- PDF-Anhänge vor jeder Mutation auf Typ, Signatur, Anzahl und Größe geprüft und unter ausschließlich serverseitig erzeugten Hashpfaden in Nextcloud-AppData gespeichert.
- Unzugeordneten Eingang auf Personalreferat, Nextcloud-Administration und globale Vertretungen mit `edit_applications` begrenzt; bereichs- oder fallgebundene Vertretungen erhalten erst nach Zuordnung ihren jeweiligen Bewerbungsscope.
- CSRF-geschützte Zuordnungs- und Ignorieraktionen sowie scoped lesbare Eingangsnachrichten in der Bewerbungsakte ergänzt.
- Wiederholbaren, zugangsdatenfreien `adrecruitment:inbox:seed`-Befehl mit zwei synthetischen Mails und PDFs ergänzt.
- Reale Mailbox-Anbindung, Hintergrundabruf, PDF-Abruf/Vorschau und Aufbewahrungsautomatik bleiben getrennte Folgepakete.

## 0.6.1

- Unverbindliche Wunschwochenstunden können neben einem einzelnen Circa-Wert auch als validierter Von-bis-Bereich erfasst und angezeigt werden.
- Die Tabellenansicht bietet den Statuswechsel nun unmittelbar und tastaturbedienbar über denselben serverseitig kontrollierten Übergangspfad wie die Kartenansicht an.
- Der Bewerbungsarbeitsplatz filtert zusätzlich nach Bürobereich, Zuständigkeit und inklusivem Eingangszeitraum und sortiert ohne Datenmutation nach Eingang, Person, Stelle oder Prozessreihenfolge.

## 0.6.0

- Unverbindliche gewünschte Wochenstunden als eigene Bewerbungsangabe ergänzt und von den verbindlichen Vertragsstunden getrennt.
- Stellen um stabile Berufsgruppen ergänzt; Basisqualifikation und KAPOVAZ serverseitig auf Assistenz begrenzt.
- Vertragsbereich mit den Beschäftigungsformen geringfügig, sozialversicherungspflichtig, studentisch und sonstige sowie Vertragsdauer, Arbeitszeitmodell, Entgeltgruppe und Tarifstufe strukturiert.
- Familienstand aus aktiver Erfassung und Projektion entfernt; vorhandene Altdaten werden beim Lesen ausgeblendet und nicht destruktiv gelöscht.
- Kartenansicht um serverseitig kontrolliertes Drag-and-drop zwischen zulässigen Statusspalten sowie eine gleichwertige Tastaturaktion erweitert.
- Bereitgestellten Haustarifvertrag als fachliche Vertragsgrundlage dokumentiert, ohne zeitabhängige Entgeltwerte automatisch fortzuschreiben.

## 0.5.0

- Wiederholbaren `adrecruitment:demo:seed`-Befehl mit synthetischer Assistenz-Stelle, E-Mail-Bewerbungen, BQ-Fall, Vertragsstammdaten und Interviewvorlage ergänzt.
- Anlageformulare über tastaturbedienbare Dialog-Overlays erreichbar gemacht; manuelle Bewerber*innen- und Bewerbungsanlage als Ausnahme zum späteren E-Mail-Eingang gekennzeichnet.
- Ersten Bewerbungsarbeitsplatz mit gleichwertiger Tabellen- und Kartenansicht sowie Suche, Stellen- und Statusfiltern ergänzt.
- Technische Eingangskanäle in der Oberfläche fachlich verständlich als E-Mail-Eingang, manuelle Ausnahme, Empfehlung oder sonstiger Kanal bezeichnet.
- Datenschutzarmen Mailfeld-Extraktor für beschriftete Websiteformular-Zeilen und eindeutige Kontaktadressen in freien Klartextmails ergänzt; Berufsrichtungen bleiben Zuordnungsvorschläge und PDF-Anhänge bewusst außerhalb dieses vorbereitenden Bausteins.
- Anlage-Overlays gegen kollidierende Nextcloud-Dialogstile abgesichert, sodass insbesondere „Bewerbung manuell anlegen“ geschlossen startet und zuverlässig geschlossen werden kann.

## 0.4.0

- Ersten manuellen Basisqualifikationsprozess für gekennzeichnete Assistenz-Stellen ergänzt.
- BQ-Durchläufe mit Zeitraum und sichtbarer Bezeichnung `BQ MM/YY` sowie versionierte Zuordnungen und einfache Ergebnisse eingeführt.
- Lohnzugriff ab BQ-Zuordnung auf die Vertragsstammdaten begrenzt und bei Abbruch, Nichtteilnahme oder fehlender Eignung sofort beendet.
- BQ-Verwaltung ausschließlich für Personalreferat und Nextcloud-Administration sowie Einstellungsfreigabe erst nach dem Ergebnis „geeignet“ serverseitig abgesichert.

## 0.3.0

- Gemeinsames LocalBase-Organisationsmodell mit getrennten Rollen für Finanzen und Lohn angebunden.
- Personalreferat, Lohn, granulare Vertretungen und bereichsgebundene Erstbegleitungen serverseitig abgesichert.
- Einstellungsfreigabe mit verpflichtendem Bürobereich sowie manuell beendbarer Erstbegleitungsfreigabe ergänzt.
- Datensparsame Vertragsvorbereitung mit validierten Einstellungsstammdaten und eigenem Berechtigungsaudit ergänzt.

## 0.2.3

- AD Recruitment in den versionierten AD-Produktkatalog aufgenommen.
- Katalogisierte Standalone-Navigation und gemeinsamen OrgSuite-Menühost ergänzt.
- Aufnahme in das vollständige AD-Suite-Archiv und ein eigenes Produktpaket vorbereitet.

## 0.2.2

- App-ID konsistent auf `adrecruitment` umgestellt, ohne PHP-Namespace oder Tabellenpräfix zu verändern.
- Ersten Stellen-, Personen-, Bewerbungs- und Interviewprozess abgesichert.
