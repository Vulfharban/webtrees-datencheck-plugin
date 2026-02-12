# Changelog: webtrees Datencheck Plugin

Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.
    
## [1.3.1] - 2026-02-12
### Geändert
- **Biologische Plausibilität (Altersprüfung)**: Einführung einer Kulanz-Regelung für unpräzise Datumsangaben (z. B. reine Jahreszahlen oder Schätzungen wie "ABT / um").
  - Bei unpräzisen Daten wird nun ein **Puffer von 5 Jahren** für das Mindestalter von Vater (Standard: 14) und Mutter (Standard: 14) gewährt, bevor ein Fehler gemeldet wird. Dies reduziert Fehlalarme bei historischen Schätzungen erheblich.
  - Die Prüfung auf das biologische Höchstalter bleibt weiterhin strikt, um reale Erfassungsfehler zuverlässig zu melden.

## [1.3.0] - 2026-02-12
### Hinzugefügt
- **Globale Namens-Wissensdatenbank**: Einführung einer umfassenden Datenbank für Namens-Äquivalente über verschiedene Sprachen hinweg (Deutsch, Polnisch, Latein, Englisch, Holländisch, Tschechisch, Russisch, Französisch, Spanisch, Italienisch, Skandinavisch). 
  - Erkennt nun hunderte Variationen wie `Henryk = Heinrich = Enrico`, `Wacław = Wenzel`, `Katarzyna = Katharina`, `Władysław = Ladislaus = Walter`, `Rosina = Rozyna`, `Apolonia = Polly = Pauline`.
  - **Teilmengen-Matching**: Intelligenter Vergleich von Mehrfachvornamen (z.B. wird "Johann" als Übereinstimmung zu "Johann Friedrich" akzeptiert).
  - Verbessert die Genauigkeit der Dubletten-Erkennung und reduziert "False Positives" bei der Namenskonsistenzprüfung massiv.
- **Intelligente Datums-Zeitraum-Prüfung**: Korrekte Behandlung von GEDCOM-Modifikatoren wie `AFT`, `BEF`, `ABT`. 
  - Behebt fehlerhafte Warnungen bei Reihenfolge-Checks (z.B. "Bestattung nach 1971" vs "Tod 1981").
  - Unpräzise Überlappungen oder fehlende Tagesdaten werden nun als **"Hinweis" (Info)** statt als Fehler gemeldet.
- **Intelligente Ehenamen-Logik**: Automatisches Ignorieren von Warnungen bei Ehenamen, wenn diese nur den neuen Nachnamen enthalten oder der Geburtsvorname im Feld enthalten ist.
- **Platzhalter-Schutz**: Automatisches Ignorieren von webtrees-Platzhaltern (z.B. Namen beginnend mit `@`) in der Namensprüfung.

## [1.2.3] - 2026-02-11
### Behoben
- **Date-API Fehler**: Korrektur eines kritischen Fehlers bei der Bulk-Analyse. Die nicht existierende Methode `gedcom()` wurde durch die korrekte webtrees-API-Methode `format('%@')` ersetzt.

## [1.2.2] - 2026-02-11
### Hinzugefügt
- **Erweiterte Alias-Unterstützung (International)**: Die „Genannt-Namen“ Logik wurde um polnische und lateinische Varianten erweitert (z. B. „vel“, „alias“, „inaczej“, „zwany“). Dies verbessert die Dubletten-Erkennung und Namenskonsistenzprüfung in osteuropäischen Beständen.
- **Konsistente Lokalisierung**: Vollständige Überarbeitung der Kategorienamen in allen unterstützten Sprachen zur besseren Verständlichkeit (z. B. „Biologische Plausibilität“ statt nur „Biologisch“).
- **Verbesserter Analyse-Filter**: Der Kategorien-Filter im Ergebnis-Dashboard wurde vervollständigt und deckt nun alle Analysebereiche konsistent ab.

