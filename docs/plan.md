# Webtrees Datencheck Plugin - Projektplan

## Projektziel
Entwicklung eines Plugins für webtrees, das Dateninkonsistenzen verhindert und die Erfassung von Dubletten während der Eingabe unterdrückt.

**Status:** Version 1.3.8 - **Stable** (Gender Heuristics & Fixes)

## Kernfunktionen

### 1. Interaktive Validierung (Live-Checks)
- [x] **Plausibilitätsprüfung**: Alter der Eltern, Geburts-/Sterbedaten-Reihenfolge.
- [x] **Dubletten-Erkennung**: Suche nach ähnlichen Personen während der Eingabe.
- [x] **Geschwister-Check**: Warnung bei zu geringem Geburtsabstand.
- [x] **Tauf-/Bestattungs-Check**: Logik für religiöse Ereignisse vor/nach Geburt/Tod.
- [x] **Zukunftsdatum-Schutz**: Verhindert die Eingabe von Daten in der Zukunft.
- [x] **Geschlechts-Validierung**: Warnung bei fehlendem Geschlecht oder Mismatch zum Vornamen.

### 2. Globale Analyse (Bulk-Check)
- [x] **Scan des gesamten Baums**: Auflistung aller Fehler in einer Tabelle.
- [x] **CSV-Export**: Download der Ergebnisse für externe Bearbeitung.
- [x] **Fehler ignorieren**: Möglichkeit, Fehlalarme dauerhaft auszublenden.

### 3. Namens-Intelligenz
- [x] **Phonetische Suche**: Kölner Phonetik für Namensvarianten.
- [x] **Globale Äquivalente**: Erkennt "Johann" = "Jan" = "John" etc.
- [x] **Geschlechts-Heuristik**: Erkennt typisch weibliche Endungen (a/e).

## Technische Basis
- **Sprache**: PHP 7.4+ (natives webtrees-Modul)
- **Framework**: webtrees 2.1+ / 2.2+
- **Datenbank**: MySQL/MariaDB (für ignorierte Fehler)
