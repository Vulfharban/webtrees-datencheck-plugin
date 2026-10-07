Alle nennenswerten Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

## [1.6.11.0] - 2026-10-07
### Hinzugefügt & Verbessert
- **Erkennung von Feld-Inhaltsvertauschungen (Data-Entry-Checks: Feature 1)**:
  - *Neuer Validator `DataEntryValidator`:* Erkennt typische Tipp- und Vertauschungsfehler zwischen Datums- und Ortsfeldern bei Personen- und Familienfakten (Geburt, Taufe, Tod, Bestattung, Heirat etc.).
  - *Datum im Ortsfeld (`PLACE_CONTAINS_DATE`):* Warnt, wenn ein Ortsbestandteil ausschließlich eine Jahreszahl (z. B. `Hamburg, 1880`), ein formatiertes Datum (`15.08.1890`), GEDCOM-Modifier (`ABT 1880`, `ca. 1880`) oder Monatsnamen mit Zahlen (`Mai 1880`) enthält. Postleitzahlen im Textkontext (`1010 Wien`, `20095 Hamburg`) bleiben unberührt.
  - *Text/Ort im Datumsfeld (`DATE_CONTAINS_TEXT`):* Warnt, wenn ein Datumsfeld nicht-interpretierbaren Text oder Ortsnamen (geprüft gegen die Stammbaum-Ortsdatenbank) enthält. Gedcom-Datumsphrasen in Klammern `(...)` und Kalender-Escapes `@#D...@` werden dabei toleriert.
  - *Live-Validierung im Bearbeitungsformular (`interaction.phtml`):* Sichtbare `DATE`- und `PLAC`-Eingabefelder werden bei der Live-Prüfung erfasst und in Echtzeit validiert.
  - *Labels & Übersetzungen:* Neue Codes `PLACE_CONTAINS_DATE` und `DATE_CONTAINS_TEXT` in `ValidationConstants` und Sprachdateien integriert.
- **Erkennung von Groß-/Kleinschreibungsfehlern in Namen (Data-Entry-Checks: Feature B)**:
  - *Vollständige Großschreibung (`NAME_ALL_CAPS`):* Warnt, wenn Vorname oder Nachname komplett in Großbuchstaben erfasst wurden (z. B. `JOHANN`, `MÜLLER`). Vornamen werden standardmäßig geprüft (`check_given_caps = 1`), Nachnamen optional (`check_surname_caps = 0`, um Massenmeldungen bei importierten GEDCOMs zu vermeiden).
  - *Versehentliche Kleinschreibung (`NAME_ALL_LOWERCASE`):* Erkennt Namen, die komplett mit Kleinbuchstaben beginnen (z. B. `johann`, `müller`).
  - *Robuste Ausnahmen:* Berücksichtigt Namenspartikel (`von`, `van der`, `de`, `du` etc.), Kurzformen/Initialen (`J.`, `H.-P.`), römische Ziffern (`II`, `III`), Präfixnamen (`McDonald`, `O'Brien`) und Platzhalter (`@N.N.`, `@P.N.`) sowie Schriften ohne Groß-/Kleinschreibung.

