# Plan: webtrees Datencheck Plugin

## Projektziel
Entwicklung eines Plugins für webtrees, das Dateninkonsistenzen verhindert und die Erfassung von Dubletten während der Eingabe unterdrückt.

**Status:** Version 1.3.0 - **Stable** (Global Name Knowledge Base)

## Kernfunktionen

### 1. Personen-Deduplizierung (Deduplication) ✅ DONE
- **Problem:** Mehrfaches Anlegen derselben Person verhindern.
- **Lösung:** 
    - [x] Echtzeit-Prüfung bei Namenseingabe
    - [x] **Fuzzy Matching** (Levenshtein) für Tippfehler
    - [x] **Phonetische Suche** (Kölner Phonetik) für gleich klingende Namen
    - [x] **Robustes Datums-Parsing:** Unterstützung für versch. Formate & Altersrechnung
    - [x] **Konfigurierbare Schwellenwerte:** Einstellbare Fuzzy-Toleranz über Admin-UI

### 2. Intelligentes Familien-Matching ✅ DONE
- **Problem:** Kinder werden oft in neue FAM-Datensätze statt bestehende Familien eingetragen.
- **Lösung:**
    - [x] Live-Check: Suche nach existierender Familie (HUSB + WIFE) bei Elterneingabe
    - [x] **Automatischer Vorschlag:** Button "Diese Familie nutzen" zur schnellen Zuweisung
    - [x] **Geschwister-Matching:** Warnung vor Dubletten innerhalb derselben Familie
    - [x] **Skandinavische Patronymika:** Unterstützung regionaler Namensregeln (-son, -dóttir, etc.) in ganz Skandinavien/Island.
    - [x] **Globale Namens-Datenbank:** Unterstützung für hunderte Namens-Äquivalente über 10+ Sprachen hinweg (DE, PL, LAT, EN, NL, CZ, RU, FR, ES, IT). Erkennt Alias-Formen und Varianten automatisch.

### 3. Workflow-Integration & Datenqualität ✅ DONE
- **Ignore-Funktion (False-Positives):**
    - [x] "Fehler ignorieren" Button im Popup.
    - [x] Datenbank-Speicherung von Ausnahmen.
    - [x] Admin-Seite zur Verwaltung ignorierter Fehler.
- **Admin-Konfiguration:**
    - [x] Einstellungen im webtrees-Layout.
    - [x] Mehrsprachigkeit (I18N).
- **Bulk-Analyse & Reporting:**
    - [x] **Batch-Processing:** Prüfung des gesamten Stammbaums in Chunks (200 Personen).
    - [x] **Fortschrittsanzeige:** Visuelles Feedback während der Analyse.
    - [x] **Ergebnisliste:** Tabelle mit Links zu fehlerhaften Personen.

## Technische Architektur

### PHP native Architektur (Refactored) ✅
- **Services Layer**: 
    - `ValidationService`: Plausibilität & Error Codes
    - `IgnoredErrorService`: DB-Management für Ausnahmen
    - `SchemaService`: Automatische Tabellen-Erstellung
    - `InteractionService`: API & AJAX
- **View Layer**:
    - `interaction.phtml`: Interaktive Overlays
    - `admin.phtml`: Konfiguration & Analyse-Dashboard
    - `admin_ignored.phtml`: Übersicht ausgeblendeter Fehler
- **Helper Classes**: String, Phonetik, Datum, Konstanten

### PHP Webtrees Plugin (UI/Integration)
- Native Integration in die webtrees-Oberfläche
- JavaScript AJAX-Calls für interaktive Checks
- Hooks: `GedcomRecordSaving`, `GedcomRecordCreated`

## Phase 5: Erweiterte Features (WIP)
- [x] **Orts-Plausibilität:** Prüfung auf geografisch unwahrscheinliche Ortswechsel (implementiert, aber Optimierung nötig)
- [x] **Präzisions-Handling:** Differenzierung zwischen harten Fehlern und Warnungen bei ungenauen Daten (1855 vs. Mai 1855).
- [x] **Quellen-Pflicht:** Optionale Prüfung auf SOUR-Tags (implementiert & robust)
- [x] **Reporting-Export:** CSV-Export der Analyse-Ergebnisse.
- [x] **UX & Kontext:** Erhalt der Baum-Auswahl und verbesserte Breadcrumb-Navigation.
- [x] **Orts-Normalisierung:** Unterstützung bei der Vereinheitlichung von Ortsnamen
- [x] **Performance-Optimierung:** ID-basierte Paginierung für 100k+ Bäume.
- [x] **Monats-Validierung:** Prüfung auf nicht-GEDCOM konforme Datumsangaben.

## Abgeschlossene Meilensteine (2026-02-08)
1. ✅ Rust-Kern vollständig in PHP reimplementiert
2. ✅ Admin-UI & Ignore-Funktion vollständig integriert
3. ✅ Bulk-Analyse Feature implementiert (Backend & Frontend)
4. ✅ Unterstützung für regionale westfälische Namensformen (Genannt-Namen)
