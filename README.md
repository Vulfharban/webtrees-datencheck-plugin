# webtrees Datencheck Plugin

Ein webtrees-Modul zur erweiterten Überprüfung und Validierung von genealogischen Daten.

Dieses Plugin bietet leistungsstarke Werkzeuge zur Identifizierung von Dubletten, logischen Fehlern und fehlenden Daten in Ihrem Stammbaum, die über die Standardfunktionen von webtrees hinausgehen.

![Screenshot](https://raw.githubusercontent.com/Vulfharban/webtrees-datencheck-plugin/main/resources/images/datencheck_icon.png)

## Funktionen

### 🔍 Tiefgehende Dubletten-Erkennung
- **Echtzeit-Warnungen** beim Erstellen von Personen.
- **Phonetischer Abgleich** (Kölner Phonetik & Levenshtein-Distanz) findet ähnlich klingende Namen.
- **Familien-Kontext**: Prüft nicht nur den Namen, sondern auch Eltern und Geschwisterkonstellationen.
- **Side-by-Side Vergleich**: Detailliertes Modal zum Vergleich von Dubletten-Kandidaten.

### ✅ Erweiterte Validierung (Plausibilitäts-Checks)
- **Biologische Plausibilität**:
  - Warnung bei Eltern, die bei der Geburt ungewöhnlich jung (<14) oder alt (>50/80) waren.
  - Erkennung von Geburten nach dem Tod der Eltern (unter Berücksichtigung posthumer Geburten).
- **Zeitliche Logik**:
  - Heirat vor Geburt oder nach Tod.
  - Bestattung vor Tod oder Taufe vor Geburt.
- **Namens-Konsistenz**: Prüft auf fehlende Nachnamen oder Unstimmigkeiten zum Vater.
- **Quellen-Prüfung**: Markiert wichtige Ereignisse (Geburt, Tod, Ehe) ohne Quellenangabe.

### 📊 Bulk-Analyse & Reporting
- **Gesamt-Check**: Prüfen Sie Ihren gesamten Stammbaum auf einmal.
- **CSV-Export**: Laden Sie die Fehlerliste als Excel-kompatible CSV-Datei herunter.
- **Fortschrittsanzeige**: Robuste Verarbeitung auch bei großen Bäumen (Chunking).


### 🛠️ Workflow-Tools
- **Familien-Zusammenführung**: Einfaches Verlinken von Eltern zu existierenden Familien.
- **Ignore-Liste**: Markieren Sie "False Positives" als ignoriert, damit sie nicht mehr auftauchen.
- **Forschungsaufgaben (Todo)**: Erstellen Sie mit einem Klick webtrees-Forschungsaufgaben (_TODO) direkt aus dem Fehler-Protokoll.
- **Vollständige Internationalisierung**: Unterstützung für 16 Sprachen (inkl. Isländisch), inklusive aller interaktiven Elemente und Fehlermeldungen.
- **Automatische Updates**: Benachrichtigung bei neuen Versionen direkt im Dashboard.

## Installation

### Manuell (Empfohlen)
1. Laden Sie die neueste Version von der [Releases-Seite](https://github.com/Vulfharban/webtrees-datencheck-plugin/releases) herunter.
2. Entpacken Sie den Ordner in das Verzeichnis `modules_v4/` Ihrer webtrees-Installation.
3. Der Ordnername sollte `webtrees-datencheck-plugin` (oder ähnlich) lauten.
4. Gehen Sie im webtrees-Adminbereich zu **Module** und aktivieren Sie "Datencheck".

### Via Git
```bash
cd modules_v4/
git clone https://github.com/Vulfharban/webtrees-datencheck-plugin.git datencheck
```

## Konfiguration

Das Modul kann unter **Verwaltung > Datencheck > Einstellungen** konfiguriert werden:
- Passen Sie Toleranzgrenzen für Fuzzy-Suche an.
- Definieren Sie Altersgrenzen (z.B. Mindestalter für Eltern).
- Aktivieren/Deaktivieren Sie einzelne Prüfungskategorien (z.B. Geografie, Quellen).

## Voraussetzungen

- **webtrees 2.1+**
- PHP 7.4 oder höher

## Lizenz

Dieses Projekt ist unter der MIT Lizenz veröffentlicht. Siehe `LICENSE` Datei für Details.

## Feedback & Support

Fehler gefunden oder Ideen für neue Features? Erstellen Sie gerne ein [Issue](https://github.com/Vulfharban/webtrees-datencheck-plugin/issues) auf GitHub.

---

# English Version

## webtrees Datencheck Plugin

A webtrees module for advanced validation and verification of genealogical data.

This plugin provides powerful tools to identify duplicates, logical errors, and missing data in your family tree, extending the standard capabilities of webtrees.

## Features

### 🔍 Deep Duplicate Detection
- **Real-time Warnings** when creating new individuals.
- **Phonetic Matching** (Cologne Phonetic & Levenshtein Distance) finds similar-sounding names.
- **Family Context**: Checks not only names but also parents and sibling constellations.
- **Side-by-Side Comparison**: Detailed modal for comparing duplicate candidates.

### ✅ Advanced Validation (Plausibility Checks)
- **Biological Plausibility**:
  - Warns about parents who were unusually young (<14) or old (>50/80) at the time of birth.
  - Detects births occurring after the death of parents (accounting for posthumous births).
- **Temporal Logic**:
  - Marriage before birth or after death.
  - Burial before death or baptism before birth.
- **Name Consistency**: Checks for missing surnames or inconsistencies with the father's surname.
- **Source Verification**: Flags key life events (birth, death, marriage) missing source citations.

### 📊 Bulk Analysis & Reporting
- **Full Tree Check**: Scan your entire family tree at once.
- **CSV Export**: Download the error list as an Excel-compatible CSV file.
- **Progress Tracking**: Robust processing even for large trees (using chunking).

### 🛠️ Workflow Tools
- **Family Merging**: Easily link parents to existing families.
- **Ignore List**: Mark "False Positives" as ignored so they don't reappear.
- **Research Tasks (Todo)**: Create webtrees research tasks (_TODO) with a single click directly from the validation log.
- **Full Internationalization**: Support for 16 languages (incl. Icelandic), including all interactive elements and error messages.
- **Automatic Updates**: Notifications about new versions directly in the dashboard.

## Installation

### Manual (Recommended)
1. Download the latest version from the [Releases Page](https://github.com/Vulfharban/webtrees-datencheck-plugin/releases).
2. Unzip the folder into the `modules_v4/` directory of your webtrees installation.
3. The folder name should be `webtrees-datencheck-plugin` (or similar).
4. Go to **Modules** in the webtrees admin area and enable "Datencheck".

### Via Git
```bash
cd modules_v4/
git clone https://github.com/Vulfharban/webtrees-datencheck-plugin.git datencheck
```

## Configuration

The module can be configured under **Control Panel > Datencheck > Settings**:
- Adjust tolerance thresholds for fuzzy search.
- Define age limits (e.g., minimum age for parents).
- Enable/Disable specific check categories (e.g., Geography, Sources).

## Requirements

- **webtrees 2.1+**
- PHP 7.4 or higher

## License

This project is released under the MIT License. See `LICENSE` file for details.

## Feedback & Support

Found a bug or have an idea for a new feature? Feel free to create an [Issue](https://github.com/Vulfharban/webtrees-datencheck-plugin/issues) on GitHub.