## [1.6.10.3] - 2026-10-07
### Behoben
- **Bearbeitete Person wird bei Duplikatsprüfung nicht mehr als eigenes Duplikat angezeigt**:
  - *Problem:* Beim Bearbeiten einer bestehenden Person – insbesondere beim Hinzufügen weiterer Namen (Alias, Geburtsname, Ehename, Adoptivname etc.) oder Fakten – erschien die Person selbst als Treffer („Mögliche Duplikate gefunden: 1“) in der Warnbox.
  - *Ursache:* Die interaktive Duplikatsprüfung im Backend (`DatabaseService::findDuplicatePerson`, `module.php`) berücksichtigte keinen Ausschluss der aktuellen Personen-ID (`XREF`). Zudem wurde im Frontend (`interaction.phtml`) beim Hinzufügen von Namen durch Formular- und URL-Schlagwörter (`hinzufügen`, `add-name`) fälschlicherweise angenommen, dass eine neue Person (`isAdd = true`) angelegt wird.
  - *Lösung:*
    - Backend: `DatabaseService::findDuplicatePerson` und `InteractionService::runInteractiveCheck` akzeptieren nun den Parameter `$excludeXref`. Ist dieser gesetzt, wird der Datensatz per SQL (`n_id != $excludeXref`) sowie über eine Schleifenprüfung sicher ausgeschlossen.
    - Controller: `getCheckPersonAction` in `module.php` liest `exclude_xref` (bzw. `xref`) aus den Request-Parametern aus und leitet ihn weiter.
    - Frontend: Neue Erkennung `getEditingIndividualXref(event)` in `interaction.phtml` differenziert präzise zwischen dem Erstellen neuer Verwandter/Personen (wie `add-child`, `add-spouse`, `add-parent`, `add-unlinked`) und dem Bearbeiten bzw. Hinzufügen von Namen/Fakten zu einer bestehenden Person. Die XREF wird an den API-Endpunkt übergeben und die Trefferliste clientseitig redundant gefiltert.

