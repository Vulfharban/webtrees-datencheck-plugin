# Webtrees Datencheck Plugin - Projektstatus & Roadmap

- **Aktuelle Version:** 1.3.8 (Stabil)
- **Status:** Geschlechts-Heuristiken, robuste AJAX-Steuerung & Namespace-Fixes.

## ✅ Kürzlich abgeschlossen (Februar 2026 - v1.3.8)

### 1. Erweitertes Geschlechts-Matching & Heuristik
*   **Heuristik (Endungen)**: Namen auf 'a' und 'e' werden automatisch als weiblich erkannt.
*   **Erweiterte Datenbank**: Viele weitere Namensvarianten (Regina, Karolina etc.) wurden hinzugefügt.
*   **Warnung bei fehlendem Geschlecht**: Verhindert das Vergessen des Geschlechtsfeldes während der Namenseingabe.

### 2. Robuste AJAX-Steuerung & UX
*   **Massive Feld-Erkennung**: Das Plugin erkennt nun zuverlässig Geschlechts-Felder (Radios mit M/F/U-Werten) und Namen, auch bei generischen webtrees-Feldnamen wie `ivalues[]`.
*   **Sofort-Validierung**: Wechsel von spezifischen Keywords auf globale `change`/`input` Listener – Meldungen verschwinden nun sofort bei Korrektur.
*   **Parsing-Fixes**: Slashes in GEDCOM-Feldern (z.B. `/Müller/`) werden vor der Validierung bereinigt.
*   **Stabilität**: Namespace-Fehler (`Class I18N not found`) im Backend behoben.

## 🚀 Ausblick & Nächste Schritte
*   **Performance**: Lokales Caching von Validierungsergebnissen zur Reduzierung von Server-Anfragen.
*   **Orts-Plausibilität**: Optionale Prüfung von Ortsnamen gegen bekannte Koordinaten oder externe APIs.
