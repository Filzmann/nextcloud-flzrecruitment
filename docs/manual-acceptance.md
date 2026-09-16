# Manuelles Abnahmeformular – AD Recruitment

Dieses Formular dokumentiert die fachliche, visuelle, sicherheits- und
datenschutzbezogene Abnahme des aktuellen Recruitment-Durchstichs auf einem
realitätsnahen Staging-System. Pro Prüffall wird genau ein Ergebnis markiert
und unter „Warum/Beleg/Abweichung“ knapp festgehalten, was beobachtet wurde.

Keine Bewerbungs-Echtdaten, Interviewinhalte, Dokumentnamen, E-Mail-Inhalte,
Zugangsdaten oder internen Kennungen eintragen. Ausschließlich neutrale
Testkonten und vollständig synthetische Personen-, Stellen- und Interviewdaten
verwenden.

## Kopfdaten

| Feld | Eintrag |
|---|---|
| Datum und Uhrzeit | |
| Prüfer*in | |
| Umgebung und URL | |
| AD-Recruitment-Version | |
| Nextcloud-Version | |
| Browser und Version | |
| Fenstergröße / Zoom | |
| Neutrale Testkonten und Berechtigungsgruppen | |
| Synthetischer Bewerbungsfall | |

Ergebniskennzeichnung: `[ ] erfolgreich` / `[ ] nicht erfolgreich` /
`[ ] nicht geprüft`. Bei „nicht erfolgreich“ oder „nicht geprüft“ ist eine
Begründung verpflichtend.

