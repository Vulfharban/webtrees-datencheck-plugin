# Webtrees Datencheck Plugin - Projektstatus & Roadmap

- **Aktuelle Version:** 1.1.3 (In Entwicklung)
- **Status:** Stabil, Fokus auf UX und Detail-Validierung.

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

### 4. Internationalisierung (I18N)
*   **Breite Unterstützung:** Support für über 25 Sprachvarianten (EN, DE, FR, NL, ES, etc.).
*   **Spezialregeln:** Robuste Unterstützung für skandinavische, slawische, spanische und niederländische Namenskonventionen.

---

## 🛠️ Geplante Roadmap & Backlog

### Priorität 1: Detail-Validierung & UX
1.  **Erkennung ungültiger Monatsnamen:**
    *   Warnung, wenn Datumsfelder sprachfremde Monatsnamen enthalten (z.B. "März" statt "MAR" in einem englischen Kontext).
    *   Automatischer Vorschlag zur Konvertierung in den GEDCOM-Standard.
2.  **Orts-Normalisierung (Light):**
    *   Identifizierung von Variationen desselben Ortes (z.B. "München" vs. "Muenchen") mittels Levenshtein-Distanz.
    *   Warnung bei inkonsistenter Schreibweise innerhalb eines Stammbaums.

### Priorität 2: Performance & Skalierbarkeit
1.  **Geodaten-Caching:**
    *   Speichern von Distanzberechnungen, um wiederholte Bulk-Analysen zu beschleunigen.
2.  **Datenbank-Optimierung:**
    *   Verfeinerung der Indizes für die `datencheck_ignored` Tabelle bei extrem großen Beständen (>100k Personen).

### Priorität 3: Erweiterte Berichte
1.  **Druckansicht / PDF-Export:**
    *   Möglichkeit, den Analysebericht in einem druckfreundlichen Format oder als PDF zu generieren.
2.  **Visualisierung der Datenqualität:**
    *   Ein kleines Dashboard-Widget, das den "Gesundheitszustand" des Stammbaums in Prozent anzeigt.

---

## 🏗️ Wartung & Stabilität
Das Projekt befindet sich in einem stabilen Zustand. Zukünftige Updates konzentrieren sich primär auf die Kompatibilität mit neuen webtrees-Releases und die Verfeinerung bestehender Algorithmen.
