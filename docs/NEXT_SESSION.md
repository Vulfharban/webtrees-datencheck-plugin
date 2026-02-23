# Webtrees Datencheck Plugin - Projektstatus & Roadmap

- **Aktuelle Version:** 1.3.12 (Stabil)
- **Status:** 502-Fix (Batch-Größe), Fehlalarm-Reduktion (Ehen), robuste Datums-Logik.

### 1. Performance & Stabilität (v1.3.12)
*   **502 Bad Gateway Fix**: Batch-Größe der Kwalitätsanalyse von 100 auf 50 reduziert, um Timeouts auf Online-Servern zu vermeiden.
*   **Intelligente Ehe-Logik**: Überlappungs-Checks berücksichtigen nun die Präzision (z.B. "vor 1888"). Fehlalarme bei ungenauen Daten werden unterdrückt (Downgrade auf Warnung/Info).
*   **Verbesserte Fehlermeldungen**: Datumsangaben in Meldungen nutzen nun das Anzeigeformat (z.B. "vor 1888" statt nur "1888").

### 2. Scheidungs-Validierung & i18n (v1.3.11)
*   **Vollständige i18n**: Alle 26 Sprachen unterstützen nun die neuen Scheidungs-Features und Fehlermeldungen.
*   **Labels & Messages**: Neue Labels für "Scheidung prüfen" und spezifische Meldungen für Partner-Ereignisse integriert.

## 🚀 Ausblick & Nächste Schritte
*   **"Likely Dead" Heuristik**: Implementierung eines optionalen Checks für Personen über 110 Jahre ohne Sterbedatum.
*   **Inzest-Check**: Entwicklung einer optionalen Prüfung für Ehen zwischen nahen Verwandten.
*   **Quick-Fix Integration**: Planung von UI-Elementen in der Analyse-Tabelle zur Schnellkorrektur.
*   **Performance**: Lokales Caching von Validierungsergebnissen zur Reduzierung von Server-Anfragen.
