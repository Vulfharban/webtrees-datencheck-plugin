Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

## [1.6.7] - 2026-03-24
### Geändert
- **Listen-Icon**: Optimierte Darstellung des Modul-Icons für Desktop-Menüs (3,4rem). Das Icon wird nun zentriert über dem Text angezeigt, um sich nahtlos in Themes mit 3D-Icons einzufügen.

## [1.6.6] - 2026-03-24
### Geändert
- **Namen-Sofortsuche**: Dubletten werden jetzt auch dann gesucht, wenn das Geburtsdatum noch leer ist. Dies beschleunigt die Erkennung während der ersten Dateneingabe erheblich.
- **Optimierte Trefferquote**: Verbesserung der Dublettenerkennung bei unvollständigen Datensätzen und Neuanlagen.

## [1.6.5] - 2026-03-24
### Behoben
- **Dublettenerkennung**: Fix für Dubletten-Suche bei unterschiedlicher Schreibweise des Geschlechts (M/m, F/f).
- **Robuste Feld-Erkennung**: Verbesserte JS-Erkennung der Namensfelder (GIVN, SURN) zur Vermeidung von Konflikten mit versteckten Platzhalterfeldern einiger Themes.
- **Datenbank-Kompatibilität**: Case-Insensitive Dubletten-Suche durch erzwungenes Kleinfomat (LOWER) in SQL-Abfragen.
- **PHP-Kompatibilität**: Erhöhung der Abwärtskompatibilität für PHP 7.4 (Vermeidung von str_starts_with / str_ends_with).

## [1.6.4] - 2026-03-24
### Hinzugefügt
- **Automatisierte Icon-Wahl**: Das Menü-Icon wird nun automatisch basierend auf der Helligkeit des Themes ausgewählt (Helles Icon für dunkle Themes, transparentes Icon für helle Themes). Die manuelle Konfiguration entfällt.
- **Doppelnamen-Optimierung**: Verbesserte Dublettenerkennung für Personen mit Bindestrich-Nachnamen oder mehreren Nachnamen (spanische Konventionen). Suchbegriffe werden nun intelligent geteilt und einzeln abgeglichen.
- **UI-Stabilität**: Das Dubletten-Warnfenster verfügt nun über einen Schließen-Button und bleibt auch beim Wechseln von Eingabefeldern sichtbar.
- **Erweiterte Feld-Erkennung**: Unterstützung für zusätzliche Namensfelder (Suffix, Volltext-NAME) als Auslöser für die Dublettenprüfung.

## [1.6.3] - 2026-03-19
### Hinzugefügt
- **Menü-Icon Stile**: Einführung von 4 wählbaren Icon-Stilen (Standard, Transparent, Hell, Kein Icon) in den Einstellungen für bessere Kompatibilität mit verschiedenen Themes.
- **Dunkle Themes**: Neues kontrastreiches Icon-Set ("Hell") für webtrees-Themes mit dunklem Hintergrund.
- **Admin-Vorschau**: Interaktive Live-Vorschau in den Modul-Einstellungen mit Hintergrund-Umschalter zur Prüfung der Lesbarkeit.
### Behoben
- **Such-Trigger**: Die automatische Dublettenprüfung wurde auf Suchseiten (Phonetische Suche, Erweiterte Suche) deaktiviert, um Performance zu sparen und unnötige Hintergrund-Anfragen zu vermeiden.

## [1.6.2] - 2026-03-04
### Geändert
- **ZIP-Struktur**: Das Release-ZIP enthält nun den übergeordneten Ordner `webtrees-datencheck-plugin`. Dies erleichtert das Entpacken direkt in das `modules_v4`-Verzeichnis von webtrees.
### Behoben
- **Analyse-Einstellungen**: Die Checkboxen für die Kategoriewahl auf dem Analyse-Tab werden nun beim Speichern dauerhaft im Benutzerprofil hinterlegt.