## [1.6.10.2] - 2026-10-02
### Behoben & Optimiert
- **Versionsanzeige im Custom Module Manager nach Update korrigiert (#49)**:
  - *Ursache:* In `module.php` lieferte die Methode `customModuleVersion()` noch statisch `'1.6.9.9'` zurück, da sie bei den Versionen 1.6.10.0 und 1.6.10.1 nicht nachgezogen worden war. Nach dem Entpacken des Release-Archivs meldete der Custom Module Manager weiterhin Version 1.6.9.9 als installiert.
  - *Lösung:* `customModuleVersion()` liest nun dynamisch aus der mitgelieferten `latest-version.txt` (mit Fallback auf die Klassenkonstante `CUSTOM_VERSION = '1.6.10.2'`), sodass Versionsnummern künftig immer synchron und konsistent bleiben.
  - *Release-Skript:* `build_release.ps1` liest die Versionsnummer nun automatisch aus `latest-version.txt` aus und validiert `module.php`.
- **Performance: N+1-Datenbankabfragen bei Kandidatenprüfung eliminiert (`DatabaseService`)**:
  - In `findDuplicatePerson()` wurden zuvor für jeden gefundenen Kandidaten (bis zu 300 Personen) separate SQL-Abfragen auf die Tabelle `families` ausgeführt, um Familien-IDs abzufragen.
  - Familien-Referenzen (`1 FAMS`) werden nun direkt in-memory aus dem bereits geladenen GEDCOM-Datensatz extrahiert. Dadurch entfallen bis zu 300 redundante Datenbank-Roundtrips pro Prüfung komplett.
  - Bei Heiratsdatums-Abgleichen werden nur noch vorhandene Familien über indexierte `f_id`-Lookups geladen anstatt ungefilterter Tabellenscans.
- **Intelligente Erkennung von Doppel- und Mehrfachvornamen (`NameHelper`)**:
  - `NameHelper::areNamesEquivalent()` erkennt Äquivalenzen (z. B. Johann / Hans) nun auch in zusammengesetzten Vornamen (z. B. "Johann Friedrich" vs. "Hans") und bei der Geschwisterprüfung zuverlässig, ohne Fehlalarme zu erzeugen.
- **Robustheit & Kompatibilität (`module.php`, `StringHelper`)**:
  - Rückwärtskompatible Polyfills für `str_starts_with`, `str_ends_with` und `str_contains` für PHP 7.4 Umgebungen.
  - Zentralisierte UTF-8-sichere JSON-Serialisierung mit `JSON_INVALID_UTF8_SUBSTITUTE`, um JavaScript-Parsefehler bei Sonderzeichen im GEDCOM zu verhindern.
  - GitHub-Versionsabfrage mit User-Agent und pfad-spezifischem Cache gegen CDN-Sperren und Datei-Zugriffskonflikte.
- **Frontend-Reaktivität & Race-Condition-Schutz (`interaction.phtml`)**:
  - Einsatz von `AbortController` bricht veraltete Dublettenabfragen bei schnellem Tippen sauber ab, sodass spätere Antworten frühere niemals überholen können.
  - Fehlerabsicherung mit `.catch()` bei Hintergrund-Fetch-Aufrufen.

## [1.6.10.1] - 2026-09-30
### Behoben & Verbessert
- **Keine vorzeitige Dubletten-Liste bei alleiniger Nachnamenseingabe**:
  - Wenn ausschließlich der Nachname eingegeben wird (ohne Vorname und ohne Datumsangaben), erscheint die Trefferliste nicht mehr störend beim Tippen.
  - Die Dublettenliste wird erst eingeblendet, sobald auch ein Vorname ODER ein Datum (Geburts-, Sterbe-, Tauf- oder Heiratsdatum) eingegeben wurde.
  - Auch backend-seitig in `DatabaseService::findDuplicatePerson` abgesichert, damit keine unvollständigen Suchanfragen ohne Vorname und Daten mehr ausgeführt werden.
- **Volle Formularbreite für Dubletten-Box auf allen Seiten (u. a. `EditFactPage`)**:
  - *Ursache des Darstellungsfehlers:* Auf Seiten zum Hinzufügen/Bearbeiten von Fakten (wie 2. Name, Alias, Ehename) existierte kein `[id*="INDI-NAME"]`-Element. Der Fallback griff auf das erste `<form>` der Seite zu – welches in Webtrees das Schnellsuche-Formular im Header (`wt-header-search-form`) in einer schmalen Header-Spalte war. Dadurch wurde die gelbe Dublettenbox ganz oben rechts in die Suche gequetscht.
  - *Lösung:* Einführung der Funktion `attachWarningArea()`, die Header- und Suchformulare strikt ausschließt und die Warnbox zuverlässig im Hauptformular des Inhaltsbereichs (`main`, `#content`, Modaldialog) direkt nach der Namenskarte bzw. am Formularanfang über die volle Zeilenbreite (`100% !important`) platziert.
- **Unterstützung für Heiratsdaten bei Dublettenprüfung**:
  - Formularfelder für Heiratsdaten (`MARR`) werden nun im Frontend erkannt und an die interaktive Personenprüfung übergeben.
  - `DatabaseService` gleicht bei Vorhandensein eines Heiratsdatums die Heiratsdaten bestehender Familien (`families`) des Kandidaten ab und vergibt entsprechende Relevanz-Boni.

## [1.6.10.0] - 2026-09-28
### Behoben
- **Dupletten-Liste beim Bearbeiten von Alias- oder Ehenamen quetscht sich nicht mehr an den Rand** (`interaction.phtml`):
  - *Problem:* Beim Hinzufügen eines Alias- oder Ehenamens zu einer **bestehenden Person** erschien die Warnbox „Mögliche Duplikate gefunden" an den rechten Seitenrand gedrückt, da das `datencheck-warning`-Div direkt als Geschwisterelement einer `.card` eingefügt wurde, die innerhalb einer schmalen Bootstrap-Grid-Spalte (`col-sm-9` o. ä.) lag. Bei **neuen Personen** war die Anzeige korrekt, weil der Formularkontext dort anders strukturiert ist.
  - *Lösung:* Der DOM-Einfügepunkt klettert jetzt vom Namens-Card-Element aufwärts durch die Elternknoten, bis ein echter Full-Width-Container gefunden wird (erstes `.row`-, `<form>`-, `<main>`- oder `#main-content`-Element). Das `datencheck-warning`-Div wird dort eingehängt und erhält zusätzlich per CSS `width: 100%; box-sizing: border-box;`, sodass es immer die gesamte Formularbreite einnimmt.

## [1.6.9.9] - 2026-09-25
### Hinzugefügt / Verbessert
- **Paginierung & Trefferanzahl-Auswahl bei Dubletten**:
  - Paginierung (Standard: 10 Treffer pro Seite) mit Blätter-Buttons (`<<`, `<`, `>`, `>>`) und Seitenanzeige.
  - Auswahl der Trefferanzahl je Seite (10 / 25 / 50 / 100).
  - Bei 10 oder weniger Treffern werden sofort alle Treffer vollständig angezeigt und die Paginierungs- sowie Größenauswahlleiste automatisch ausgeblendet.
  - Anzeige der Gesamttrefferzahl direkt im Header (`Mögliche Duplikate gefunden: X (Zeige 1–10)`).
  - Schlankes, platzsparendes Layout für die Dublettenliste mit kompakten Aktionsbuttons.
- **Intelligentes Relevanz-Scoring & Sortierung für Dubletten**:
  - Exakte Übereinstimmungen bei Vor- und Nachname (z. B. "JUAN BAUTISTA" + "SALA LLOBELL") werden priorisiert ganz oben angezeigt.
  - Relevanz-Gewichtung: 1. Vollständiger Name > 2. Vollständiger Nachname + Vorname > 3. Zusammengesetzte Nachnamensteile (z. B. 1. Nachname bei spanischen Doppelnamen) > 4. Phonetische Treffer.
  - Suche schließt nun auch den unzerlegten vollständigen Doppelnamen in die SQL-Muster ein.
  - SQL-Trefferlimit auf 300 erhöht und absteigende Sortierung nach Relevanz-Score in PHP eingeführt.
- **Optimiertes Trigger-Verhalten bei neuen Einträgen**:
  - Bei der Eingabe des Vornamens wird die Dubletten-Warnung solange zurückgehalten, bis auch ein Nachname oder ein Datum (Geburt/Taufe/Tod) eingegeben wurde. Dadurch wird das vorzeitige Aufploppen langer Trefferlisten während des Tippens von Vornamen verhindert.
  - Wenn der Nachname unbekannt ist, filtert die Dublettenprüfung gezielt nach Personen mit gleichem Vornamen und passendem Geburts-/Sterbejahr, sobald ein Datum eingegeben wird.
  - Live-Reaktivität bei Eingabe oder Auswahl von Datumsfeldern (BIRT, DEAT, BAPM, DATE) und Geschlecht.
- **Übersetzungen & Lokalisierung**:
  - Neue Sprachschlüssel für 'Page' und 'Per page:' in allen 49 unterstützten Sprachen (u. a. Katalanisch, Spanisch, Deutsch, Französisch, Niederländisch, Polnisch, Italienisch etc.) hinterlegt.
- **Sicherheit & Robustheit**:
  - HTML-Escaping bei der dynamischen Duplikatausgabe und zuverlässiges Ausblenden der Warnbox bei geleerten Formularfeldern.

## [1.6.9.8] - 2026-09-23
### Behoben
- **Dubletten-Prüfung TypeError behoben**: Wiederherstellung der Variablen `$normalizedCandidate = StringHelper::normalizeName($candidateName);` in `DatabaseService::findDuplicatePerson()`. Zuvor stürzte die Duplikatprüfung mit einem `TypeError` in `PhoneticHelper::cologneEncode()` bzw. `StringHelper::levenshteinDistance()` ab, sobald ein echter Kandidat gefunden wurde, wodurch das Frontend fälschlicherweise "Keine Duplikate" anzeigte.
- **Akzent- und Diakritika-Normalisierung (Spanisch / Katalanisch / International)**:
  - Erweiterung von `StringHelper::normalizeName()` um spanische/katalanische und mitteleuropäische Sonderzeichen (z. B. `ñ` -> `n`, `ç` -> `c`, `à/è/ò` -> `a/e/o`, `ß` -> `ss` u. v. m.).
  - Generierung von SQL-Suchmustern sowohl in Kleinbuchstaben mit Akzenten als auch normalisiert ohne Akzente, sodass Treffer unabhängig von der Datenbank-Kollation (MySQL, MariaDB, SQLite, PostgreSQL) zuverlässig gefunden werden (z. B. "José" findet "José" und "Jose", "Sánchez" findet "Sánchez" und "Sanchez").
- **Performance & N+1-Query-Eliminierung bei häufigen Nachnamen**:
  - `DB::table('name')` wird nun direkt mit `individuals` gejoint, anstatt für jeden Kandidaten eine separate Abfrage nach `i_gedcom` auszuführen.
  - Wenn sowohl Vor- als auch Nachname eingegeben wurden, wird die SQL-Abfrage nach beiden Kriterien vorgefiltert, anstatt Tausende von Personen mit gleichem Nachnamen zu laden.
  - `LIMIT 150` und ID-Deduplizierung verhindern Timeouts und Speicherüberläufe bei sehr großen Stammbäumen und häufigen Familiennamen.
- **Frontend-Interaktion**:
  - Wenn noch kein Nachname eingegeben wurde, wird die Suche erst ab mindestens 3 Zeichen im Vornamen oder bei Vorhandensein von Datumsangaben ausgelöst, um unnötige Trefferfluten bei kurzen Eingaben (z. B. 1–2 Buchstaben) zu vermeiden.
  - Fehlerhafte API-Antworten werden im Frontend geloggt (`console.warn`), um Probleme transparent nachvollziehbar zu machen.

## [1.6.9.7] - 2026-09-21
### Behoben
- **Namenskonsistenz & Formularfeld-Erkennung**: Behebung von Fehlalarmen bei der Prüfung auf Namenskonsistenz durch gezielte Erfassung tatsächlicher Namensfelder im Eingabeformular (PR #45).
- **Übersetzungen**: Fehlender englischer Sprachschlüssel für "Name in the form" ergänzt.

## [1.6.9.6] - 2026-09-17
### Behoben
- **Theme-Kompatibilität**: Reine Text-Menüs für Drittanbieter-Themes ohne Icon-Konflikte.

## [1.6.9.5] - 2026-09-15
### Behoben
- **Icon-Darstellung JustLight & Drittanbieter**: Dynamische Client-seitige Erkennung für textbasierte Themes.

## [1.6.9.4] - 2026-08-26
### Behoben
- **Pixelgenaue Menü-Icon Integration über alle Webtrees-Themes**:
  - *Webtrees / Modern / Drittanbieter-Themes*: Box-Modell exakt an native Webtrees-Berechnungsmaße angepasst (`57.2px × 56.2px`, `margin: 0 auto`), womit Text-Grundlinie, Icon-Oberkante und vertikaler Rhythmus auf den Subpixel genau mit den Standard-Menüpunkten harmonieren.
  - *Xenea*: Maßgeschneiderte `28px × 28px` Kachel mit `1px solid #a6a6a6` Rand und zentriertem Vektor-Icon.
  - *Clouds*: Blaue Kachel-Integration mit angepasstem Farbrahmen (`#5b82a6`).
  - *Colors*: Exakte `40px × 40px` Kachel mit dunklem Rand (`#555555`) passend zum Header-Grid.
  - *Text-Themes (Minimal, FAB, Paper)*: Korrektes Ausblenden ohne Layout-Verschiebung.
  - *SVG-Optimierung*: Bounding-Box verlustfrei und ohne inneren Versatz (`viewBox="94 128 467 392"`) auf die Vektorgrenzen zugeschnitten.

## [1.6.9.3] - 2026-08-06
### Behoben
- **Menü-Icon Skalierung & Ausrichtung**: Gezieltes Styling für 3D-Desktop-Themes (`Xenea`, `Modern`, `Webtrees`, `Clouds`) mit zentriertem 3,4rem-Block über dem Text sowie korrekter Inline-Darstellung für Kompakt-Themes (`Minimal`, `Colors`, `Paper`).
- **Prüfungs-Standardwerte**: Aktivierung der Standardwerte (`'1'`) für *Fehlende Daten*, *Namenskonsistenz*, *Geografische Plausibilität* und *Quellenpflicht*, damit Warnungen (z. B. fehlende Geburts-/Sterbedaten) auch ohne manuelles Speichern der Admin-Einstellungen direkt greifen.
- **Dublettensuche bei neuem Personeneintrag**: Die Echtzeit-Suche nach möglichen Duplikaten durchsucht nun bei leerem Nachnamensfeld auch Vornamen, sobald mindestens 2 Zeichen eingegeben wurden.
- **Namensvalidierung**: Ergänzung der Warnung bei fehlendem Nachnamen (`MISSING_SURNAME`) bei vorhandenem Vornamen.

## [1.6.9.2] - 2026-05-08
### Behoben
- **Fehlerbehandlung (Fetch API)**: Behebung des Fehlers `Response.text: Body has already been consumed` im JavaScript, der bei ungültigen Serverantworten auftrat. Die API-Antworten werden nun robuster verarbeitet und Details im Fehlerfall in der Konsole ausgegeben.
- **Kategorien-Filterung**: Korrektur der Logik für die Auswahl von Analysekategorien. Das Deaktivieren aller Kategorien führt nun korrekt dazu, dass keine Prüfungen ausgeführt werden, statt fälschlicherweise alle Prüfungen zu starten.
- **Stabilität**: Entfernung redundanter Code-Pfade und verbesserte Typ-Sicherheit bei der Batch-Verarbeitung.

## [1.6.9.1] - 2026-05-05
### Behoben
- **Datenbank-Fehler beim Speichern**: Behebung eines kritischen Fehlers (`SQLSTATE[22001]: Data too long for column 'setting_name'`), der beim Speichern der Einstellungen auftrat. Der interne Schlüssel `DC_enable_spanish_lenient_duplicates` (38 Zeichen) überschritt das Webtrees-Limit von 32 Zeichen für `wt_user_setting.setting_name`. Gekürzt auf `DC_enable_es_lenient_dupes` (27 Zeichen).

## [1.6.9] - 2026-05-04
### Hinzugefügt
- **Spanische Namenskonventionen**: Neue Option für "Lenientes Matching" bei Dubletten. Ermöglicht Übereinstimmungen, wenn nur Teile des Vor- oder Nachnamens übereinstimmen (wichtig für komplexe spanische Doppelnamen).
- **Akzent-Toleranz**: Die Namenssuche ist nun unempfindlich gegenüber Akzenten (z. B. Bernát = Bernat).
- **Ignorierte Fakt-Typen**: Benutzer können nun eine Liste von Fakt-Labels (z. B. `Ashes Interred`, `Probate`) definieren, die bei der chronologischen Prüfung ignoriert werden sollen.
- **Moderne Bestätigungsdialoge**: Alle Browser-Dialoge (OK/Abbrechen) wurden durch webtrees-konforme Bootstrap-Modale mit klaren "Ja/Nein"-Schaltflächen ersetzt.
### Geändert
- **Robuste Namensextraktion**: Optimierte Trennung von Vor- und Nachnamen aus GEDCOM-Daten (Unterstützung von /Slashes/).
- **Fehlerbehandlung**: Verbesserte API-Antworten bei unvollständigen Datensätzen.

## [1.6.8] - 2026-04-01
### Geändert
- **Premium Menü-Integration**: Vollständige Überarbeitung der Menü-Icons nach webtrees-Best-Practices. Die Steuerung von Icon-Größe und Sichtbarkeit erfolgt nun ausschließlich über das Theme, während das Plugin nur noch die semantische Klasse und das SVG-Icon bereitstellt. Dies sorgt für eine perfekte Integration in alle Themes (z.B. Xenea, Modern, Clouds).
- **Vektorgrafiken**: Umstellung des Menü-Icons auf SVG für verlustfreie Skalierung bei jeder Displaygröße.
- **CSS-Refaktorisierung**: Auslagerung der Stile in eine externe CSS-Datei und Entfernung hartkodierter Inline-Styles im PHP-Code.

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