## [1.2.1] - 2026-02-11
### Hinzugefügt
- **Performance-Optimierung für große Bäume**: Umstellung der Bulk-Analyse auf ID-basierte Paginierung. Dies verhindert Performance-Einbußen bei sehr großen Beständen (z.B. 130k Personen).
- **DOM-Schutz**: Begrenzung der angezeigten Analyse-Ergebnisse im Browser auf 1000 Zeilen zur Vermeidung von Browser-Abstürzen bei massiven Fehlerlisten. (Vollständiger Export weiterhin via CSV möglich).
- **Erkennung monatsspezifischer Inkonstistenzen**: Neue Prüfung auf nicht-GEDCOM-konforme Monatsnamen (z.B. "März", "Januar").
- **Genannt-Namen in Dubletten-Suche**: Die Unterstützung für westfälische Alias-Namen („genannt“) wurde nun auch auf die interaktive Dubletten-Suche während der Dateneingabe ausgeweitet.

    
## [1.2.0] - 2026-02-11
### Hinzugefügt
- **Umgang mit ungenauen Daten**: Intelligente Erkennung von ungenauen Datumsangaben (z. B. nur Jahreszahl). Konflikte werden in diesen Fällen nun als Warnung statt als Fehler ausgegeben, um "False Positives" zu reduzieren.
- **Optionale Warnungen für ungenaue Daten**: Neue Einstellung im Admin-Bereich, um Warnungen bei fehlender Tages-/Monatspräzision komplett zu deaktivieren.
- **Münsterländische Genannt-Namen**: Unterstützung für westfälische Alias-Namen („genannt“, „gen.“, „vulgo“, „dictus“). Diese werden nun korrekt bei der Namenskonsistenzprüfung zwischen Eltern und Kindern berücksichtigt.
- **Erweiterte englische Lokalisierung**: Vollständige Übersetzung aller neuen Validierungsmeldungen und Einstellungsoptionen.


## [1.1.3] - 2026-02-10
### Hinzugefügt
- **Benutzerabhängige Konfiguration**: Administratoren können nun ihre eigenen Grenzwerte und aktiven Prüfungen individuell für ihr Benutzerkonto speichern (DC-Präfix zur Einhaltung von DB-Limits).
- **Erweiterte Tauf-Validierung**: Neue Prüfung für Taufen, die ungewöhnlich spät (>30 Tage) nach der Geburt stattfinden.
- **Dokumentations-Update**: Umfassende Überarbeitung der README (DE/EN) und Aktualisierung der Roadmap.
### Geändert
- **Abschluss PHP-Migration**: Vollständige Entfernung des `datencheck_cli` Ordners; alle Algorithmen (Phonetik, Levenshtein, Geschwister-Check) laufen nun ausschließlich nativ in PHP.
- **Optimierung**: Verkürzung der Einstellungsschlüssel zur Vermeidung von SQL-Fehlern bei langen Bezeichnern.
### Behoben
- **Alterscheck-Fehler**: Behebung eines Problems, bei dem Personen ohne Eltern fälschlicherweise sich selbst als zu jungen Vater/Mutter erkannten.
- **SQL-Stabilität**: Behebung des "Data too long" Fehlers beim Speichern von Benutzer-Präferenzen.

## [1.1.2] - 2026-02-09
### Hinzugefügt
- **Internationale Namenskonventionen**: Unterstützung für slawische (`-ski/-ska`), spanische (Doppelnamen), niederländische (Tussenvoegsels) und griechische (`-is/-ou`) Namensregeln.
- **Prüfung auf Namensvorsätze**: Erkennt, wenn Vorsätze (z.B. "von", "van", "de") fälschlicherweise im Nachnamenfeld statt im Präfixfeld eingetragen wurden.
- **Neue Sprachen**: Ukrainisch, Slowakisch, Ungarisch, Kroatisch, Rumänisch, Bulgarisch sowie Unterscheidungen für Englisch (GB/US/AU) und Französisch (CA).
- **Isländische Sprachunterstützung**: Vollständige Lokalisierung für Isländisch (`is.php`) hinzugefügt.
- **Optimierte Namensprüfung**: Verbesserte Behandlung von isländischen Patronymen (`-son` / `-dóttir`).
### Behoben
- **Navigation**: Korrektur des "Zurück zum Stammbaum"-Links (führt nun zur Startseite des Baums).
- **URL-Encoding**: Behebung eines Fehlers mit ungültigen Platzhaltern in generierten Links (`%7Bxref%7D`).

