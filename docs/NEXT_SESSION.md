# Webtrees Datencheck Plugin - Projektstatus & Roadmap

- **Aktuelle Version:** 1.3.11 (Stabil)
- **Status:** Scheidungs-Validierung, verbesserte Ehe-Logik & vollständige Übersetzungen.

## ✅ Kürzlich abgeschlossen (Februar 2026 - v1.3.11)

### 1. Scheidungs-Validierung & Logik
*   **Chronologie-Checks**: Prüfung auf Scheidung nach Tod/Bestattung oder vor Geburt/Hochzeit.
*   **Partner-Vergleich**: Einbeziehung der Lebensdaten des Partners bei Scheidungs-Checks.
*   **Ehe-Überlappung v2**: Berücksichtigung von Scheidungen zur Vermeidung von Fehlalarmen bei Wiederverheiratung.

### 2. Internationalisierung (i18n)
*   **Vollständige Übersetzungen**: Alle 26 Sprachen wurden mit den neuen Scheidungs-Parametern und Fehlermeldungen aktualisiert.
*   **Labels & Messages**: Neue Labels für "Scheidung prüfen" und spezifische Meldungen für Partner-Ereignisse integriert.

## 🚀 Ausblick & Nächste Schritte
*   **"Likely Dead" Heuristik**: Implementierung eines optionalen Checks für Personen über 110 Jahre ohne Sterbedatum.
*   **Inzest-Check**: Entwicklung einer optionalen Prüfung für Ehen zwischen nahen Verwandten.
*   **Quick-Fix Integration**: Planung von UI-Elementen in der Analyse-Tabelle zur Schnellkorrektur.
*   **Performance**: Lokales Caching von Validierungsergebnissen zur Reduzierung von Server-Anfragen.
