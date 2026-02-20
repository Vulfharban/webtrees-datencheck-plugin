Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

## [1.3.10] - 2026-02-20
### Hinzugefügt
- **Menü-Icon Option**: Das Modul-Icon im Hauptmenü kann nun in den Einstellungen deaktiviert werden.
- **Default-Einstellung**: Das Icon ist nun standardmäßig **deaktiviert**, um Layout-Probleme mit bestimmten Themes (z. B. webtrees primer theme) zu vermeiden.
### Behoben
- **Server-Error (TypeError)**: Fix für einen kritischen Fehler in `checkBurialBeforeDeath()`, bei dem unter bestimmten Bedingungen kein Rückgabewert geliefert wurde (Return value must be of type ?array, none returned).

## [1.3.8] - 2026-02-17
### Hinzugefügt
- **Geschlechts-Heuristik**: Namen, die auf 'a' oder 'e' enden, werden nun automatisch als weiblich erkannt, falls sie nicht in der Datenbank stehen.
- **Erweiterte Namensliste**: Unterstützung für weitere Varianten wie Giesela, Karolina, Regina, Marianna etc.
### Behoben
- **AJAX-Trigger**: Validierung reagiert nun sofort auf jede Änderung im Formular (Input/Change auf allen Feldern).
- **Feld-Erkennung**: Massive Verbesserung der Erkennung von Geschlechts-Radios (M/F) und Namensfeldern, auch bei webtrees-spezifischen Patterns wie `ivalues[]`.
- **Parsing-Fix**: Sonderzeichen (Slashes) in Namen werden nun vor der Validierung bereinigt.
- **Namespace-Fix**: Fehler 'Class I18N not found' im AJAX-Service behoben.

## [1.3.7] - 2026-02-17
### Hinzugefügt
- **Geschlechts-Validierung**: 
  - Warnung, wenn ein Vorname eingegeben wurde, aber das Geschlecht noch nicht ausgewählt ist.
  - Hinweis (Info), wenn der Vorname nicht zum gewählten Geschlecht passt (basierend auf einer Datenbank mit über 100 gängigen Vornamen und deren Varianten).
- **UX**: Validierung wird nun auch beim Ändern des Geschlechts im Formular sofort ausgelöst.
- **Lokalisierung**: Unterstützung für Geschlechts-Prüfungen in Deutsch, Englisch, Bulgarisch, Ukrainisch, Ungarisch und Griechisch.

## [1.3.6] - 2026-02-17
### Hinzugefügt
- **Prüfung auf zukünftige Daten (Erweiterung)**: Detektion von zukünftigen Daten für Geburt, Tod und Heirat nun auch bei Neuanlage von Personen (vor dem ersten Speichern).
- **Mehrsprachigkeit**: Unterstützung für Bulgarisch (bg), Ukrainisch (uk), Griechisch (el) und Ungarisch (hu) für die Zukunftsdatumsprüfung vervollständigt.
### Behoben
- **Stabilität (Skelett-Objekte)**: Fix für Abstürze bei Neuanlagen, wenn die Person noch nicht in der Datenbank existiert (`exists()` Check / `checkInvalidMonths`).
- **Performance/UX**: Validierung bei Datumsfeldern wird nun erst beim Verlassen des Feldes (`change`) statt bei jeder Eingabe (`input`) ausgelöst, um unnötige Server-Anfragen während des Tippens zu vermeiden.
- **Debug-Logs**: Entfernung von internen Konsolen-Ausgaben.

## [1.3.4] - 2026-02-17
### Hinzugefügt
- **Prüfung auf zukünftige Daten**: Neue Validierung für Geburts-, Todes-, Tauf-, Begräbnis- und Heiratsdaten. Erkennt Tippfehler wie "2945" statt "1945".
- **Mehrsprachige Unterstützung**: Neue Übersetzungen für Deutsch, Englisch, Polnisch, Spanisch, Italienisch, Russisch, Französisch und Niederländisch hinzugefügt.
### Behoben
- **Syntaxfehler**: Fehlende schließende Klammer im `TemporalValidator` behoben.
- **Menüposition**: Die webtrees-interne Menü-Sortierung wurde wiederhergestellt (redundante Einstellung entfernt).

## [1.3.3] - 2026-02-13
### Hinzugefügt
- **Vollständige Lokalisierung (bg)**: Unterstützung für Bulgarisch vervollständigt (inkl. aller neuen Vergleichs-Strings).
- **UX / Formular-Automatisierung**: Optimierte Feld-Erkennung für die Buttons "Diese Familie nutzen" / "Diese Person nutzen" (Unterstützung für weitere webtrees 2.2-spezifische IDs wie `fid`, `f_id`).
- **Anzeige-Optimierung**:
  - Ortsangaben werden für bessere Lesbarkeit nun bis zum ersten Komma gekürzt.
  - Sterbeorte werden nun für alle Personen im Vergleich angezeigt.
  - Klare Trennung durch Symbole (* für Geburt, † für Tod) in separaten Zeilen.
### Behoben
- **Flimmern im Modal**: Priorisierung von Formular-Daten gegenüber Server-Daten verhindert das Überschreiben ungespeicherter Änderungen während des Vergleichs.
- **Robustes Parsing**: Einführung eines Multi-Strategie-Extraktors für GEDCOM-Daten (MARR, PLAC), der fehlertolerant gegenüber verschiedenen Zeilenumbruch-Flavors ist.

