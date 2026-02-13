# webtrees Datencheck Plugin

Ein leistungsstarkes webtrees-Modul zur erweiterten Überprüfung, Validierung und Bereinigung genealogischer Daten. Dieses Plugin wurde entwickelt, um über die Standard-Plausibilitätsprüfungen hinaus sicherzustellen, dass Ihr Stammbaum höchsten Qualitätsstandards entspricht.

![Screenshot](https://raw.githubusercontent.com/Vulfharban/webtrees-datencheck-plugin/main/resources/images/datencheck_icon.png)

## 🌟 Hauptmerkmale

### 🔍 Intelligente Dubletten-Erkennung
Vermeiden Sie doppelt angelegte Personen bereits im Entstehungsprozess.
*   **Echtzeit-Validierung:** Schon während der Dateneingabe (Name, Geburtsdatum) sucht das System im Hintergrund nach potenziellen Treffern.
*   **Phonetik & Fuzzy-Match:** Nutzt die **Kölner Phonetik** für deutsche Namen und die **Levenshtein-Distanz** für Tippfehler-Toleranz. So werden auch "Meier" und "Maier" oder "Christoph" und "Kristof" erkannt.
*   **Kontext-Analyse:** Das System vergleicht nicht nur Namen, sondern auch Eltern und Geschwister, um die Genauigkeit bei häufig vorkommenden Namen zu erhöhen.
*   **Interaktiver Vergleich:** Ein detailliertes "Side-by-Side"-Modal erlaubt den direkten Vergleich zwischen dem neuen Datensatz und bestehenden Personen oder Familien, bevor eine Dublette entsteht.
*   **Familien-Zusammenführung:** Erkennt, wenn für ein Elternpaar bereits eine Familie existiert, und ermöglicht die direkte Verknüpfung statt einer Neuanlage.

### ✅ Erweiterte Plausibilitätsprüfungen
Umfangreiche Regeln zur Identifizierung biologischer und logischer Unstimmigkeiten.
*   **Biologische Grenzen:**
    *   **Alters-Schwellenwerte:** Warnungen bei Eltern, die bei der Geburt ungewöhnlich jung (<14) oder alt (>50 bei Müttern, >80 bei Vätern) waren (konfigurierbar).
    *   **Posthume Geburten:** Erkennt Geburten nach dem Tod des Vaters (bis zu 9 Monate danach zulässig) oder der Mutter (unmöglich).
*   **Zeitliche Logik:**
    *   **Lebensereignisse:** Prüft die korrekte Reihenfolge: Geburt → Taufe → Heirat → Tod → Bestattung.
    *   **Tauf-Check:** Neue Warnung, wenn eine Taufe ungewöhnlich spät (z.B. nach mehr als 30 Tagen, konfigurierbar) nach der Geburt stattfindet – wichtig für die Identifizierung von Erwachsentaufen oder späten Quellen.
    *   **Lebensspanne:** Anpassbare Warnung bei extremem Alter (z.B. >120 Jahre).
*   **Namens- & Formalkonsistenz:**
    *   Prüft auf fehlende Nachnamen oder Unstimmigkeiten zwischen Kindern und Vätern.
    *   **Alias- & Genannt-Namen:** Unterstützung für westfälische Genannt-Namen ("gen.", "vulgo"), polnische/lateinische Aliase ("vel", "alias") und weitere Namensvarianten.
    *   **Internationale Regeln:** Unterstützung für skandinavische Patronymika (-sen/-datter), slawische Endungen (-ski/-ska), spanische Doppelnamen und niederländische Tussenvoegsels.

### 📊 Analyse-Dashboard & Reporting
Behalten Sie den Überblick über die Datenqualität Ihres gesamten Stammbaums.
*   **Bulk-Analyse:** Scannt den kompletten Baum in effizienten Chunks (auch für sehr große Bäume geeignet).
*   **Fehler-Management:** Markieren Sie "False Positives" als ignoriert, damit diese nicht erneut gemeldet werden.
*   **Export:** Laden Sie alle gefundenen Probleme als CSV-Datei für die externe Bearbeitung herunter.
*   **Forschungs-Integration:** Erstellen Sie mit einem Klick webtrees-Forschungsaufgaben (`_TODO`) direkt aus einem Fehlerbericht.

## ⚙️ Konfiguration

Das Modul ist hochgradig anpassbar. Unter **Veraltung > Datencheck > Einstellungen** können Sie festlegen:
*   **Benutzer-spezifische Einstellungen:** Jeder Administrator/Moderator kann seine eigenen Toleranzgrenzen und aktiven Prüfungen speichern, ohne andere Benutzer zu beeinflussen.
*   **Toleranzgrenzen:** Justieren Sie die Fuzzy-Logik für Namen und Datumsabweichungen.
*   **Feature-Toggles:** Aktivieren oder Deaktivieren Sie spezifische Prüfmodule (z.B. Geografie-Check, Quellen-Check).

## 🚀 Installation & Voraussetzungen

### Anforderungen
*   **webtrees 2.1+**
*   PHP 7.4 oder höher (voll kompatibel mit PHP 8.x)
*   Standard-Datenbank-Unterstützung von webtrees (MySQL/MariaDB)

### Manuelle Installation
1.  Laden Sie das neueste Release (`.zip`) von der [GitHub-Releases-Seite](https://github.com/Vulfharban/webtrees-datencheck-plugin/releases) herunter.
2.  Entpacken Sie den Inhalt in Ihr webtrees-Verzeichnis unter `modules_v4/webtrees-datencheck-plugin`.
3.  Aktivieren Sie das Modul im webtrees-Adminbereich unter **Module > Modulverwaltung**.

## 🌍 Internationalisierung
Das Modul ist vollständig übersetzbar und unterstützt aktuell 16+ Sprachen, darunter Deutsch, Englisch, Französisch, Niederländisch, Spanisch und viele mehr.

## 📄 Lizenz
Veröffentlicht unter der MIT Lizenz. Siehe `LICENSE` für weitere Informationen.

---

# English Version

## webtrees Datencheck Plugin

A powerful webtrees module for advanced verification, validation, and cleanup of genealogical data. This plugin is designed to go beyond standard plausibility checks to ensure your family tree meets the highest quality standards.

## 🌟 Key Features

### 🔍 Intelligent Duplicate Detection
Prevent duplicate individuals before they are even created.
*   **Real-time Validation:** While entering data (name, birth date), the system searches in the background for potential matches.
*   **Phonetics & Fuzzy Matching:** Utilizes **Cologne Phonetic** for German names and **Levenshtein Distance** for typo tolerance (e.g., catching "Smith" vs. "Smyth").
*   **Contextual Analysis:** Compares not only names but also parents and sibling constellations to increase accuracy for common names.
*   **Interactive Comparison:** A detailed "side-by-side" modal allows for direct comparison between the new record and existing individuals or families before a duplicate is created.
*   **Family Merging:** Detects if a family already exists for a pair of parents and allows for direct linking instead of creating a new family record.

### ✅ Advanced Plausibility Checks
Comprehensive rules to identify biological and logical inconsistencies.
*   **Biological Limits:**
    *   **Age Thresholds:** Warnings for parents who were unusually young (<14) or old (>50 for mothers, >80 for fathers) at the time of birth (fully configurable).
    *   **Posthumous Births:** Detects births occurring after the father's death (up to 9 months allowed) or the mother's death.
*   **Temporal Logic:**
    *   **Life Events:** Verifies the correct chronological order: Birth → Baptism → Marriage → Death → Burial.
    *   **Baptism Check:** New warning for unusually late baptisms (e.g., more than 30 days after birth, configurable) – helpful for identifying adult baptisms or delayed records.
    *   **Lifespan:** Customizable warnings for extreme ages (e.g., >120 years).
*   **Naming & Formal Consistency:**
    *   Checks for missing surnames or inconsistencies between children and fathers.
    *   **Alias & Alias Names:** Support for German "Genannt-Namen" ("gen.", "vulgo"), Polish/Latin aliases ("vel", "alias"), and other naming variants.
    *   **International Conventions:** Support for Scandinavian patronymics (-sen/-datter), Slavic gendered endings (-ski/-ska), Spanish double surnames, and Dutch "tussenvoegsels".

### 📊 Analysis Dashboard & Reporting
Maintain an overview of the data quality of your entire family tree.
*   **Bulk Analysis:** Scans the entire tree in efficient chunks (suitable for very large trees).
*   **Issue Management:** Mark "false positives" as ignored so they don't appear in future reports.
*   **Export:** Download all identified issues as a CSV file for external processing.
*   **Workflow Integration:** Create webtrees research tasks (`_TODO`) with a single click directly from an error report.

## ⚙️ Configuration

The module is highly customizable. Under **Control Panel > Datencheck > Settings**, you can define:
*   **User-Specific Settings:** Each administrator/moderator can save their own tolerance limits and active checks without affecting other users.
*   **Tolerance Thresholds:** Adjust fuzzy logic for names and date deviations.
*   **Feature Toggles:** Enable or disable specific check modules (e.g., Geographic check, Source check).

## 🚀 Installation & Requirements

### Requirements
*   **webtrees 2.1+**
*   PHP 7.4 or higher (fully compatible with PHP 8.x)
*   Standard webtrees database support (MySQL/MariaDB)

### Manual Installation
1.  Download the latest release (`.zip`) from the [GitHub Releases page](https://github.com/Vulfharban/webtrees-datencheck-plugin/releases).
2.  Extract the content into your webtrees directory under `modules_v4/webtrees-datencheck-plugin`.
3.  Enable the module in the webtrees admin area under **Modules > Module management**.

## 🌍 Internationalization
The module is fully translatable and currently supports 16+ languages, including German, English, French, Dutch, Spanish, and many more.

## 📄 License
Released under the MIT License. See `LICENSE` for more information.
