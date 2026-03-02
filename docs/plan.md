# Webtrees Datencheck Plugin - Projektplan

## Projektziel
Entwicklung eines Plugins für webtrees, das Dateninkonsistenzen verhindert und die Erfassung von Dubletten während der Eingabe unterdrückt.

**Status:** Version 1.5.7 - **Stable** (Check GEDCOM & Fixes)

## Kernfunktionen

### 1. Interaktive Validierung (Live-Checks)
- [x] **Plausibilitätsprüfung**: Alter der Eltern, Geburts-/Sterbedaten-Reihenfolge.
- [x] **Dubletten-Erkennung**: Suche nach ähnlichen Personen während der Eingabe.
- [x] **Geschwister-Check**: Warnung bei zu geringem Geburtsabstand.
- [x] **Tauf-/Bestattungs-Check**: Logik für religiöse Ereignisse vor/nach Geburt/Tod.
- [x] **Zukunftsdatum-Schutz**: Verhindert die Eingabe von Daten in der Zukunft.
- [x] **Geschlechts-Validierung**: Warnung bei fehlendem Geschlecht oder Mismatch zum Vornamen.
- [x] **Scheidungs-Validierung**: Chronologie-Prüfungen für Scheidungen und Berücksichtigung bei Ehe-Überlappung.
- [x] **Check GEDCOM (Mehrfache Ereignisse)**: Identifikation redundanter Fakten (Info-Level).

### 2. Globale Analyse (Bulk-Check)
- [x] **Scan des gesamten Baums**: Auflistung aller Fehler in einer Tabelle.
- [x] **CSV-Export**: Download der Ergebnisse für externe Bearbeitung.
- [x] **Fehler ignorieren**: Möglichkeit, Fehlalarme dauerhaft auszublenden.
- [x] **Filterung**: Auswahl bestimmter Fehlerklassen für gezielte Analysen (inkl. GEDCOM-Standard).

### 3. Namens-Intelligenz
- [x] **Phonetische Suche**: Kölner Phonetik für Namensvarianten.
- [x] **Globale Äquivalente**: Erkennt "Johann" = "Jan" = "John" etc.
- [x] **Geschlechts-Heuristik**: Erkennt typisch weibliche Endungen (a/e).

### 4. Erweiterte Analysen (Geplant/In Arbeit)
- [x] **"Likely Dead" Heuristik**:
    - *Status:* **Vollständig implementiert** (Check auf fehlende Sterbedaten >110J inkl. letzter Lebenszeichen).
- [x] **Verwaiste Fakten**:
    - *Status:* **Vollständig implementiert** (Prüfung auf biografische Ereignisse außerhalb der Lebensspanne inkl. technischer Blacklist).
- [ ] **Erweiterte Quellenprüfung**:
    - *Status:* **Teilweise implementiert** (Check auf komplett fehlende Quellen existiert).
    - *Offen:* Qualitative Prüfung (Repositories, Seitenzahlen, Konsistenz Check).
- [ ] **Quick-Fix Buttons**:
    - *Status:* noch nicht gestartet.
- [ ] **Hierarchie-Checks**: Statistische Prüfung auf biologische Ausreißer (z.B. Elternteil zu jung/alt bei Geburt).
- [x] **Familien-Dubletten**:
    - *Status:* **Vollständig implementiert** (Interaktiver Check bei Neuanlage & Bulk-Check GEDCOM-Standard).

### 5. Quellencheck & Archive
- [x] **Quellen-Duplikatssuche (Live)**: Suche nach ähnlichen Quellen während der Eingabe (inkl. Übersetzung, Autoren-Check & Zeichen-Toleranz).
- [x] **Archiv-Duplikatssuche (Live)**: Echtzeit-Check für Archive/Repositories zur Konsolidierung der Quellenverwaltung.
- [x] **Bilinguales Keyword-Mapping**: Erkennt Äquivalente in DE/EN für 20+ Kategorien (Militär, Census, Standesamt etc.).

## Technische Basis
- **Sprache**: PHP 7.4+ (natives webtrees-Modul)
- **Framework**: webtrees 2.1+ / 2.2+
- **Datenbank**: MySQL/MariaDB (für ignorierte Fehler)