## [1.3.2] - 2026-02-13
### Hinzugefügt
- **Erweiterter Familien-Vergleich**:
  - **Geburts- & Sterbeorte**: Anzeige von Orten für alle Personen im Vergleichs-Modal (sowohl aktueller Eintrag als auch Duplikat-Kandidaten).
  - **Elegantes Layout**: Vollständige Überarbeitung der Familien-Ansicht. Ehepartner werden nun platzsparend dargestellt.
  - **Heirats-Sektion**: Integration der Heiratsdaten (Ring-Symbol `∞`, Datum und Ort) direkt neben den Ehepartnern.
- **Robustes GEDCOM-Parsing**: Verbesserte Extraktion von Heiratsdaten (MARR) und Orten (PLAC) aus verschiedensten GEDCOM-Dialekten (Regex-Optimierung für CRLF/LF).
### Behoben
- **Rollen-Duplizierung**: Fix für einen Fehler, bei dem eine Person fälschlicherweise gleichzeitig als Ehemann und Ehefrau im Vergleich angezeigt wurde.
- **Daten-Synchronität**: Sicherstellung, dass manuelle Formulareingaben (Orte/Daten) korrekt in das Vergleichs-Modal übernommen werden.
    
## [1.3.1] - 2026-02-12
### Geändert
- **Biologische Plausibilität (Altersprüfung)**: Einführung einer Kulanz-Regelung für unpräzise Datumsangaben (z. B. reine Jahreszahlen oder Schätzungen wie "ABT / um").
  - Bei unpräzisen Daten wird nun ein **Puffer von 5 Jahren** für das Mindestalter von Vater (Standard: 14) und Mutter (Standard: 14) gewällt, bevor ein Fehler gemeldet wird. Dies reduziert Fehlalarme bei historischen Schätzungen erheblich.
  - Die Prüfung auf das biologische Höchstalter bleibt weiterhin strikt, um reale Erfassungsfehler zuverlässig zu melden.

## [1.3.0] - 2026-02-12
### Hinzugefügt
- **Globale Namens-Wissensdatenbank**: Einführung einer umfassenden Datenbank für Namens-Äquivalente über verschiedene Sprachen hinweg. 
  - Erkennt nun hunderte Variationen wie `Henryk = Heinrich = Enrico`, `Wacław = Wenzel`, `Katarzyna = Katharina`.
  - **Teilmengen-Matching**: Intelligenter Vergleich von Mehrfachvornamen.
- **Intelligente Datums-Zeitraum-Prüfung**: Korrekte Behandlung von GEDCOM-Modifikatoren wie `AFT`, `BEF`, `ABT`. 
- **Intelligente Ehenamen-Logik**: Automatisches Ignorieren von Warnungen bei Ehenamen.

## [1.2.3] - 2026-02-11
### Behoben
- **Date-API Fehler**: Korrektur eines kritischen Fehlers bei der Bulk-Analyse.

## [1.2.2] - 2026-02-11
### Hinzugefügt
- **Erweiterte Alias-Unterstützung (International)**: „Genannt-Namen“ Logik wurde um polnische und lateinische Varianten erweitert.
- **Konsistente Lokalisierung**: Überarbeitung der Kategorienamen.

## [1.2.1] - 2026-02-11
### Hinzugefügt
- **Performance-Optimierung**: Umstellung der Bulk-Analyse auf ID-basierte Paginierung.
- **DOM-Schutz**: Begrenzung der angezeigten Ergebnisse auf 1000 Zeilen.
- **Monats-Validierung**: Neue Prüfung auf nicht-GEDCOM-konforme Monatsnamen.

## [1.2.0] - 2026-02-11
### Hinzugefügt
- **Umgang mit ungenauen Daten**: Intelligente Erkennung von ungenauen Datumsangaben.
- **Münsterländische Genannt-Namen**: Unterstützung für westfälische Alias-Namen.

## [1.1.0] - 2026-02-09
### Geändert
- **Refactoring Übersetzungen**: Umstellung auf einheitliche englische Keys.

---

## ✅ Phase 18: Erweitertes Matching & Heuristik (COMPLETE - 2026-02-17)
- [x] **Fallback-Heuristik**: Automatisches Erkennen weiblicher Endungen (a/e).
- [x] **Robustes AJAX**: Fix für Context-Guards und Feld-Keywords.
- [x] **Versions-Sprung v1.3.8**: Stabilitäts-Patch für Geschlechts-Validierung.

---

## Versionshistorie
- **Status:** Version 1.3.8 - **Stable** (Gender Heuristics & Fixes)
- **v1.3.0:** Globale Namens-Datenbank (10+ Sprachen), Intelligente Ehenamen-Logik, Diakritika-Handling
- [x] **v1.3.3:** Kompakte Anzeige, Sterbeort-Integration, bulgarische Lokalisierung
- [x] **v1.3.6:** Zukunftsdaten, Fixes für Neuanlagen und Stabilitäts-Patch
- [x] **v1.3.8:** Geschlechts-Heuristiken & AJAX-Fixes

## [0.9.0] - 2026-02-08
### Hinzugefügt
- **Bulk-Analyse**: Gesamte Stammbaum-Prüfung im Admin-Bereich.

## [0.8.0] - 2026-02-08
### Hinzugefügt
- **Funktion "Fehler ignorieren"**: Dauerhafte Ignorieren-Liste mit DB-Tabelle.
- **Admin-UI**: Neue Tabs und Kontext-Hilfe.
