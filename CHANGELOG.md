# Changelog

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