## [1.6.1.1] - 2026-03-03
### Behoben
- **"Likely Dead" Korrektur**: Behebung eines Fehlers, bei dem das Datum der letzten Datensatz-Änderung (`CHAN`) fälschlicherweise als "Lebenszeichen" gewertet wurde (z. B. 2026 bei Personen aus dem 17. Jahrhundert).
- **Tag-Normalisierung**: Robuste Bereinigung von webtrees-spezifischen Präfixen (z. B. `INDI:`) bei der Prüfung von Fakten-Tags.
- **Erweiterte Blacklist**: Technische Metadaten-Tags wie `UID`, `RIN`, `_TODO`, `_UPD` etc. werden nun zuverlässig ignoriert, um Fehlalarme bei der Altersprüfung zu vermeiden.

## [1.6.1] - 2026-03-03
### Geändert
- **Massives Sprach-Update**: Vollständige Überarbeitung und Ergänzung von 28+ Sprachdateien (u.a. Russisch, Ukrainisch, Polnisch, Japanisch, Koreanisch, Persisch, Türkisch, Vietnamesisch).

## [1.6.0] - 2026-03-03
### Geändert
- **Vollständige Internationalisierung (i18n)**: Alle verbleibenden hartkodierten Texte in PHP-Services (Validation, Action, Interaction, Database) und JavaScript-AJAX-Meldungen wurden in `I18N::translate()` gekapselt.
- **Robustere Fehlerbehandlung**: Einführung maschinenlesbarer Fehlercodes (z.B. `NOT_FOUND`, `MISSING_PARAMS`) für die API, um Logikfehler in verschiedenen Sprachen zu vermeiden.
- **Sprach-Automatisierung**: Neues Skript-System zur automatischen Verteilung von Übersetzungsschlüsseln auf alle 49 unterstützten Sprachen.
- **Spezifische Übersetzungen**: Integration neuer Übersetzungen für Französisch, Italienisch, Spanisch, Niederländisch und Portugiesisch.
- **Datenbank-Meldungen**: Alle Meldungen aus dem `DatabaseService` (Duplikate, Geschwister, Quellen) sind nun vollständig übersetzbar.

## [1.5.9] - 2026-03-03
### Hinzugefügt
- **Quick-Fix Buttons**: Einführung von Buttons in der Analyse-Tabelle zur schnellen Korrektur gängiger Fehler.
- **Intelligenter Datums-Tausch**: `ActionService::swapDates` ermöglicht den Tausch von Daten zwischen Fakten (z.B. Taufe vor Geburt) unter Erhalt von Zusatzdaten wie Orten (`PLAC`) und Quellen (`SOUR`).
- **Erweiterte Korrekturen**: Unterstützung für den Tausch von BIRT/CHR, DEAT/BURI und BIRT/DEAT.

## [1.5.8] - 2026-03-03
### Geändert
- **Globales i18n-Refactoring**: Alle verbleibenden hartkodierten Texte in PHP-Services und JavaScript-AJAX-Meldungen wurden in `I18N::translate()` gekapselt.
- **Sprach-Offensive**: Integration von 22 neuen Sprachen (jetzt insgesamt 49 Sprachvarianten unterstützt).
- **Norwegisch-Standard**: Umstellung des Sprachcodes von `no` auf den webtrees-Standard `nb` (Bokmål).
- **Platzhalter-Synchronisation**: Alle `%d` und `%s` Platzhalter wurden in allen 49 Sprachdateien einheitlich korrigiert.

## [1.5.7] - 2026-03-02
### Hinzugefügt
- **GEDCOM-Standardprüfung (Bulk)**: Neue Validierung auf mehrfache Ereignisse (BIRT, DEAT, SEX, BAPM, BURI).
- **Info-Kategorie**: Einführung einer "Blauen Kategorie" (Info) für redaktionelle Hinweise zur Datenpflege, die keine harten Fehler darstellen.
- **Build-System**: Sanierung des PowerShell-Build-Skripts für robuste ZIP-Erstellung auf Windows-Systemen (Fix für Pfad-Variationen).

