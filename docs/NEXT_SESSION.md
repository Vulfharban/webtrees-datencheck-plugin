# Nächste Sitzung - Planung

## Aktueller Stand (v1.5.7)
*   **GEDCOM-Standardprüfung**: Vollständig implementiert (BIRT, DEAT, BAPM, BURI, SEX).
*   **Info-Kategorie**: Neue blaue Kategorie für redaktionelle Hinweise zur Datenpflege eingeführt.
*   **Lokalisierung**: Alle 27 Sprachdateien sind auf dem neuesten Stand.
*   **Build-System**: Das PowerShell-Build-Skript ist nun robust gegen Pfad-Variationen und stellt die Ordnerstruktur korrekt wieder her.

## Offene Punkte & Ideen
1.  **Erweiterte Quellenprüfung (Qualität)**:
    *   Überprüfung auf konsistente Verwendung von Quellentypen (z.B. Geburtsurkunde für Geburtsfakt).
    *   Erkennung von fehlenden Seitenzahlen (`PAGE`) in Quellenzitaten.
2.  **Quick-Fix Buttons**:
    *   UI-Erweiterung in der Analyse-Tabelle, um einfache Fehler (z.B. falsche Reihenfolge Taufe/Geburt) mit einem Klick zu korrigieren.
3.  **Hierarchie & Statistik**:
    *   Prüfung auf statistische Ausreißer im Stammbaum (z.B. extreme Abstände zwischen Geschwistern über 10 Jahre ohne Lücke).
4.  **Familien-Zusammenführung (Bulk)**:
    *   Globale Analyse zur Identifizierung von gesplitteten Familien (gleiche Eltern, verschiedene Familien-Records).

## Erledigte Aufgaben (heute)
*   Fix: `sprintf` Placeholder Error (`%d` vs `%s`) in Übersetzungstexten.
*   Fix: ZIP-Build Fehler (fehlende Buchstaben am Dateianfang).
*   Fix: ZIP-Build Fehler (flache Dateihierarchie korrigiert).
*   Feature: "Check GEDCOM" Kategorie in Dashboard und Filter.
*   Feature: Dubletten-Check für Taufe und Bestattung.
*   Localization: Update für alle 27 Sprachen.