## [1.1.1] - 2026-02-09

### Geändert
- **Refactoring Übersetzungen**: Umstellung auf einheitliche englische Keys als Basis für alle Übersetzungen zur Vermeidung von Rückfällen auf Deutsch.

### Behoben
- **Übersetzungsfehler**: Korrektur von hartkodierten Textelementen im Interaktions-Modal und in API-Antworten.
- **Biologische Validierung**: Fix für Argument-Typen in der Altersprüfung der Mutter.

## [1.1.0] - 2026-02-09

### Geändert
- **Platzhalter-Vereinheitlichung**: Umstellung von `%s` auf `{ID}` in allen Sprachdateien für JS-Kompatibilität.

### Behoben
- **Modul-Variablen Fehler**: Fix für "Undefined variable $module" im Analyse-Backend.
- **Sichtbarkeit**: Hilfsmethoden im `ValidationService` für den Zugriff durch spezialisierte Validatoren auf `public` gesetzt.
- **Französische/Holländische Lokalisierung**: Veraltete deutsche Reste entfernt und durch korrekte Übersetzungen ersetzt.

## [0.9.2] - 2026-02-08
### Hinzugefügt
- **CSV-Export**: Schaltfläche im Analyse-Dashboard zum Herunterladen aller Ergebnisse als CSV-Datei (Excel-optimiert).
- **GitHub-Update**: Automatische Prüfung auf neue Versionen via `latest-version.txt` (direkt vom GitHub-Release).
- **Public Repository**: Codebase für Open-Source-Feedback veröffentlicht.

### Geändert
- **Verbesserte Fehlermeldungen**:
  - Datumsangaben in Validierungsmeldungen sind nun vollständig formatiert (z.B. "01.05.1850" statt nur "1850").
  - Kontext-Informationen (z.B. Geburtsdatum der Eltern bei Alters-Warnung) werden detaillierter angezeigt.

## [0.9.1] - 2026-02-08
### Hinzugefügt
- **Ergebnis-Filter**: Dropdowns in der Analyse-Tabelle zum Filtern nach Schweregrad (Fehler/Warnung) und Kategorie.
### Geändert
- **Source-Check Optimierung**:
  - Implementierung einer robusten Erkennung von Quellen (`gedcom()` String-Analyse + `attribute('SOUR')`).
  - Behebt "False Positives" bei existierenden Quellen.
  - Debugging-Optionen im Backend entfernt.

## [0.9.0] - 2026-02-08
### Hinzugefügt
- **Bulk-Analyse**:
  - Neue Funktion im Admin-Bereich ("Analyse"-Tab), um den gesamten Stammbaum auf Fehler zu prüfen.
  - Stückweise Verarbeitung (Chunks à 200 Personen) mit Fortschrittsanzeige verhindert Timeouts.
  - Auflistung aller gefundenen Fehler in einer übersichtlichen Tabelle.
- **Backend-Stabilität**:
  - `validationResult` Struktur angepasst (Issues + Debug-Daten).
  - Fehlerbehandlung für API-Antworten verbessert (HTML-Fehlerseiten werden gefangen).
  - `BatchAnalysis`-Action korrekt im Router registriert.
- **Bugfixes**:
  - `Registry::placeFactory` durch `Registry::locationFactory` ersetzt (webtrees 2.1 Kompatibilität).
  - Unbekannte API-Methoden (`citations()`, `sour()`) vorübergehend deaktiviert, um Abstürze zu vermeiden.

## [0.8.0] - 2026-02-08
### Hinzugefügt
- **Funktion "Fehler ignorieren"**:
  - Implementierung einer dauerhaften "Ignorieren"-Liste für Fehlalarme (False Positives).
  - Neue Datenbanktabelle `datencheck_ignored` mit automatischer Schema-Erstellung.
  - "Ignorieren"-Button direkt im Validierungs-Popup.
- **UI-Improvements:**
  - Admin-Einstellungen in Tabs strukturiert (Allgemein, Plausibilität, Funktionen).
  - Kontext-sensitive Hilfetexte für jeden Einstellungsbereich hinzugefügt.
  - Menüpunkt vereinfacht ("Einstellungen & Prüfungen").