## A. Zugriff, Tabs und Bedienbarkeit

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| A1 | Personalreferat | Mit einem Konto aus der LocalBase-Rolle `staff_hr` die App öffnen. | Alle operativen und administrativen Recruitment-Fähigkeiten sind verfügbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A2 | Konto ohne Fachrolle | App und direkten Bootstrap-Aufruf mit einem Konto ohne passende LocalBase-Rolle oder Vertretungsfreigabe versuchen. | Der Zugriff wird serverseitig verweigert und es werden keine Bewerbungsdaten geliefert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A3 | Nextcloud-Admin | App mit einem Nextcloud-Admin öffnen. | Der Admin erhält die vorgesehenen Fähigkeiten unabhängig von zusätzlichen Recruitment-Gruppen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A4 | Tabnavigation | Zwischen Bewerbungen, Stellen und Einstellungen mit Maus sowie Pfeiltasten wechseln; unter Einstellungen ebenso zwischen Interviewfragen, Mailvorlagen, Bewerberpool und Berechtigungen wechseln. | Aktiver Haupt- und Untertab, `aria-selected`, Fokus und zugehöriger Tabpanel bleiben synchron. Nicht berechtigte Einstellungsbereiche erscheinen nicht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A5 | Tastatur und Fokus | Formulare, Tabellen, Detailansicht, Vorlageneditor und Interview ausschließlich mit Tastatur bedienen. | Alle Funktionen sind erreichbar, Fokus ist sichtbar und es gibt keine Tastaturfalle. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A6 | Responsivität und Scrollen | Viele synthetische Einträge bei kleinem Fenster und vergrößertem Zoom anzeigen. | App-Root und Tabellenwrapper halten Inhalte erreichbar; es entsteht kein unkontrolliertes globales Seitenscrolling. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A7 | Nativer Adminabschnitt | Als Nextcloud-Admin `Einstellungen → Verwaltung → AD Recruitment` öffnen und danach denselben Pfad als gewöhnliches PersRef-Konto versuchen. | Admins sehen Mail-Testmodus, Datenextraktion und Erstbegleitungsgruppe in drei kompakten Formularen. Der Bewerberpool bleibt im Recruitment-Modul; Nicht-Admins erhalten keinen Adminabschnitt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## B. Stellen, Personen und Bewerbungen

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| B1 | Stelle anlegen | Als Personalreferent*in eine synthetische Stelle mit interner und öffentlicher Bezeichnung sowie neutraler Zuständigkeit anlegen. | Die Stelle erscheint einmal mit den gespeicherten Angaben und bleibt nach Neuladen erhalten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B2 | Katalogrecht | B1 mit einer Vertretung ohne `manage_catalog` über UI und direkten Request versuchen. | Nur die ausdrücklich vergebene Fähigkeit erlaubt die Katalogaktion; abgewiesene Requests verändern nichts. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B3 | Person getrennt von Bewerbung | Als Editor eine synthetische Person anlegen und danach zwei Bewerbungen derselben Person auf unterschiedliche Stellen erstellen. | Eine Person bleibt eine Entität; beide Bewerbungen referenzieren sie getrennt und besitzen eigenen Status. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B4 | Vollständige Bewerbungszuordnung | Bewerbung mit Person, aktiver Stelle, Eingangskanal, Eingangsdatum und neutraler Zuständigkeit anlegen. | Der Datensatz erscheint mit genau der gewählten Person und Stelle in Liste und Detail. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B5 | Ungültige Referenz und Eingabe | Fehlende Person, deaktivierte beziehungsweise ungültige Stelle, ungültiges Datum und leere Pflichtfelder versuchen. | Jeder Versuch wird verständlich abgewiesen; es entsteht keine halbe Person-Bewerbungs-Beziehung. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B6 | Bearbeitungsscope | Personen- und Bewerbungsanlage mit globaler sowie bereichs- und bewerbungsgebundener Vertretung vergleichen. | Schreibrechte folgen Fähigkeit und serverseitigem Scope; sichtbare UI allein erteilt kein Recht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B7 | Anlage-Overlays | Die Buttons „Bewerber*in neu“, „Bewerbung neu“ und „Stelle neu“ per Maus und Tastatur öffnen, mit Escape beziehungsweise „Schließen“ beenden und erneut öffnen. | Jedes Formular erscheint als beschrifteter modaler Dialog; Fokus gelangt hinein und nach dem Schließen zum auslösenden Button zurück. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B8 | Manueller Ausnahmeweg | Den Bewerbungsbereich und die beiden manuellen Anlage-Overlays lesen. | Die Oberfläche erklärt verständlich, dass Bewerber*innen und Bewerbungen regulär aus E-Mails entstehen und die manuelle Anlage nur Ausnahmen beziehungsweise Empfehlungen abbildet. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B9 | Tabelle und Karten | Zwischen Tabelle und Karten wechseln und dieselbe Filterkombination aus Suchtext, Stelle, Status, Bürobereich, Zuständigkeit und Eingangszeitraum sowie verschiedene Sortierungen verwenden. | Beide Darstellungen enthalten in identischer Reihenfolge exakt dieselben berechtigten Bewerbungen; Datumsgrenzen sind inklusive und leere Ergebnisse bleiben verständlich. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B10 | Demo-Pack | In einer freigegebenen lokalen Demo-Umgebung `php occ adrecruitment:demo:seed` zweimal ausführen. | Beide Läufe melden zwei Demo-Stellen, vier Bewerber*innen, vier Bewerbungen, einen BQ-Durchlauf und zwei Vorlagen mit insgesamt 23 neutralisierten Interviewfragen und je drei aktiven Antwort-Bubbles; der zweite Lauf erzeugt keine Duplikate und die Assistenz-Stelle wird zuerst angelegt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B11 | Wunschstunden | Je eine Bewerbung mit einem einzelnen Circa-Wert und mit einem Von-bis-Bereich anlegen; anschließend eine Obergrenze ohne Von-Wert und eine Obergrenze unter dem Von-Wert versuchen. | Einzelwert und Bereich erscheinen einheitlich in Tabelle, Karte und Bewerbungsakte; ungültige Bereiche werden ohne Anlage abgelehnt. Verbindliche Wochenstunden bleiben ein getrenntes Vertragsfeld. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B12 | Status verschieben | Je eine Bewerbung per Karten-Drag-and-drop, Karten-Tastaturaktion und direkter Tabellenaktion in einen erlaubten Folgestatus bewegen. Danach als gewöhnliche oder delegierte Bearbeitung einen nicht regulären Zielstatus beziehungsweise eine manipulierte ID versuchen. | Alle erlaubten Wege protokollieren genau einen Statuswechsel über denselben Übergangspfad; nicht reguläre oder manipulierte Wechsel werden ohne Ausnahmefähigkeit und ohne Datenänderung abgelehnt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B13 | Einstellungsfreigabe aus Karten | Eine freigabefähige Karte zunächst ohne, dann mit ausgewähltem Bürobereich nach „Zur Einstellung freigegeben“ bewegen. | Ohne Bereich erfolgt keine Änderung und eine verständliche Meldung; mit gültigem Bereich wird der serverseitig erlaubte Übergang ausgeführt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B14 | Vereinfachte Stellenanlage | Nacheinander Assistenz, Pflege, Sozialarbeit und Verwaltung wählen, die angebotenen Gruppen vergleichen und in ausgewählten Gruppen nach Personen suchen. Anschließend fremde Gruppen- und Benutzerkennungen per direktem Request senden. | Es erscheinen nur fachlich passende AD-Gruppen und nur deren Mitglieder. Manipulierte Gruppen, gruppenfremde Personen und Suchtexte unter zwei Zeichen werden serverseitig ohne Stellenanlage abgewiesen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B14 | Stellenbasierter Vertrag | Eine Assistenz- und eine andere Stelle mit Vertragsdauer, EG, ausgeschriebenen/Vollzeit-Stunden, Urlaub und Arbeitsort anlegen und die LoBu-Projektion nach Einstellungsfreigabe öffnen. | Assistenz wird als KAPOVAZ, andere Stellen als Festgehalt gezeigt; Vertragswerte stammen aus der Stelle, Berlin ist Standard, Gehalt und Währung fehlen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B15 | LoBu-Sichtbarkeit | Dieselbe Bewerbung vor und nach `approved_for_hire` als Personalreferat, BQ und LoBu öffnen und sensible Felder direkt per API schreiben. | Bank, Krankenkasse, Steuer-ID und Sozialversicherung sind vor Freigabe nicht sichtbar; nur LoBu kann sie ab Freigabe schreiben. PersRef/BQ und verfrühte LoBu-Writes werden serverseitig ohne Nebenwirkung abgewiesen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## C. Kontrollierte Bewerbungsstatus

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| C1 | Erlaubter Übergang | In einer Testbewerbung einen angebotenen nächsten Status wählen und Detailansicht neu laden. | Nur ein erlaubter Übergang wird ausgeführt; der neue Status und die neue Version sind sichtbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C2 | Verbotener Übergang | Einen nicht angebotenen Status beziehungsweise einen fachlich unzulässigen Sprung per direktem Request versuchen. | Der Server weist den Übergang ab; Status und Verlauf bleiben unverändert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C3 | Statusverlauf | Zwei erlaubte Übergänge mit neutralen Editor-Konten durchführen. | Jeder Übergang protokolliert Ausgangsstatus, Zielstatus, Zeitpunkt und ausführende Nextcloud-UID. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C4 | Fehlende Bearbeitungsfähigkeit | Statuswechsel als Lohn, Finanzen und Vertretung ohne `edit_applications` versuchen. | Alle unberechtigten Wege werden serverseitig verweigert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C5 | Paralleländerung | Dieselbe Bewerbungsdetailversion in zwei Browserfenstern öffnen, zuerst in Fenster A und danach in Fenster B ändern. | Die veraltete zweite Änderung wird als Konflikt abgewiesen und überschreibt den aktuellen Stand nicht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C6 | Kurzfragebogen überspringen | Von Vorprüfung und offenem Kurzfragebogen die jeweils angebotenen direkten Wege zu Telefon, Vorstellung, Entscheidung beziehungsweise Ablehnung verwenden. | Die definierten Sprünge sind regulär möglich, werden normal protokolliert und erzeugen bei deaktivierter neuer Mailregel keine Nachricht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C7 | Ausnahme durch Personalreferat | Als Personalreferentin im Dropdown einen als „Ausnahme“ bezeichneten Status wählen, die Abfrage zunächst abbrechen und danach bestätigen. Dasselbe als delegierte Vertretung direkt per API versuchen. | Abbrechen ändert nichts. Bestätigen führt genau einen Übergang und ein gesondertes Ausnahme-Audit aus. Die delegierte Vertretung wird serverseitig ohne Mutation abgewiesen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C8 | Nicht übersteuerbare Grenzen | Als Personalreferentin eine Bewerbung ohne Freigabe direkt auf „Eingestellt“ und eine Bewerbung mit offenem/ungeeignetem BQ auf „Zur Einstellung freigegeben“ setzen. | Beide Ziele werden nicht angeboten und auch ein direkter Ausnahme-Request wird ohne Status-, Mail- oder Auditnebenwirkung abgewiesen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## D. Interviewvorlagen und Revisionen

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| D1 | Vorlage anlegen | Mit `manage_catalog` eine synthetische Vorlage mit Typ, Zielgruppe und neutraler Beschreibung anlegen. | Die Vorlage erscheint aktiv mit eindeutiger Revision. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| D2 | Fragetypen | Pflicht- und optionale Fragen für Freitext, Ja/Nein, Einzelauswahl, Mehrfachauswahl und Bewertung ergänzen. | Alle Typen werden mit ihren Optionen korrekt gespeichert und im Interview passend dargestellt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| D3 | Neue Revision | Eine vorhandene Frage ändern und Vorlagendetail neu laden. | Die Vorlagenrevision steigt nachvollziehbar; die aktuelle Vorlage zeigt die Änderung. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| D4 | Interview-Snapshot | Aus Revision 1 ein Interview erzeugen, danach die Vorlage ändern und ein zweites Interview erzeugen. | Das erste Interview behält unverändert Revision 1; nur das zweite verwendet die neue Revision. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| D5 | Katalog-Deny | Vorlage, Frage und Bubble als Konto ohne `manage_catalog` über direkte Requests anlegen oder ändern. | Alle Requests werden abgewiesen und verändern keine Revision. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## E. Interviewentwurf, Bubbles und Abschluss

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| E1 | Entwurf speichern | Ein Interview mit synthetischen Antworten teilweise ausfüllen, als Entwurf speichern und neu laden. | Antworten und Entwurfsstatus bleiben erhalten; das Interview bleibt bearbeitbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E2 | Bubble in leeren Freitext | Einen neutralen Antwortbaustein bei leerem Freitext aktivieren. | Der vorgesehene Text wird eingefügt und bleibt anschließend frei editierbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E3 | Bubble überschreibt nicht | Eigenen Text eingeben und danach einen Bubble-Button verwenden. | Vorhandener Text bleibt erhalten; der Baustein wird verständlich ergänzt statt zu überschreiben. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E4 | Bubble nur für Freitext | Auswahl-, Ja/Nein- und Bewertungsfragen prüfen. | Für diese Fragetypen werden keine unpassenden Antwort-Bubbles angeboten oder akzeptiert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E5 | Pflichtfrage | Mindestens eine Pflichtfrage leer lassen und Interview abschließen. | Der Abschluss wird abgewiesen; der Entwurf und bereits eingegebene Antworten bleiben erhalten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E6 | Abschluss und Unveränderlichkeit | Alle Pflichtfragen synthetisch beantworten, Interview abschließen, neu laden und eine weitere Änderung versuchen. | Das Interview ist abgeschlossen, schreibgeschützt und kann nicht still verändert werden. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E7 | Paralleländerung im Entwurf | Dieselbe Entwurfsversion in zwei Fenstern ändern und nacheinander speichern. | Die veraltete zweite Speicherung wird als Konflikt abgewiesen und überschreibt keine neueren Antworten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| E8 | Interviewrecht | Entwurf und Abschluss mit einer Vertretung mit beziehungsweise ohne `interview` versuchen. | Nur Konten mit Interviewfähigkeit und passendem Bewerbungsscope dürfen schreiben; unberechtigte Requests verändern nichts. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## F. Vertragsvorbereitung, Vertretungen und Erstbegleitungen

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| F1 | Trennung Finanzen/Lohn | Dieselbe freigegebene synthetische Einstellung als `finance` und `payroll` öffnen. | Finanzen erhält keinen Recruitment-Zugriff; Lohn sieht ausschließlich Vertragsstammdaten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| F2 | Stammdatenumfang | Adresse, Geburtsdatum, Bank, Krankenkasse sowie Vertragsparameter synthetisch erfassen und als Lohn öffnen. | Alle vorgesehenen Felder sind lesbar; Interviews, Verlauf und Bewerberakte fehlen in der Lohnsicht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| F3 | Freigabe mit Bereich | Bewerbung aus `decision_pending` ohne und anschließend mit gültigem Bürobereich freigeben. | Ohne Bereich wird abgewiesen; mit Bereich werden Status, Bereich und Erstbegleitungsfreigabe gemeinsam gespeichert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| F4 | Erstbegleitungsscope | EB-Konten mit und ohne Erstbegleitungsgruppe sowie passendem und fremdem Bereich vergleichen. | Nur EB plus Zusatzgruppe plus passender Bereich liest die freigegebene Akte. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| F5 | Manuelles Ende | Erstbegleitungszugriff beenden und direkten Detailrequest wiederholen. | Der Zugriff endet sofort; eine veraltete Paralleländerung wird als Konflikt abgewiesen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| F6 | Granulare Vertretung | Fähigkeiten und globalen, Bereichs- sowie Einzelbewerbungsscope nacheinander konfigurieren. | Jeder direkte API-Aufruf bleibt exakt auf Fähigkeit und Scope begrenzt; die Vertretung kann keine Vertretungen vergeben. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## G. Basisqualifikation

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| G1 | Abgeleitete BQ-Pflicht | Je eine Assistenz- und Nicht-Assistenz-Stelle anlegen und versuchen, die Pflicht separat umzuschalten beziehungsweise per Request gegenteilig zu setzen. Danach einen neutralen Durchlauf mit Start- und Enddatum anlegen. | Jede Assistenz-Stelle zeigt automatisch die BQ-Pflicht, jede andere Berufsgruppe nie. Es gibt keinen Schalter; manipulierte Gegenwerte werden abgewiesen. Der Durchlauf erhält stabil die Bezeichnung `BQ MM/YY`. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| G2 | Zulässige Zuordnung | Eine Bewerbung auf die Assistenz-Stelle bis `decision_pending` führen und einem BQ-Durchlauf zuordnen. | Die Bewerbung zeigt den gewählten Durchlauf, bleibt eine Bewerbung und ist noch nicht zur Einstellung freigegeben. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| G3 | Unzulässige Zuordnung | Eine Bewerbung auf eine Nicht-Assistenz-Stelle und eine Bewerbung im falschen Ausgangsstatus per direktem Request zuordnen. | Beide Requests werden serverseitig abgewiesen und verändern weder Status noch BQ-Verlauf. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| G4 | Lohn während BQ | Die zugeordnete Bewerbung als Lohn öffnen. | Lohn sieht die Vertragsstammdaten und die BQ-Bezeichnung, aber keine Bewerbungsakte, Interviews, internen Anmerkungen oder BQ-Bewertung. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| G5 | Negatives Ergebnis | Nacheinander mit synthetischen Fällen `nicht geeignet`, `abgebrochen` und `nicht teilgenommen` erfassen und die Lohnsicht neu laden. | Der BQ-begründete Lohnzugriff endet jeweils sofort; es erfolgt keine automatische Ablehnung der Bewerbung. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| G6 | Geeignet und Personalentscheidung | Ergebnis `geeignet` erfassen, die Einstellungsfreigabe zunächst ohne und anschließend mit gültigem Bereich versuchen. | Das Ergebnis stellt nicht automatisch ein; erst die ausdrückliche Freigabe mit Bereich gelingt. Ohne `geeignet` bleibt auch ein Umweg über `decision_pending` gesperrt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| G7 | BQ-Verwaltungsrecht | Durchlauf, Zuordnung und Ergebnis als Lohn, Erstbegleitung und Vertretung direkt aufrufen beziehungsweise verändern. | Ausschließlich Personalreferat und Nextcloud-Admin erhalten Zugriff; alle anderen Requests werden vor dem Objektzugriff abgewiesen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| G8 | Erstbegleitung während BQ | Die BQ-Bewerbung mit einer ansonsten passenden Erstbegleitung öffnen. | Vor der Einstellungsfreigabe besteht kein Erstbegleitungszugriff. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## H. Sicherheit, Datenschutz und bewusste Grenzen

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| H1 | CSRF-Schutz | Einen schreibenden Personen-, Bewerbungs-, Status-, BQ-, Einstellungsdaten- und Berechtigungsrequest ohne gültiges Requesttoken senden. | Jeder Request wird abgewiesen und verändert keinen Datensatz. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| H2 | Direkte Capability-Denies | Für jede eingesetzte Testrolle mindestens einen nicht erlaubten Endpunkt direkt aufrufen. | Die serverseitige Capability- und Scope-Prüfung greift unabhängig von sichtbaren Schaltflächen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| H3 | Verständliche Fehler | Fehlendes Recht, nicht vorhandenen Datensatz, Parallelkonflikt und Validierungsfehler nacheinander auslösen. | Die Oberfläche unterscheidet die Fehler verständlich und zeigt keine technischen oder personenbezogenen Details. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| H4 | Logs und Browserantworten | Technische Logs und Netzwerkantworten der Testfälle auf sensible Inhalte prüfen. | Vertragsstammdaten, Personeninhalte, Interviewantworten, BQ-Bewertungen und E-Mail-Inhalte erscheinen nicht in technischen Logs oder Fehlerantworten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| H5 | Nicht umgesetzte Integrationen | Oberfläche und Routen auf weitere Dokumentablage, IMAP-Import, Antwortzuordnung, BQ-Modulanbindung und öffentliche Fragebogenlinks prüfen. | Diese bewusst nicht umgesetzten Integrationen werden nicht fälschlich als verfügbar oder abgenommen dargestellt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| H6 | Datensparsame Abnahme | Formular und Screenshots vor Ablage oder Weitergabe prüfen. | Es wurden ausschließlich synthetische Daten verwendet; keine Zugangsdaten oder Echtdaten sind enthalten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## I. Bewerbungsposteingang

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| I1 | Idempotenter Demo-Import | In der freigegebenen lokalen Demo-Umgebung `php occ adrecruitment:inbox:seed` zweimal ausführen. | Der erste Lauf importiert zwei, der zweite null neue Nachrichten; insgesamt bestehen zwei Nachrichten, zwei Anhänge und zwei Import-Auditeinträge. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| I2 | Unzugeordneter Zugriff | Den Posteingang als Personalreferat, globale Vertretung mit `edit_applications`, Bereichs-/Fallvertretung, Lohn und Erstbegleitung direkt aufrufen. | Nur Personalreferat, Nextcloud-Admin und globale bearbeitende Vertretung erhalten den unzugeordneten Eingang. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| I3 | Original und Vorschläge | Websiteformular-Mail und freie Mail mit normalisiertem Lebenslauftext öffnen. | Unveränderter Mailtext, Absender, Empfang, Postfach, PDF-Metadaten sowie quellmarkierte Vorschläge aus Mail und Lebenslauf sind unterscheidbar; Vorschläge überschreiben keine Stammdaten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| I4 | Manuelle Zuordnung | Eine neue Nachricht einer bestehenden synthetischen Bewerbung zuordnen und danach die Bewerbungsakte mit berechtigten sowie unberechtigten Konten öffnen. | Die Nachricht verschwindet nicht, erhält Status und Audit; Mailtext und Metadaten folgen danach ausschließlich dem Bewerbungsscope. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| I5 | Ausgewählte Feldvorbelegung | Eine Mail mit Kontaktangaben, Eintritt, Ort, Wunschstunden, Vorerfahrung und Deutschniveau einer Bewerbung zuordnen, deren Vertragsbereich bereits einen abweichenden Ort und deren Bewerbung bereits B1 enthält. Nur E-Mail, Eintritt, Wunschstunden, Vorerfahrung und Deutschniveau markieren; E-Mail, Stundenbereich und Vorerfahrung zuvor korrigieren. | Korrigierte E-Mail, Eintritt, Wunschstundenbereich und Vorerfahrung werden in leere Felder übernommen. Telefon bleibt leer, Ort und vorhandenes B1 unverändert; manipulierte oder ungültige Vorschlagsfelder werden ohne Zuordnung abgewiesen. Das Audit enthält nur Feldnamen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| I6 | Kontaktaktionen | E-Mail-Adressen und unterschiedlich formatierte deutsche Telefonnummern im Posteingang, Bewerbungsdetail sowie Vertrags-/LoBu-Bereich anklicken. | E-Mail öffnet als `mailto:` in einem neuen, vom Recruitment-Kontext getrennten Tab; Telefon öffnet als normalisierter `tel:`-Link die registrierte Telefonsoftware, beispielsweise AGFEO Dashboard. Ungültige Werte bleiben unverlinkt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| I5 | Korrektur und Konflikt | Dieselbe Zuordnung in zwei Fenstern öffnen, in A korrigieren und in B mit der alten Version erneut speichern. | Genau die erste Änderung gelingt; die zweite wird als Konflikt abgewiesen und Originaltext sowie PDF bleiben unverändert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| I6 | PDF-Grenzen | Nicht-PDF, falsche PDF-Signatur, mehr als fünf Dateien und Überschreitung der Größenlimits über die Importgrenze versuchen. | Jede ungültige Nachricht wird vor Persistenz abgewiesen; zulässige PDFs liegen nur unter serverseitigen Hashpfaden im privaten AppData. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| I7 | Nicht-Bewerbung schließen | Eine neue Nachricht als Nicht-Bewerbung schließen und dieselbe Aktion erneut beziehungsweise mit alter Version versuchen. | Der Status wird einmal auditiert auf `ignoriert` gesetzt; weitere oder konkurrierende Änderungen werden abgewiesen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| I8 | Lokale PDF-Extraktion | Eine Mail mit textbasiertem PDF-Lebenslauf importieren, der Name, Telefon, Wohnort, Wunschstunden, Verfügbarkeit, Berufserfahrung und Deutsch C1 enthält. Danach als Nextcloud-Admin `Einstellungen → Verwaltung → AD Recruitment → Datenextraktion` öffnen. | Die Werte erscheinen nur als Vorschläge mit PDF-/Lebenslaufquelle. Die Einstellung zeigt das lokale PDF-Werkzeug, „Lokale Stichworterkennung“ ist aktiv und „Lokales Server-Modell“ sichtbar, aber als noch nicht angebunden deaktiviert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| I9 | Atomare Neuanlage | Eine neue Nachricht öffnen, Personendaten korrigieren, eine aktive Stelle wählen, nur einzelne Vorschläge bestätigen und „Person und Bewerbung anlegen“ ausführen. Danach denselben Request mit alter Version wiederholen sowie den Ablauf ohne Posteingangs- beziehungsweise Bearbeitungsrecht versuchen. | Genau eine Person und eine Bewerbung im Status `Eingang` werden mit dem Mail-Eingangsdatum angelegt und die Nachricht wird zugeordnet. Nur bestätigte Werte werden übernommen. Alte oder unberechtigte Requests werden ohne zusätzliche Person, Bewerbung, Vorbelegung oder Zuordnung abgewiesen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## J. PDF-Lightbox und Feldverknüpfungen

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| J1 | Geschützte Lightbox | Dasselbe synthetische PDF als berechtigtes Personalreferat, passende Vertretung, Lohn und fremde Vertretung über Oberfläche und Dokumentroute öffnen. | Das PDF öffnet in einer großen Lightbox. Nur Konten mit globalem Posteingangs- beziehungsweise passendem Bewerbungsscope erhalten PDF-Inhalt; Lohn und fremde Scopes werden serverseitig abgewiesen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| J2 | Text- und Bereichsmarkierung | In einem textbasierten PDF Text auswählen und übernehmen; anschließend auf einer Seite einen grafischen Bereich aufziehen. | Beide Wege übernehmen Seite und sichtbare Fundstelle in das Zuordnungsformular. Bereits gespeicherte Markierungen erscheinen nach erneutem Öffnen an derselben Position. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| J3 | Tastaturweg | Lightbox ohne Zeiger bedienen, PDF-Werkzeuge erreichen und Seite sowie Quelltext manuell eingeben. | Öffnen, Schließen, Zoomen, Zielauswahl und manuelle Fundstellenerfassung sind vollständig per Tastatur möglich; Fokus bleibt nachvollziehbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| J4 | Strukturierte Ziele | Je eine synthetische Fundstelle mit Vorerfahrung, Deutschniveau, Geburtsdatum und Geburtsort verbinden. | Zulässige Werte erscheinen im richtigen Bewerbungs- beziehungsweise Einstellungsstammdatenfeld und behalten Seite, Markierung, Quelle, Akteur und Zeitpunkt als getrennten Herkunftsnachweis. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| J5 | Kein stilles Überschreiben | Ein bereits gefülltes strukturiertes Feld zunächst ohne und danach mit sichtbarer Ersatzbestätigung verknüpfen. | Der erste Request wird konfliktfrei ohne Mutation abgewiesen; erst die ausdrückliche Bestätigung ersetzt den alten Wert und dokumentiert die Herkunft. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| J6 | Freier Kommentar bleibt erhalten | Einen bestehenden mehrzeiligen freien Bewerbungskommentar mit einer markierten PDF-Stelle verbinden. | Der Bestand bleibt bytegetreu erhalten; der neue Text wird genau einmal in einer neuen Zeile angehängt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| J7 | Rechte und Zuordnung | Verknüpfung als Erstbegleitung, Lohn, unpassende Vertretung sowie Vertretung mit passenden Kombinationen aus Scope, `manage_documents` und Feldrecht versuchen. | Lesen folgt der Akte. Schreiben gelingt ausschließlich mit passendem Scope, `manage_documents` und `edit_applications` beziehungsweise `edit_hiring_data`; ein unzugeordnetes Dokument ist nicht verknüpfbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| J8 | Validierung und Idempotenz | Ungültige Seite, Rechtecke außerhalb der Seite, unbekanntes Zielfeld, C3 als Deutschniveau, ungültiges Datum und denselben Client-Schlüssel zweimal senden. | Alle ungültigen Requests bleiben ohne Mutation; die gültige Wiederholung erzeugt weder zweiten Feldinhalt noch zweiten Herkunftsnachweis. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| J9 | Unverändertes Original und Integrität | PDF vor und nach mehreren Verknüpfungen vergleichen; in isolierter Testumgebung anschließend den gespeicherten Inhalt verändern. | Verknüpfungen verändern das Original nicht. Ein Hashfehler verhindert jede spätere Dateiausgabe und offenbart weder Pfad noch Inhalt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## K. Statusmail-Entwürfe und Versand

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| K1 | Regel ein/aus | Als Personalreferat für eine zulässige Statuskante eine Vorlage wählen, die Regel deaktiviert und danach aktiviert testen. | Ohne aktive Regel ändert sich nur der Status; mit aktiver Regel entstehen Statusprotokoll und genau ein Entwurf atomar. Es wird noch nichts versendet. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| K2 | Revision und Schlusskorrektur | Entwurf erzeugen, danach die Vorlage revidieren und im Entwurf Zieladresse, Betreff und Text ändern. | Der Entwurf behält seinen alten Vorlagen-Snapshot; die Schlusskorrekturen sind sichtbar und erst mit der Freigabe verbindlich. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| K3 | Empfänger-Nachweis | Die aus dem Personendatensatz übernommene Adresse vor Freigabe bewusst korrigieren. | Ursprüngliche Adresse, freigegebene Zieladresse und tatsächlicher Zustellempfänger bleiben getrennt sichtbar; die korrigierte Adresse wird validiert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| K4 | Terminierung | Je einen synthetischen Entwurf sofort, zu einem freien Zukunftszeitpunkt und für den kommenden Montag freigeben; den Hintergrundjob vor und nach Fälligkeit ausführen. | Vor Fälligkeit erfolgt kein Versand. „Kommender Montag“ liegt um 09:00 Uhr in Europe/Berlin; nach erfolgreichem Transport steht der Entwurf auf versendet. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| K5 | Zentraler Testmodus | Als Nextcloud-Admin unter `Einstellungen → Verwaltung → AD Recruitment` Testmodus und Standardadresse aktivieren; als Personalreferat eine Mail an eine andere synthetische Adresse freigeben. | Der Server stellt ausschließlich an die Testadresse zu. Oberfläche und Nachweis zeigen ursprüngliche, freigegebene und tatsächliche Adresse; Nicht-Admins können die Einstellung nicht ändern. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| K6 | Rechte, CSRF und Konflikte | Vorlagen-, Regel-, Entwurfs- und Freigabeendpunkte als unberechtigte Rolle, ohne Requesttoken sowie parallel mit alter Version aufrufen. | Jeder unzulässige Request bleibt ohne Mutation; Personalreferat verwaltet Vorlagen, `communicate` plus Bewerbungsscope schützt Entwürfe und nur Nextcloud-Admins verwalten den Testmodus. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| K7 | Retry und Logs | In isolierter Testumgebung einen Transportfehler sowie einen verwaisten Worker-Claim auslösen. | Der Auftrag bleibt mit begrenztem Retry sichtbar, ein verwaister Claim wird nach 15 Minuten wieder aufgenommen und technische Logs enthalten weder Adressen noch Betreff oder Nachrichtentext. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| K8 | Vorbereitete Übergänge | Die Mailvorlagenverwaltung öffnen, mehrere Kanten derselben Vorlage zuweisen, alle Rückzugskanten prüfen und das Upgrade erneut ausführen. | Alle 41 erlaubten Statuskanten erscheinen genau einmal und zunächst ausgeschaltet. Vorlagen sind mehrfach zuweisbar; alle Rückzugskanten teilen dieselbe Vorlage. Bearbeitete oder aktivierte Regeln werden beim erneuten Upgrade nicht überschrieben. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| K11 | Rückzug | Eine aktive Bewerbung in Karten- und Tabellenansicht zurückziehen und die Bestätigung einmal abbrechen. | Es gibt keine Spalte/Karte `Zurückgezogen`; die rote Aktion fragt nach Bestätigung. Abbrechen ändert nichts, Bestätigen erzeugt den auditierten Statuswechsel und gegebenenfalls den gemeinsamen Mailentwurf. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| K9 | HTML-Editor und Textalternative | Eine Vorlage und anschließend den erzeugten Entwurf mit Absatz, Fett, Kursiv, Liste und HTTPS-Link bearbeiten. Danach HTML- und Klartextteil der synthetischen Testmail prüfen. | Beide Teile sind lesbar und inhaltlich gleichwertig; die letzte Änderung ist enthalten. Editor und Aktionen sind vollständig per Tastatur erreichbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| K10 | Manipuliertes HTML | Per direktem Request Skript, Style, Bild mit Eventhandler sowie `javascript:`-Link in Vorlage und Entwurf senden. | Aktive Inhalte und unsichere Attribute/URLs erreichen weder Snapshot noch Versand. Zulässiger Text bleibt erhalten und es werden keine Mailinhalte geloggt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## L. Bewerberpool-Einstellungen

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| L1 | PersRef-Pflege | Als Personalreferat unter `Recruitment → Einstellungen → Datenschutz & Rückstellungen` Datenschutzhinweis, Einwilligungsdauer und Erinnerungsfrist speichern. | Die Werte werden versioniert gespeichert und nach dem Neuladen angezeigt; der Bewerberpool bleibt ohne versionierten Datenschutzhinweis deaktiviert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| L2 | Berechtigungsgrenze | Dieselbe Oberfläche und den direkten Speicherrequest als Lohn, Erstbegleitung und delegierte Vertretung versuchen. | Nur Personalreferat und Nextcloud-Admins mit der nicht delegierbaren Fähigkeit `manage_candidate_pool` können lesen oder ändern; abgewiesene Requests verändern keine Einstellung. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| L3 | Keine Doppelpflege | Als Nextcloud-Admin den nativen Abschnitt `Einstellungen → Verwaltung → AD Recruitment` und anschließend das Recruitment-Modul öffnen. | Der native Abschnitt enthält keine Bewerberpool-Konfiguration; die einzige Bearbeitungsoberfläche liegt im Recruitment-Modul. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## Automatisierter lokaler Nachweis vom 11.09.2026