## [1.5.2] - 2026-02-25
### Hinzugefügt
- **"Likely Dead" Heuristik**: Neue Prüfung für Personen, die über 110 Jahre alt wären und keinen Sterbebeleg haben. Berücksichtigt "letzte Lebenszeichen" (z. B. Kindergeburten), um das vermutete Alter zu verfeinern.
- **Quick-Fix**: Personen in der Analyse können nun direkt per Klick als verstorben markiert werden (Quick-Fix für "Wahrscheinlich verstorben").
- **Verwaiste Fakten (Orphaned Facts)**: Erkennt Ereignisse (z. B. Beruf, Wohnort), die zeitlich unmöglich vor der Geburt oder nach dem Tod liegen. Robuster Abgleich durch Tag-Normalisierung (strip prefixes) und strikte technische Blacklist (CHAN, UID, SEX etc.).
- **Lokalisierung**: Vollständige Unterstützung für verwaiste Fakten in allen 26 Sprachen (de, en, fr, es, it, nl vollständig übersetzt).

## [1.5.1] - 2026-02-25
### Hinzugefügt
- **Keyword-Update**: "Christening" und "Chri" werden nun beim Tauf-Abgleich erkannt. "Sterbe" und "Sterben" sind nun beide abgedeckt.

## [1.5.0] - 2026-02-25
### Hinzugefügt
- **Live-Archiv-Check**: Echtzeit-Dublettensuche für Archive/Repositories (z.B. Archiv ↔ Archives ↔ Staatsarchiv).
- **Erweiterte Quellendetails**: Der Autor (`AUTH`) wird nun beim Dubletten-Check mit ausgelesen und zur besseren Unterscheidung angezeigt.
- **Neue Keyword-Kategorien**: Unterstützung für Testamente/Nachlass, Grundbesitz, Friedhöfe und Zeitungen (Obituaries).

## [1.4.3] - 2026-02-25
### Hinzugefügt
- **Erweiterte Quellen-Keywords**: Unterstützung für Adressbücher und Social Security Death Index (SSDI) / Identifikationsnummern beim Dubletten-Check.

## [1.4.2] - 2026-02-25
### Hinzugefügt
- **Erweiterte Quellen-Keywords**: Unterstützung für Kategorien wie Passagierlisten, Einbürgerung, Militärdienst, Aus/Einwanderung und Volkszählungen beim Dubletten-Check.
- **Register-Logik**: Automatische Verknüpfung von "Geburtsregister" etc. mit den entsprechenden englischen Begriffen.

## [1.4.0] - 2026-02-25
### Hinzugefügt
- **Live-Quellen-Check**: Echtzeit-Suche nach Dubletten bei der Eingabe von Quellentiteln (Deutsch/Englisch-kompatibel).
- **Integrierte Namens-Normalisierung**: Verbesserte Erkennung von ähnlichen Quellentiteln durch Entfernung von Satzzeichen und Berücksichtigung von Übersetzungen (z.B. "birth" vs "geburt").
### Behoben
- **i18n Bugfix: Geschwisterabstand**: Korrektur der fehlerhaften Übersetzungsschlüssel für den Hilfetext "Geschwisterabstand" in 13+ Sprachen (de, it, es, ru, pl, sv, no, fi, da, cs, el, pt, nl).
- **Behoben: Quellen-Dubletten-Check**: Die Erkennung der Quellentitel-Felder wurde verbessert, damit der Check auch in Modal-Dialogen und bei der Neuanlage zuverlässig auslöst.
- **Lokalisierung (nl)**: Fehlende Übersetzungen für Geschwisterabstand und Elternalter im Niederländischen nachgetragen.

## [1.3.16] - 2026-02-23
### Hinzugefügt
- **Intelligente Tauf-Logik**: Zeiträume (z. B. nur Geburtsjahr bekannt) werden nun korrekt verglichen. Taufen im selben Jahr wie eine unpräzise Geburt führen nicht mehr zu Warnungen, sondern zu einem informativen Hinweis ("Info").
- **Detaillierte Fehlermeldungen**: Validierungsmeldungen enthalten nun direkt die relevanten Datumsangaben für eine schnellere Überprüfung.
### Behoben
- **Performance-Boost (502 Fix)**: Einführung eines personenspezifischen Caches für Fakten und Julian Days. Reduziert die Datenbanklast bei der Bulk-Analyse um ca. 80%.
- **Batch-Stabilität**: Batch-Größe für die Analyse im Admin-Bereich wurde auf 50 Personen optimiert, um Timeouts (502 Bad Gateway) auf Shared-Hosting-Servern zu verhindern.
- **Fehlerbereinigung**: Interner Debug-Ballast und unnötige Log-Einträge entfernt.

