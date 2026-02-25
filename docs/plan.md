# Webtrees Datencheck Plugin - Projektplan

## Projektziel
Entwicklung eines Plugins für webtrees, das Dateninkonsistenzen verhindert und die Erfassung von Dubletten während der Eingabe unterdrückt.

**Status:** Version 1.5.1 - **Stable** (Live Source & Repo Check, Keyword Expansion)

## Kernfunktionen

### 1. Interaktive Validierung (Live-Checks)
- [x] **Plausibilitätsprüfung**: Alter der Eltern, Geburts-/Sterbedaten-Reihenfolge.
- [x] **Dubletten-Erkennung**: Suche nach ähnlichen Personen während der Eingabe.
- [x] **Geschwister-Check**: Warnung bei zu geringem Geburtsabstand.
- [x] **Tauf-/Bestattungs-Check**: Logik für religiöse Ereignisse vor/nach Geburt/Tod.
- [x] **Zukunftsdatum-Schutz**: Verhindert die Eingabe von Daten in der Zukunft.
- [x] **Geschlechts-Validierung**: Warnung bei fehlendem Geschlecht oder Mismatch zum Vornamen.
- [x] **Scheidungs-Validierung**: Chronologie-Prüfungen für Scheidungen und Berücksichtigung bei Ehe-Überlappung.

### 2. Globale Analyse (Bulk-Check)
- [x] **Scan des gesamten Baums**: Auflistung aller Fehler in einer Tabelle.
- [x] **CSV-Export**: Download der Ergebnisse für externe Bearbeitung.
- [x] **Fehler ignorieren**: Möglichkeit, Fehlalarme dauerhaft auszublenden.

### 3. Namens-Intelligenz
- [x] **Phonetische Suche**: Kölner Phonetik für Namensvarianten.
- [x] **Globale Äquivalente**: Erkennt "Johann" = "Jan" = "John" etc.
- [x] **Geschlechts-Heuristik**: Erkennt typisch weibliche Endungen (a/e).

### 4. Erweiterte Analysen (Geplant/In Arbeit)
- [ ] **"Likely Dead" Heuristik**:
    - *Status:* **Teilweise implementiert** (Max. Lebensspanne 120J wird bereits geprüft).
    - *Offen:* Heuristik für fehlende Sterbedaten (>110J) und Prüfung letzter Lebenszeichen.
- [ ] **Erweiterte Quellenprüfung**:
    - *Status:* **Teilweise implementiert** (Check auf komplett fehlende Quellen existiert).
    - *Offen:* Qualitative Prüfung (Repositories, Seitenzahlen, Konsistenz Check).
- [ ] **Quick-Fix Buttons**:
    - *Status:* noch nicht gestartet.
- [ ] **Generations-Statistiken**:
    - *Status:* **Teilweise implementiert** (Biologisches Alter der Eltern wird geprüft).
    - *Offen:* Statistische Ausreißer und extreme Geschwisterabstände.
- [ ] **Familien-Dubletten**:
    - *Status:* **Teilweise implementiert** (Interaktiver Check bei Neuanlage existiert).
    - *Offen:* Bulk-Analyse für "gesplittete" Familien im gesamten Baum.

### 5. Quellencheck & Archive
- [x] **Quellen-Duplikatssuche (Live)**: Suche nach ähnlichen Quellen während der Eingabe (inkl. Übersetzung, Autoren-Check & Zeichen-Toleranz).
- [x] **Archiv-Duplikatssuche (Live)**: Echtzeit-Check für Archive/Repositories zur Konsolidierung der Quellenverwaltung.
- [x] **Bilinguales Keyword-Mapping**: Erkennt Äquivalente in DE/EN für 20+ Kategorien (Militär, Census, Standesamt etc.).

## Technische Basis
- **Sprache**: PHP 7.4+ (natives webtrees-Modul)
- **Framework**: webtrees 2.1+ / 2.2+
- **Datenbank**: MySQL/MariaDB (für ignorierte Fehler)
