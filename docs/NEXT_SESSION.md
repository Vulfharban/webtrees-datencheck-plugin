# Webtrees Datencheck Plugin - Projektstatus & Roadmap

- **Aktuelle Version:** 1.2.2 (Stabil)
- **Status:** Internationalisierte Validierung & erweiterte Alias-Erkennung.

## ✅ Kürzlich abgeschlossen (Februar 2026)

### 1. Benutzerabhängige Konfiguration
*   **Individuelle Einstellungen:** Administratoren können nun ihre eigenen Grenzwerte (z.B. Mindestalter der Mutter) und aktiven Prüfungen speichern.
*   **Technik:** Implementierung von `getSetting()` / `setSetting()` zur Umgehung von `final`-Methoden im webtrees-Kern. Nutzung des Präfixes `DC_` zur Einhaltung von DB-Längenbeschränkungen.
*   **Fallback:** Automatische Rückfallebene auf globale Modul-Standards, falls keine Benutzereinstellung vorhanden ist.

### 2. Erweiterte Tauf-Validierung
*   **Verspätete Taufe:** Neue Prüfung erkennt Taufen, die mehr als X Tage (Standard: 30) nach der Geburt stattfinden. Hilfreich zur Identifizierung von Sonderfällen oder späten Quellen.
*   **Fehlerkorrektur Alterscheck:** Fix für "Vater zu jung"-Fehler, bei dem fälschlicherweise die eigene Person als Vater erkannt wurde (Selbstreferenz-Prüfung).

### 3. Native PHP-Logik (CLI Entfernung)
*   **Konsolidierung:** Der `datencheck_cli` (Rust) wurde vollständig entfernt. Die gesamte Logik (Phonetik, Levenshtein, Sibling-Check) wurde nach PHP portiert, um die Installation zu vereinfachen und Abhängigkeiten zu reduzieren.

### 4. Regionale Erweiterungen & Präzision (v1.2.0)
*   **Münsterländische Genannt-Namen:** Unterstützung für westfälische Alias-Formen in der Namensprüfung.
*   **Intelligentes Datums-Handling:** Detaillierte Prüfung der Datumspräzision. Warnungen statt Fehler bei ungenauen Daten (z.B. nur Jahr).
*   **Konfigurierbarkeit:** Möglichkeit, Warnungen bei ungenauen Daten komplett zu unterbinden.
*   **Vollständige Lokalisierung:** Neue Strings in Deutsch und Englisch konsistent ergänzt.

### 5. Performance & Datenqualität (v1.2.1)
*   **Performance für Großbestände:** Umstellung der Bulk-Analyse auf ID-basierte Paginierung (Schutz vor Timeout bei >130k Personen).
*   **DOM-Schutz:** Begrenzung der Browser-Anzeige auf 1000 Zeilen zur Vermeidung von Browser-Abstürzen (Full Export via CSV möglich).
*   **Monats-Validierung:** Neue Prüfung auf nicht-GEDCOM-konforme Monatsnamen (z.B. lokalisierte Namen wie "Januar").
*   **Erweiterte Alias-Suche:** Genannt-Namen Logik in die allgemeine Dubletten-Suche integriert.

### 6. Internationalisierung & Alias-Erweiterung (v1.2.2)
*   **Polnische Alias-Namen:** Erweiterung der "Genannt-Namen" Logik um Varianten wie "vel", "alias", "zwany", "inaczej". Essentiell für die korrekte Verarbeitung polnischer historischer Aufzeichnungen.
*   **Terminologie-Harmonisierung:** Vereinheitlichung der Kategorienamen in allen 15 Sprachen (z.B. "Source Quality" statt "Sources").
*   **Filter-Vervollständigung:** Der Ergebnisfilter im Dashboard deckt nun alle 9 Analysebereiche vollständig ab.

---

### Geplante Roadmap & Backlog (Next Steps)

1.  **Geodaten-Caching / Optimierung:**
    *   Speichern von Distanzberechnungen, um wiederholte Bulk-Analysen zu beschleunigen.
2.  **Druckansicht / PDF-Export:**
    *   Möglichkeit, den Analysebericht in einem druckfreundlichen Format oder als PDF zu generieren.
3.  **Visualisierung der Datenqualität:**
    *   Ein Dashboard-Widget für den "Gesundheitszustand" des Stammbaums.

---

## 🏗️ Wartung & Stabilität
Das Projekt befindet sich in einem stabilen Zustand. Zukünftige Updates konzentrieren sich primär auf die Kompatibilität mit neuen webtrees-Releases und die Verfeinerung bestehender Algorithmen.
