# Webtrees Datencheck Plugin - Roadmap

## ✅ Phase 1: Grundstruktur & PHP Migration (COMPLETE)
- [x] Abkehr vom Rust-CLI, Fokus auf natives PHP.
- [x] Implementierung der Basis-Validatoren.
- [x] Optimierung der webtrees 2.2 Kompatibilität.

... (weitere Phasen gekürzt für Übersicht)

## ✅ Phase 16: Zukunftsdaten & Stabilität (COMPLETE - 2026-02-17)
- [x] **TemporalValidator**: Check für Daten in der Zukunft.
- [x] **Neuanlagen-Support**: Fix für Skelett-Objekte ohne ID.
- [x] **UX-Trigger**: Umstellung von `input` auf `change` für Datumsfelder.

## ✅ Phase 17: Geschlechts-Validierung (COMPLETE - 2026-02-17)
- [x] **Pflichtfeld-Prüfung**: Warnung bei fehlendem Geschlecht trotz Vornamen.
- [x] **Namens-Geschlechts-Abgleich**: Heuristik zur Erkennung von Mismatch.

## ✅ Phase 18: Erweitertes Matching & Heuristik (COMPLETE - 2026-02-17)
- [x] **Fallback-Heuristik**: Automatisches Erkennen weiblicher Endungen (a/e).
- [x] **Robustes AJAX**: Fix für Context-Guards und Feld-Keywords.
- [x] **Versions-Sprung v1.3.8**: Stabilitäts-Patch für Geschlechts-Validierung.
- [x] **Versions-Sprung v1.3.10**: Modul-Icon Option & Fixes.

## ✅ Phase 19: Scheidungs-Validierung (COMPLETE - 2026-02-23)
- [x] **Event-Support**: Integration von 'DIV' in alle Datums-Parser und Validatoren.
- [x] **Chronologie-Check**: Prüfung auf Scheidung nach Tod/Bestattung oder vor Geburt/Hochzeit.
- [x] **Partner-Vergleich**: Einbeziehung der Lebensdaten des Partners bei Scheidungs-Checks.
- [x] **Ehe-Überlappung v2**: Berücksichtigung von Scheidungen zur Vermeidung von Fehlalarmen bei Wiederverheiratung.
- [x] **Vollständige i18n**: Aktualisierung aller 26 Sprachdateien für Scheidungs-Features.

## ✅ Phase 20: Performance & Präzision (COMPLETE - 2026-02-25)
- [x] **502 Bad Gateway Fix**: Reduktion der Batch-Größe bei der Analyse (v1.3.12).
- [x] **Präzisions-Awareness**: Korrekte Handhabung von ungenauen Daten (vor/nach/ca) bei Ehen.
- [x] **UX-Meldungen**: Verwendung von Klartext-Daten in Validierungsnachrichten.
- [x] **i18n Fix Turn**: Korrektur von Übersetzungsschlüsseln in 13+ Sprachen (v1.4.0).

## ✅ Phase 21: Quellencheck & Live-Validation (COMPLETE - 2026-02-25)
- [x] **Source-Live-Check**: Duplikatssuche in Echtzeit bei der Quelleneingabe (Bilingual DE/EN).
- [x] **Keyword-Mapping**: Intelligentes Matching von Quellen (v1.4.0) und massive Erweiterung der Kategorien (v1.5.0).
- [x] **Repository-Check**: Live-Dublettensuche für Archive/Repositories (v1.5.0).
- [x] **Präzisions-Matching**: Berücksichtigung von Autoren (AUTH) und Wort-Reihenfolge (v1.5.1).

## ✅ Phase 22: Erweiterte Analysen & Heuristiken (COMPLETE - 2026-02-25)
- [x] **"Likely Dead" Heuristik**: Warnung für Personen ohne Sterbedatum (>110J) inkl. Prüfung letzter Lebenszeichen.
- [x] **Verwaiste Fakten**: Prüfung auf Ereignisse zeitlich außerhalb der Lebensspanne.
- [ ] **Generations-Check**: Statistische Prüfung auf biologische Ausreißer (z.B. Elternteil zu jung/alt bei Geburt).
- [ ] **Erweiterte Quellenprüfung**: Identifikation quellenloser Ereignisse und Konsistenzprüfung Quellentyp vs. Fakt.

## 🟦 Phase 22: UI-Komfort & Dubletten-Management (Geplant)
- [ ] **Quick-Fix UI**: Buttons zur schnellen Korrektur (z.B. Swap-Dates, Set-to-Dead) direkt in der Analyse-Tabelle.
- [ ] **Familien-Matching**: Dubletten-Suche auf Basis von Elternpaaren zur Konsolidierung gesplitteter Familien.
- [x] **Source-Live-Check**: Duplikatssuche in Echtzeit bei der Quelleneingabe (Bilingual DE/EN).

---

## Versionshistorie
- **v1.3.0:** Globale Namens-Datenbank, Ehenamen-Logik, Diakritika
- **v1.3.3:** Kompakte Anzeige, Sterbeort-Integration
- **v1.3.6:** Zukunftsdaten, Fixes für Neuanlagen
- **v1.3.8:** Geschlechts-Heuristiken & AJAX-Fixes
- **v1.3.10:** Deaktivierbares Menü-Icon & Server-Error Fixes
- **v1.3.11:** Scheidungs-Validierung & Verbesserungen an der Eheurkunde
- **v1.4.0:** Live-Quellen-Check & i18n Fixes
- **v1.5.1:** Live-Archiv-Check, Keyword-Mapping & Autoren-Support
- **v1.5.2:** Likely Dead Heuristik & Verwaiste Fakten (Orphaned Facts)
