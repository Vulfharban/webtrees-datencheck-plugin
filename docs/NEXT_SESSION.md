# Webtrees Datencheck Plugin - Next Steps
- **Version:** 0.8.0 (Integrated Ignore & Task Features)
- **Status:** Feature Complete. Codebase stable.

## Current Status (2026-02-07)
- **✅ Version 0.8.0 - ABGESCHLOSSEN**
- **✅ Features:** Validierung, Dubletten-Check, Side-by-Side Modal, Konfiguration, Menü-Integration, Fehler Ignorieren, Admin-Verwaltung.

## Completed Tasks (Phase 0.8.0)

- [x] **Automatic Schema Creation**: Implemented `SchemaService` to ensure `datencheck_ignored` table exists.
- [x] **Error Codes**: Assigned unique codes (e.g. `MOTHER_TOO_YOUNG`) to all validation checks.
- [x] **Ignore Service**: Created `IgnoredErrorService` to handle database interactions (add, check, list, relieve).
- [x] **Frontend Integration**: Added "Ignore" button to error popups.
- [x] **Backend Logic**: Updated `ValidationService` to filter out ignored errors.
- [x] **Admin Interface**: Created a page to list and manage ignored errors (`AdminIgnored`).
- [x] **Access Control**: Only Moderators/Managers can ignore/delete errors.
- [x] **I18N**: German/English labels for error codes.

## Nächste Session: Bulk-Analyse & Reporting

## Aktueller Status (v0.9.1) - 2026-02-08
- **Bulk-Analyse:**
    - Vollständig implementiert und getestet.
    - **Filterung:** Ergebnisse nach Typ/Schweregrad filterbar (Client-Side).
    - Robustes Error-Handling.
- **Sources API:**
    - **Gelöst:** Robuster Check via `$fact->gedcom()` und `$fact->attribute('SOUR')` funktioniert zuverlässig.
    - Keine False Positives mehr bei existierenden Quellen.

## Offene Probleme / Bugs
- (Keine kritischen Bugs bekannt)

## Ziele für die nächste Session
1.  **Export:**
    - Ergebnisse als CSV oder PDF herunterladen.
2.  **Performance & Optimierung:**
    - Caching für Geodaten bei Bulk-Analyse (vermeidet unnötige API Calls).
    - Testen mit sehr großen Bäumen (100k+).
3.  **Feinschliff:**
    - Datums-Formatierung in der Ergebnis-Tabelle vereinheitlichen.
    - Orts-Standardisierung (Vorschläge für Korrekturen).

### 3. Geografische Validierung (Advanced)
- Echte Distanzberechnung (km) zwischen Ereignissen.