## [1.3.12] - 2026-02-23
### Geändert
- **Ehe-Überschneidungen**: Mathematisch präzise Berechnung von Überlappungen mittels Julian Day Ranges. Unterscheidung zwischen definitiven Fehlern und möglichen Konflikten bei ungenauen Daten.

## [1.3.11] - 2026-02-23
### Hinzugefügt
- **Scheidungs-Validierung**: Neue Prüfungen für Scheidungsdaten (Chronologie gegenüber Geburt, Tod und Heirat).
- **Intelligente Ehedauer**: Scheidungen werden nun bei der Prüfung auf überschneidende Ehen berücksichtigt, um Fehlalarme bei Wiederverheiratung zu Lebenszeiten des Ex-Partners zu vermeiden.

## [1.3.10] - 2026-02-20
### Hinzugefügt
- **Menü-Icon Option**: Das Modul-Icon im Hauptmenü kann nun in den Einstellungen deaktiviert werden.
- **Einstellung**: Das Icon ist standardmäßig **aktiviert**, kann aber bei Layout-Problemen (z. B. webtrees primer theme) manuell ausgeschaltet werden.
### Behoben
- **Server-Error (TypeError)**: Fix für einen kritischen Fehler in `checkBurialBeforeDeath()`, bei dem unter bestimmten Bedingungen kein Rückgabewert geliefert wurde (Return value must be of type ?array, none returned).

## [1.3.8] - 2026-02-17
### Hinzugefügt
- **Geschlechts-Heuristik**: Namen, die auf 'a' oder 'e' enden, werden nun automatisch als weiblich erkannt, falls sie nicht in der Datenbank stehen.
- **Erweiterte Namensliste**: Unterstützung für weitere Varianten wie Giesela, Karolina, Regina, Marianna etc.
### Behoben
- **AJAX-Trigger**: Validierung reagiert nun sofort auf jede Änderung im Formular (Input/Change auf allen Feldern).
- **Feld-Erkennung**: Massive Verbesserung der Erkennung von Geschlechts-Radios (M/F) und Namensfeldern, auch bei webtrees-spezifischen Patterns wie `ivalues[]`.
- **Parsing-Fix**: Sonderzeichen (Slashes) in Namen werden nun vor der Validierung bereinigt.
- **Namespace-Fix**: Fehler 'Class I18N not found' im AJAX-Service behoben.

## [1.3.7] - 2026-02-17
### Hinzugefügt
- **Geschlechts-Validierung**: 
  - Warnung, wenn ein Vorname eingegeben wurde, aber das Geschlecht noch nicht ausgewählt ist.
  - Hinweis (Info), wenn der Vorname nicht zum gewählten Geschlecht passt (basierend auf einer Datenbank mit über 100 gängigen Vornamen und deren Varianten).
- **UX**: Validierung wird nun auch beim Ändern des Geschlechts im Formular sofort ausgelöst.
- **Lokalisierung**: Unterstützung für Geschlechts-Prüfungen in Deutsch, Englisch, Bulgarisch, Ukrainisch, Ungarisch und Griechisch.

