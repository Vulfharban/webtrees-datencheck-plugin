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
