# Webtrees Datencheck Plugin - Projektplan

## Projektziel
Entwicklung eines Plugins für webtrees, das Dateninkonsistenzen verhindert und die Erfassung von Dubletten während der Eingabe unterdrückt.

**Status:** Version 1.3.11 - **Stable** (Divorce Validation & Marriage Logic)

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

### 4. Erweiterte Analysen (Geplant)
- [ ] **"Likely Dead" Heuristik** (Optional): Warnung bei fehlendem Sterbedatum (>110 Jahre).
- [ ] **Inzest-Check** (Optional): Prüfung auf zu nahe Verwandtschaftsverhältnisse in Ehen.
- [ ] **Erweiterte Quellenprüfung** (Optional): Tiefenprüfung der Belegqualität und Konsistenz.
- [ ] **Quick-Fix Buttons**: Direkte Korrektur einfacher Zeitfehler in der Analyse-Tabelle.
- [ ] **Generations-Statistiken**: Erkennung von Ausreißern beim Alter bei Erstgeburt.
- [ ] **Familien-Dubletten**: Identifikation doppelt angelegter Partnerschaften (Elternpaar-Matching).

## Technische Basis
- **Sprache**: PHP 7.4+ (natives webtrees-Modul)
- **Framework**: webtrees 2.1+ / 2.2+
- **Datenbank**: MySQL/MariaDB (für ignorierte Fehler)