## [1.3.6] - 2026-02-17
### Hinzugefügt
- **Prüfung auf zukünftige Daten (Erweiterung)**: Detektion von zukünftigen Daten für Geburt, Tod und Heirat nun auch bei Neuanlage von Personen (vor dem ersten Speichern).
- **Mehrsprachigkeit**: Unterstützung für Bulgarisch (bg), Ukrainisch (uk), Griechisch (el) und Ungarisch (hu) für die Zukunftsdatumsprüfung vervollständigt.
### Behoben
- **Stabilität (Skelett-Objekte)**: Fix für Abstürze bei Neuanlagen, wenn die Person noch nicht in der Datenbank existiert (`exists()` Check / `checkInvalidMonths`).
- **Performance/UX**: Validierung bei Datumsfeldern wird nun erst beim Verlassen des Feldes (`change`) statt bei jeder Eingabe (`input`) ausgelöst, um unnötige Server-Anfragen während des Tippens zu vermeiden.
- **Debug-Logs**: Entfernung von internen Konsolen-Ausgaben.

## [1.3.4] - 2026-02-17
### Hinzugefügt
- **Prüfung auf zukünftige Daten**: Neue Validierung für Geburts-, Todes-, Tauf-, Begräbnis- und Heiratsdaten. Erkennt Tippfehler wie "2945" statt "1945".
- **Mehrsprachige Unterstützung**: Neue Übersetzungen für Deutsch, Englisch, Polnisch, Spanisch, Italienisch, Russisch, Französisch und Niederländisch hinzugefügt.
### Behoben
- **Syntaxfehler**: Fehlende schließende Klammer im `TemporalValidator` behoben.
- **Menüposition**: Die webtrees-interne Menü-Sortierung wurde wiederhergestellt (redundante Einstellung entfernt).

## [1.3.3] - 2026-02-13
### Hinzugefügt
- **Vollständige Lokalisierung (bg)**: Unterstützung für Bulgarisch vervollständigt (inkl. aller neuen Vergleichs-Strings).
- **UX / Formular-Automatisierung**: Optimierte Feld-Erkennung für die Buttons "Diese Familie nutzen" / "Diese Person nutzen" (Unterstützung für weitere webtrees 2.2-spezifische IDs wie `fid`, `f_id`).
- **Anzeige-Optimierung**:
  - Ortsangaben werden für bessere Lesbarkeit nun bis zum ersten Komma gekürzt.
  - Sterbeorte werden nun für alle Personen im Vergleich angezeigt.
  - Klare Trennung durch Symbole (* für Geburt, † für Tod) in separaten Zeilen.
### Behoben
- **Flimmern im Modal**: Priorisierung von Formular-Daten gegenüber Server-Daten verhindert das Überschreiben ungespeicherter Änderungen während des Vergleichs.
- **Robustes Parsing**: Einführung eines Multi-Strategie-Extraktors für GEDCOM-Daten (MARR, PLAC), der fehlertolerant gegenüber verschiedenen Zeilenumbruch-Flavors ist.

## [1.3.2] - 2026-02-13
### Hinzugefügt
- **Erweiterter Familien-Vergleich**:
  - **Geburts- & Sterbeorte**: Anzeige von Orten für alle Personen im Vergleichs-Modal (sowohl aktueller Eintrag als auch Duplikat-Kandidaten).
  - **Elegantes Layout**: Vollständige Überarbeitung der Familien-Ansicht. Ehepartner werden nun platzsparend dargestellt.
  - **Heirats-Sektion**: Integration der Heiratsdaten (Ring-Symbol `∞`, Datum und Ort) direkt neben den Ehepartnern.
- **Robustes GEDCOM-Parsing**: Verbesserte Extraktion von Heiratsdaten (MARR) und Orten (PLAC) aus verschiedensten GEDCOM-Dialekten (Regex-Optimierung für CRLF/LF).
### Behoben
- **Rollen-Duplizierung**: Fix für einen Fehler, bei dem eine Person fälschlicherweise gleichzeitig als Ehemann und Ehefrau im Vergleich angezeigt wurde.
- **Daten-Synchronität**: Sicherstellung, dass manuelle Formulareingaben (Orte/Daten) korrekt in das Vergleichs-Modal übernommen werden.
    
