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

## 🟦 Phase 20: Erweiterte Plausibilität & Heuristiken (Geplant)
- [ ] **"Likely Dead"**: Optionale Warnung für Personen ohne Sterbedatum über 110J.
- [ ] **Inzest-Check**: Optionale Prüfung auf Ehen zwischen nahen Verwandten.
- [ ] **Generations-Check**: Statistische Prüfung auf Ausreißer (z.B. Elternteil zu jung/alt für Erstgeburt).

## 🟦 Phase 21: UI-Komfort & Quellen-Qualität (Geplant)
- [ ] **Quick-Fix UI**: Buttons zur schnellen Korrektur (z.B. Vertauschen von Daten) direkt in der Analyse.
- [ ] **Deep Source Check**: Optionale Prüfung der Quellenkonsistenz (Datum vs. Quellentext).
- [ ] **Familien-Matching**: Dubletten-Suche auf Basis von Elternpaaren.

---

## Versionshistorie
- **v1.3.0:** Globale Namens-Datenbank, Ehenamen-Logik, Diakritika
- **v1.3.3:** Kompakte Anzeige, Sterbeort-Integration
- **v1.3.6:** Zukunftsdaten, Fixes für Neuanlagen
- **v1.3.8:** Geschlechts-Heuristiken & AJAX-Fixes
- **v1.3.10:** Deaktivierbares Menü-Icon & Server-Error Fixes
- **v1.3.11:** Scheidungs-Validierung & Verbesserungen an der Eheurkunde
