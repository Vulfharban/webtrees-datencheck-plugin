# Roadmap: webtrees Datencheck Plugin

## ✅ Phase 1: Fundament (COMPLETE)
- [x] **Rust-CLI:** Kommandozeilen-Tool für Dubletten-Erkennung
- [x] **Basics:** Levenshtein und Kölner Phonetik für Namen
- [x] **Webtrees Skeleton:** PHP-Plugin-Registrierung in webtrees

## ✅ Phase 2: Logik & Validierung (COMPLETE)
- [x] **Alterscheck:** Berechnung von plausiblen Altersspannen
- [x] **FAM-Matching (Basic):** Suche nach HUSB/WIFE Paaren
- [x] **API/FFI Bridge:** Anbindung via CLI (später migriert zu nativem PHP)

## ✅ Phase 3: UI-Integration & Automatisierung (COMPLETE)
- [x] **Inline-Warnungen:** Echtzeit-Anzeige von möglichen Dubletten via AJAX
- [x] **Konfiguration:** Fuzzy-Matching Einstellungen im Control Panel
- [x] **Auto-Link FAM:** Button zum direkten Verknüpfen mit existierenden Familien
- [x] **Geschwister-Check:** Spezifische Prüfung innerhalb der gewählten Familie

## ✅ Phase 4: PHP Migration (COMPLETE - 2026-02-03)
- [x] **Native PHP Implementation:** Komplette Migration von Rust zu PHP
  - [x] StringHelper (Fuzzy Matching)
  - [x] PhoneticHelper (Kölner Phonetik)
  - [x] DateParser (GEDCOM-Datums-Parsing)
  - [x] DatabaseService (webtrees DB-Integration)
- [x] **Admin-UI Integration:** 
  - [x] Breadcrumbs Navigation
  - [x] CSRF-Token-Fix
  - [x] I18N-Unterstützung
  - [x] Proper webtrees Layout
- [x] **Cross-Platform:** Plugin funktioniert jetzt auf Linux, Windows, macOS
- [x] **Production-Ready:** Keine Kompilierung oder externe Binaries erforderlich

## ✅ Phase 5: Testing & Deployment (COMPLETE)
- [x] **Manuelle Funktionstests:**
  - [x] Admin-Seite (Settings, Save, Cancel)
  - [x] Person-Duplikat-Erkennung
  - [x] Familien-Matching
  - [x] Geschwister-Check
- [x] **Modularisierung:** Extraktion von Services und Views
- [x] **Version 0.7.0 Release**
- [x] **Archivierung des Rust-Codes** (zur Referenz behalten)

## ✅ Phase 6: Polish & Erweiterte Checks (COMPLETE)
- [x] **Detailed Comparison Modal:** Side-by-side Vergleich von Dubletten-Kandidaten
- [x] **Erweiterte Alters-Validierung:** Biologisch unmögliche Konstellationen
- [x] **Geschwister-Spatium:** Prüfung auf Mindestabstand zwischen Geburten
- [x] **Namens-Konsistenz:** Abgleich von Nachnamen (Vater vs. Kind)

## ✅ Phase 7: Workflow & Exception Management (COMPLETE - 2026-02-07)
- [x] **Ignore System:**
  - [x] Unique Error Codes for all validation rules
  - [x] Database Schema for ignored errors (`datencheck_ignored`)
  - [x] "Ignore" Action in Validation Popup
  - [x] Admin Interface for managing ignored errors
  - [x] Access Control (Moderators only)
- [x] **Version 0.8.0 Release**

## ✅ Phase 8: Bulk-Analyse & Quality Assurance (COMPLETE - 2026-02-08)
- [x] **Bulk-Modus:** Batch-Verarbeitung für große Datenbestände (200 Personen/Chunk)
- [x] **Status-Tracking:** Fortschrittsanzeige und Fehlertoleranz
- [x] **Ergebnis-Dashboard:** Tabellarische Auflistung aller gefundenen Fehler
- [x] **Version 0.9.0 Release (Beta)**