## [1.3.1] - 2026-02-12
### Geändert
- **Biologische Plausibilität (Altersprüfung)**: Einführung einer Kulanz-Regelung für unpräzise Datumsangaben (z. B. reine Jahreszahlen oder Schätzungen wie "ABT / um").
  - Bei unpräzisen Daten wird nun ein **Puffer von 5 Jahren** für das Mindestalter von Vater (Standard: 14) und Mutter (Standard: 14) gewällt, bevor ein Fehler gemeldet wird. Dies reduziert Fehlalarme bei historischen Schätzungen erheblich.
  - Die Prüfung auf das biologische Höchstalter bleibt weiterhin strikt, um reale Erfassungsfehler zuverlässig zu melden.

## [1.3.0] - 2026-02-12
### Hinzugefügt
- **Globale Namens-Wissensdatenbank**: Einführung einer umfassenden Datenbank für Namens-Äquivalente über verschiedene Sprachen hinweg. 
  - Erkennt nun hunderte Variationen wie `Henryk = Heinrich = Enrico`, `Wacław = Wenzel`, `Katarzyna = Katharina`.
  - **Teilmengen-Matching**: Intelligenter Vergleich von Mehrfachvornamen.
- **Intelligente Datums-Zeitraum-Prüfung**: Korrekte Behandlung von GEDCOM-Modifikatoren wie `AFT`, `BEF`, `ABT`. 
- **Intelligente Ehenamen-Logik**: Automatisches Ignorieren von Warnungen bei Ehenamen.

## [1.2.3] - 2026-02-11
### Behoben
- **Date-API Fehler**: Korrektur eines kritischen Fehlers bei der Bulk-Analyse.

## [1.2.2] - 2026-02-11
### Hinzugefügt
- **Erweiterte Alias-Unterstützung (International)**: „Genannt-Namen“ Logik wurde um polnische und lateinische Varianten erweitert.
- **Konsistente Lokalisierung**: Überarbeitung der Kategorienamen.

## [1.2.1] - 2026-02-11
### Hinzugefügt
- **Performance-Optimierung**: Umstellung der Bulk-Analyse auf ID-basierte Paginierung.
- **DOM-Schutz**: Begrenzung der angezeigten Ergebnisse auf 1000 Zeilen.
- **Monats-Validierung**: Neue Prüfung auf nicht-GEDCOM-konforme Monatsnamen.

## [1.2.0] - 2026-02-11
### Hinzugefügt
- **Umgang mit ungenauen Daten**: Intelligente Erkennung von ungenauen Datumsangaben.
- **Münsterländische Genannt-Namen**: Unterstützung für westfälische Alias-Namen.

## [1.1.0] - 2026-02-09
### Geändert
- **Refactoring Übersetzungen**: Umstellung auf einheitliche englische Keys.

---

## ✅ Phase 18: Erweitertes Matching & Heuristik (COMPLETE - 2026-02-17)
- [x] **Fallback-Heuristik**: Automatisches Erkennen weiblicher Endungen (a/e).
- [x] **Robustes AJAX**: Fix für Context-Guards und Feld-Keywords.
- [x] **Versions-Sprung v1.3.8**: Stabilitäts-Patch für Geschlechts-Validierung.

---

## Versionshistorie
- **Status:** Version 1.3.16 - **Stable** (Performance & Baptism Logic)
- **v1.3.0:** Globale Namens-Datenbank (10+ Sprachen), Intelligente Ehenamen-Logik, Diakritika-Handling
- [x] **v1.3.8:** Geschlechts-Heuristiken & AJAX-Fixes
- [x] **v1.3.11:** Scheidungs-Validierung & Ehe-Plausibilität
- [x] **v1.3.15:** Performance-Cache (502 Fix)
- [x] **v1.3.16:** Intelligente Tauf-Logik & Perioden-Vergleich

## [0.9.0] - 2026-02-08
### Hinzugefügt
- **Bulk-Analyse**: Gesamte Stammbaum-Prüfung im Admin-Bereich.

## [0.8.0] - 2026-02-08
### Hinzugefügt
- **Funktion "Fehler ignorieren"**: Dauerhafte Ignorieren-Liste mit DB-Tabelle.
- **Admin-UI**: Neue Tabs und Kontext-Hilfe.