Im Rahmen der risikoarmen Luna-Prüfung wurden ausschließlich lokale, nicht
mutierende Prüfungen ausgeführt:

| Prüfung | Ergebnis | Aussagegrenze |
|---|---|---|
| `php tests/run.php` | erfolgreich | PHP-Syntax sowie Bewerbungsworkflow-, Mail-, Dokument-, BQ-, Interview-, Rechte-, Privacy-, Processing-Metadata-, Migrations- und Adminverträge sind grün; auch negative und mutierte Eingabefälle bleiben abgedeckt. |
| `node tests/run-js.mjs` | erfolgreich | JavaScript-Syntax sowie Admin-, Kontaktverknüpfungs- und Frontend-Smokes sind grün. |
| `git diff --check` | erfolgreich | Keine Whitespace-Fehler im Arbeitsstand. |

DDEV, `occ`, Installation, App-Aktivierung, Postfachanbindung, Bewerbungs-,
Dokumenten- oder Maildaten wurden nicht verändert. Dieser Nachweis ersetzt
weder die offene manuelle/stagingbezogene Abnahme noch Entscheidungen zu
produktiver Mail-, Datenschutz- und Aufbewahrungsintegration.

## Abschlussentscheidung

| Feld | Eintrag |
|---|---|
| Anzahl erfolgreich | |
| Anzahl nicht erfolgreich | |
| Anzahl nicht geprüft | |
| Kritische Abweichungen / Ticketreferenzen | |
| Erneute Prüfung erforderlich bis | |
| Gesamtentscheidung | [ ] abgenommen [ ] mit Auflagen abgenommen [ ] nicht abgenommen |
| Begründung der Gesamtentscheidung | |
| Name / Datum | |