## ✅ Phase 9: Reporting & Release-Prep (COMPLETE - 2026-02-08)
- [x] **Export:** CSV-Bericht aller Validierungsfehler.
- [x] **Datums-Formatierung:** Lesbare Datumsangaben im gesamten UI.
- [x] **GitHub-Integration:** Updates via Release-Tag und `latest-version.txt`.
- [x] **Version 0.9.2 Release** (Feature Complete)

## ✅ Phase 10: V1.1.0 Regional Support & UX (COMPLETE - 2026-02-09)
- [x] **Skandinavische Namensregeln:** Patronymische Logik für Schweden/Island erweitert.
- [x] **Multi-Language Update:** Vollständiger Support für 15 Sprachen (ES, IT, PT, etc.).
- [x] **UX Context Persistence:** Beibehaltung der Baum-Auswahl beim Navigieren und Speichern.
- [x] **Tree-Aware Breadcrumbs:** Verbesserte Navigation zurück zum aktiven Baum.
- [x] **Back-to-Tree Navigation:** Direkter Link vom Dashboard zum Stammbaum.
- [x] **Bugfixes:** Behebung von Variablen- und Sichtbarkeitsproblemen im Backend.
    
## ✅ Phase 11: Regionale Erweiterungen & Präzision (COMPLETE - 2026-02-11)
- [x] **Ungenaues Datums-Handling:** Herabstufung von Fehlern zu Warnungen bei unpräzisen GEDCOM-Daten (z.B. nur Jahr).
- [x] **Münsterländische Genannt-Namen:** Unterstützung für westfälische Alias-Namen („genannt“, „vulgo“, etc.) in der Namensprüfung.
- [x] **Konfigurierbare Präzision:** Option zum vollständigen Deaktivieren von Warnungen bei ungenauen Daten.
- [x] **Version 1.2.0 Release**

## ✅ Phase 12: Performance & Datenqualität (COMPLETE - 2026-02-11)
- [x] **ID-basierte Paginierung:** Optimierung der Bulk-Analyse für extrem große Bäume (130k+ Personen).
- [x] **DOM-Protection:** Begrenzung der Ergebnisdarstellung im Browser auf 1000 Zeilen.
- [x] **Monats-Validierung:** Erkennung von nicht-standardisierten (lokalisierten) Monatsnamen.
- [x] **Alias-Matching (Allgemein):** Westfälische Namenskonventionen in der gesamten Dubletten-Suche integriert.
- [x] **Version 1.2.1 Release**
    
## ✅ Phase 13: Internationalisierung & Alias-Erweiterung (COMPLETE - 2026-02-11)
- [x] **Polnische Alias-Namen:** Erweiterung der Logik um „vel“, „zwany“, „inaczej“ etc.
- [x] **Terminologie-Review:** Vereinheitlichung der Kategorienamen in allen 15 Sprachen.
- [x] **Dashboard-Filter:** Vollständige Abdeckung aller Analysebereiche im UI-Filter.
- [x] **Version 1.2.2 Release**

---

## Versionshistorie
- **v0.5.0:** Komplette PHP-Migration, Admin-UI-Fix
- **v0.6.0:** Detaillierter Vergleichs-Modal, Konfigurierbare Schwellwerte
- **v0.7.0:** Modularisierung (Services/Views), Erweiterte Plausibilitäts-Checks
- **v0.8.0:** Ignore-Feature, Admin-Liste, DB-Schema-Management
- **v0.9.0:** Bulk-Analyse, Batch-Processing, Reporting-Dashboard
- **v0.9.2:** CSV-Export, GitHub-Update-Check, Formatiertes Datum
- **v1.0.0:** Major Release mit verfeinerter Architektur und Stabilität
- **v1.1.0:** Skandinavische Namenskonventionen, 15 Sprachen, UX Context Persistence
- **v1.2.0:** Münsterländische Genannt-Namen, Intelligentes Handling ungenauer Daten
- **v1.2.1:** Performance-Optimierung für 100k+ Bäume, Monats-Validierung, Alias-Matching
- **v1.2.2:** Polnische Alias-Namen, Harmonierte Lokalisierung, Vervollständigte Filter
