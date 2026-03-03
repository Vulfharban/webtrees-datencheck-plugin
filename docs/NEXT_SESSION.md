# Nächste Sitzung - Planung

## Aktueller Stand (v1.5.9)
*   **Quick-Fix Buttons**: Vollständig implementiert für gängige Datumsfehler (Taufe vor Geburt etc.) und "Wahrscheinlich verstorben".
*   **GEDCOM-Standardprüfung**: Vollständig implementiert (BIRT, DEAT, BAPM, BURI, SEX).
*   **Info-Kategorie**: Neue blaue Kategorie für redaktionelle Hinweise zur Datenpflege eingeführt.
*   **Lokalisierung**: Alle 27 Sprachdateien sind auf dem neuesten Stand.
*   **Build-System**: Das PowerShell-Build-Skript ist nun robust gegen Pfad-Variationen und stellt die Ordnerstruktur korrekt wieder her.

## Offene Punkte & Ideen
1.  **Erweiterte Quellenprüfung (Qualität)**:
    *   Überprüfung auf konsistente Verwendung von Quellentypen (z.B. Geburtsurkunde für Geburtsfakt).
    *   Erkennung von fehlenden Seitenzahlen (`PAGE`) in Quellenzitaten.
2.  **Hierarchie & Statistik**:
    *   Prüfung auf statistische Ausreißer im Stammbaum (z.B. extreme Abstände zwischen Geschwistern über 10 Jahre ohne Lücke).
3.  **Familien-Zusammenführung (Bulk)**:
    *   Globale Analyse zur Identifizierung von gesplitteten Familien (gleiche Eltern, verschiedene Familien-Records).
4.  **Lokalisierung**:
    *   Vervollständigung der Übersetzungen für die neu integrierten 22 Sprachen (aktuell Englisch-Fallback).
    *   **Zuletzt integriert (v1.5.8)**: `bs`, `cy`, `et`, `eu`, `fa`, `gl`, `hy`, `id`, `ja`, `ka`, `kk`, `ko`, `lt`, `lv`, `ms`, `nb`, `pt-BR`, `sl`, `sq`, `sr`, `tr`, `uz`, `vi`.
    *   **Noch fehlende webtrees-Sprachen**: `af`, `ar`, `dv`, `fo`, `hi`, `jv`, `ln`, `mi`, `mr`, `ne`, `nn`, `oc`, `su`, `sw`, `ta`, `th`, `tl`, `tt`, `ur`, `yi`, `zh-Hans`, `zh-Hant`.

## Erledigte Aufgaben (heute)
*   Feature: Quick-Fix Buttons (v1.5.9) für Datums-Tausch (Swap) und Sterbe-Markierung.
*   Backend: `ActionService::swapDates` implementiert, um GEDCOM-Fakten unter Erhalt von Zusatzdaten (PLAC, SOUR) zu korrigieren.
*   Translation: Massives i18n-Update (v1.5.8 & v1.5.9).
*   Translation: Integration von 22 neuen Sprachen (insges. 49).
*   Translation: Umstellung Norwegisch auf Standard-Code `nb`.
*   Translation: Vollständige Korrektur aller `sprintf`-Platzhalter in allen Sprachdateien.
*   Feature: Interaktive Dublettensuche für Repositorien und Quellen technisch übersetzbar gemacht.
*   Refactoring: Alle Log-Meldungen und UI-Strings in Services (`DatabaseService.php`) global in `I18N::translate()` gekapselt.
*   Feature: Deaktivierbares Menü-Icon übersetzt.