- **Datenbank-Verbesserung:** Tabelle `datencheck_ignored` ist nun per Foreign-Key (`ON DELETE CASCADE`) mit dem jeweiligen Stammbaum (`tree_id`) verknüpft. Beim Löschen eines Baumes werden ignorierte Fehler automatisch mitgelöscht.
- **Admin-Oberfläche**:
  - Neue Seite "Ignorierte Fehler" (`AdminIgnored`) zum Anzeigen und Wiederherstellen ausgeblendeter Fehler.
  - Zugriffskontrolle: Nur Moderatoren und Manager können Fehler ignorieren oder löschen.
  - Mehrsprachigkeit (Deutsch/Englisch) für Fehlercodes.
- **Backend-Verbesserungen**:
  - `IgnoredErrorService` zur Handhabung von Datenbankoperationen.
  - Refactoring des `ValidationService` zur Unterstützung eindeutiger Fehlercodes.
  - Verbesserte Transaktionsverwaltung bei Schema-Updates.

## [0.7.0] - 2026-02-06
### Hinzugefügt
- **Modulare Architektur**: Komplettes Refactoring in Services und Views.
- **InteractionService**: Zentralisierte Behandlung von AJAX-Anfragen und Koordination.
- **Verbesserte Benutzeroberfläche**: JavaScript und CSS in `interaction.phtml` ausgelagert für bessere Wartbarkeit.
- **Erweiterte Plausibilitätsprüfungen**:
  - Erkennung doppelter Geschwister mit phonetischem und Datums-Abgleich.
  - Prüfung auf posthume Geburt (Warnung, wenn Geburt > 9 Monate nach Tod des Vaters).
  - Namenskonsistenz: Warnung, wenn der Nachname des Kindes dem der Mutter, aber nicht dem des Vaters entspricht.
- **UI-Verbesserungen**: Link "In neuem Fenster öffnen" zum Vergleichs-Modal hinzugefügt.
- **Fehlerbehebungen**: Fehler bei Variablendefinition in `getCheckPersonAction` behoben.

## [0.6.0] - 2026-02-05
### Hinzugefügt
- **Konfigurierbare Schwellenwerte**: Admin-UI erlaubt nun die Anpassung aller Validierungsparameter.
- **Detailliertes Vergleichs-Modal**: Seite-an-Seite-Ansicht für Dubletten-Kandidaten mit visueller Hervorhebung.
- **Zusätzliche Validierungskategorien**: Ehen, fehlende Daten, geografische Plausibilität und Namenskonsistenz.

## [0.5.0] - 2026-02-03
### Geändert
- **Reine PHP-Migration**: Abhängigkeit von Rust-CLI vollständig entfernt. Das Plugin ist nun 100% natives PHP.
- **Plattformunabhängigkeit**: Funktioniert auf Linux, Windows und macOS ohne Kompilierung.
- **Admin-UI Korrekturen**: Korrektes webtrees-Layout, Breadcrumbs, CSRF-Schutz und I18N-Unterstützung.

## [0.4.0] - 2026-01-27
### Hinzugefügt
- **Geschwister-Abstandsprüfung**: Hochpräzise Julianisches-Datum-Berechnung für Geburtsintervalle.
- **Tauf-Fallback**: Nutzt Tauf-/Christening-Daten, falls kein Geburtsdatum vorhanden ist.
- **Familien-Zusammenführung**: Neuer Button, um Eltern automatisch mit einem existierenden Familiendatensatz zu verknüpfen.

## [0.3.0] - 2026-01-20
### Hinzugefügt
- **Interaktive Dubletten-Warnung**: Echtzeit-Warnungen während der Personenerstellung.
- **Phonetische Suche**: Integration der Kölner Phonetik für besseren Namensabgleich.

## [0.2.0] - 2026-01-15
### Hinzugefügt
- **Grundlegende Datenvalidierung**: Konsistenzprüfung von Geburts- und Sterbedaten.
- **Altersvalidierung**: Regeln für das Alter von Mutter/Vater bei der Geburt.

## [0.1.0] - 2026-01-08
### Hinzugefügt
- Erstveröffentlichung mit Rust-basierter CLI zur Dubletten-Erkennung.
- Grundlegende webtrees-Modul-Integration.
