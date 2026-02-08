# Webtrees Datencheck Plugin - Next Steps
- **Version:** 0.9.2 (Export & Formatting)
- **Status:** Feature Complete. Polished.

## Current Status (2026-02-08)
- **✅ Version 0.9.2 - ABGESCHLOSSEN**
- **✅ Features:**
    - **CSV-Export:** Bulk-Analyse Ergebnisse können als CSV heruntergeladen werden (Excel-kompatibel).
    - **Datums-Formatierung:** Fehlermeldungen zeigen nun lesbare Datumsangaben (z.B. "01.05.1980") statt nur Jahreszahlen.
    - **GitHub-Integration:** Automatischer Update-Check via `latest-version.txt`.
    - **Repository:** Code ist nun auf GitHub verfügbar.

## Completed Tasks (Phase 0.9.x)

- [x] **Repository Setup**: Initiales Git-Repo erstellt, auf GitHub veröffentlicht.
- [x] **Update Check**: `module.php` prüft nun `latest-version.txt` auf GitHub.
- [x] **Export-Funktion**: CSV-Export Button im Analyse-Dashboard implementiert.
- [x] **UI-Polishing**: `formatDate`-Helper für bessere Lesbarkeit von Fehlermeldungen.
- [x] **Bugfixes**: CLI-Artefakte entfernt, `.gitignore` erstellt.

## Nächste Session: Optimierung & Erweiterung

## Offene Probleme / Bugs
- (Keine kritischen Bugs bekannt)

## Ziele für die nächste Session (v1.0.0?)
1.  **Multi-Language Support (I18N):**
    - Alle hardcodierten Strings durch Übersetzungsfunktionen ersetzen.
    - Sprachdateien anlegen für: Deutsch, Englisch, Niederländisch (NL), Französisch (FR).
    - Sicherstellen, dass die UI automatisch die richtige Sprache wählt.
2.  **Geografische Validierung (Advanced):**
    - Echte Distanzberechnung (km) zwischen Ereignissen (Tod/Geburt).
    - Caching für Geodaten (um externe API-Calls zu minimieren, falls wir solche einführen).
2.  **Performance:**
    - Testen mit sehr großen Bäumen (100k+) -> Ggf. Chunk-Größe dynamisch anpassen.
3.  **Refactoring:**
    - Ggf. `ValidationService` weiter aufsplitten (z.B. `BiologicalCheck`, `TemporalCheck`), da die Datei recht groß wird.
4.  **Orts-Vorschläge:**
    - Ähnliche Ortsnamen finden und korrigieren (Levenshtein auf Orte).

