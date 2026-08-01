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
| A1 | Leserecht | Mit einem Konto aus `adrecruitment-readers` die App und vorhandene synthetische Datensätze öffnen. | Bewerbungen, Stellen und Vorlagen sind lesbar; schreibende Formulare und Aktionen sind nicht verfügbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A2 | Konto ohne Fachgruppe | App und direkten Bootstrap-Aufruf mit einem angemeldeten Konto außerhalb aller Recruitment-Gruppen versuchen. | Der Zugriff wird serverseitig verweigert und es werden keine Bewerbungsdaten geliefert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A3 | Nextcloud-Admin | App mit einem Nextcloud-Admin öffnen. | Der Admin erhält die vorgesehenen Fähigkeiten unabhängig von zusätzlichen Recruitment-Gruppen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A4 | Tabnavigation | Zwischen Bewerbungen, Stellen und Interviewvorlagen mit Maus sowie Pfeiltasten wechseln. | Aktiver Tab, `aria-selected`, Fokus und zugehöriger Tabpanel bleiben synchron. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A5 | Tastatur und Fokus | Formulare, Tabellen, Detailansicht, Vorlageneditor und Interview ausschließlich mit Tastatur bedienen. | Alle Funktionen sind erreichbar, Fokus ist sichtbar und es gibt keine Tastaturfalle. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| A6 | Responsivität und Scrollen | Viele synthetische Einträge bei kleinem Fenster und vergrößertem Zoom anzeigen. | App-Root und Tabellenwrapper halten Inhalte erreichbar; es entsteht kein unkontrolliertes globales Seitenscrolling. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## B. Stellen, Personen und Bewerbungen

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| B1 | Stelle anlegen | Als `adrecruitment-managers`-Testkonto eine synthetische Stelle mit interner und öffentlicher Bezeichnung sowie neutraler Zuständigkeit anlegen. | Die Stelle erscheint einmal mit den gespeicherten Angaben und bleibt nach Neuladen erhalten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B2 | Katalogrecht | B1 als reines Reader-, Editor- und Interviewer-Konto über UI und direkten Request versuchen. | Nur die dafür vorgesehenen Manager-/Adminrechte erlauben die Katalogaktion; abgewiesene Requests verändern nichts. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B3 | Person getrennt von Bewerbung | Als Editor eine synthetische Person anlegen und danach zwei Bewerbungen derselben Person auf unterschiedliche Stellen erstellen. | Eine Person bleibt eine Entität; beide Bewerbungen referenzieren sie getrennt und besitzen eigenen Status. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B4 | Vollständige Bewerbungszuordnung | Bewerbung mit Person, aktiver Stelle, Eingangskanal, Eingangsdatum und neutraler Zuständigkeit anlegen. | Der Datensatz erscheint mit genau der gewählten Person und Stelle in Liste und Detail. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B5 | Ungültige Referenz und Eingabe | Fehlende Person, deaktivierte beziehungsweise ungültige Stelle, ungültiges Datum und leere Pflichtfelder versuchen. | Jeder Versuch wird verständlich abgewiesen; es entsteht keine halbe Person-Bewerbungs-Beziehung. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| B6 | Editorscope | Personen- und Bewerbungsanlage als Reader, Manager ohne Editorrecht und Editor vergleichen. | Schreibrechte folgen den serverseitigen Fähigkeiten; sichtbare UI allein erteilt kein Recht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## C. Kontrollierte Bewerbungsstatus

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| C1 | Erlaubter Übergang | In einer Testbewerbung einen angebotenen nächsten Status wählen und Detailansicht neu laden. | Nur ein erlaubter Übergang wird ausgeführt; der neue Status und die neue Version sind sichtbar. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C2 | Verbotener Übergang | Einen nicht angebotenen Status beziehungsweise einen fachlich unzulässigen Sprung per direktem Request versuchen. | Der Server weist den Übergang ab; Status und Verlauf bleiben unverändert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C3 | Statusverlauf | Zwei erlaubte Übergänge mit neutralen Editor-Konten durchführen. | Jeder Übergang protokolliert Ausgangsstatus, Zielstatus, Zeitpunkt und ausführende Nextcloud-UID. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C4 | Fehlendes Editorrecht | Statuswechsel als Reader, Interviewer und Manager ohne Editorrecht versuchen. | Alle unberechtigten Wege werden serverseitig verweigert. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| C5 | Paralleländerung | Dieselbe Bewerbungsdetailversion in zwei Browserfenstern öffnen, zuerst in Fenster A und danach in Fenster B ändern. | Die veraltete zweite Änderung wird als Konflikt abgewiesen und überschreibt den aktuellen Stand nicht. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## D. Interviewvorlagen und Revisionen

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| D1 | Vorlage anlegen | Als Manager eine synthetische Vorlage mit Typ, Zielgruppe und neutraler Beschreibung anlegen. | Die Vorlage erscheint aktiv mit eindeutiger Revision. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
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
| E8 | Interviewrecht | Entwurf und Abschluss mit Interviewer/Editor sowie als Reader und Manager ohne Interviewrecht versuchen. | Nur Konten mit Interviewfähigkeit dürfen schreiben; unberechtigte Requests verändern nichts. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

## F. Sicherheit, Datenschutz und bewusste Grenzen

| ID | Was wird geprüft? | Auszuführende Schritte | Erwartetes Ergebnis | Ergebnis | Warum/Beleg/Abweichung |
|---|---|---|---|---|---|
| F1 | CSRF-Schutz | Einen schreibenden Personen-, Bewerbungs-, Status- und Interviewrequest ohne gültiges Requesttoken senden. | Jeder Request wird abgewiesen und verändert keinen Datensatz. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| F2 | Direkte Capability-Denies | Für jede eingesetzte Testrolle mindestens einen nicht erlaubten Endpunkt direkt aufrufen. | Die serverseitige Capability-Prüfung greift unabhängig von sichtbaren Schaltflächen. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| F3 | Verständliche Fehler | Fehlendes Recht, nicht vorhandenen Datensatz, Parallelkonflikt und Validierungsfehler nacheinander auslösen. | Die Oberfläche unterscheidet die Fehler verständlich und zeigt keine technischen oder personenbezogenen Details. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| F4 | Logs und Browserantworten | Technische Logs und Netzwerkantworten der Testfälle auf sensible Inhalte prüfen. | Personeninhalte, Interviewantworten, E-Mail-Inhalte und unnötige Kennungen erscheinen nicht in technischen Logs oder Fehlerantworten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| F5 | Nicht umgesetzte Integrationen | Oberfläche und Routen auf Dokumentablage, IMAP-Import, Mailversand und öffentliche Fragebogenlinks prüfen. | Diese bewusst nicht umgesetzten Integrationen werden nicht fälschlich als verfügbar oder abgenommen dargestellt. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |
| F6 | Datensparsame Abnahme | Formular und Screenshots vor Ablage oder Weitergabe prüfen. | Es wurden ausschließlich synthetische Bewerbungs- und Interviewdaten verwendet; keine Zugangsdaten oder Echtdaten sind enthalten. | [ ] erfolgreich [ ] nicht erfolgreich [ ] nicht geprüft | |

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
