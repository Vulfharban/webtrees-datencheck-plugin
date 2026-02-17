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

---

## Versionshistorie
- **v1.3.0:** Globale Namens-Datenbank, Ehenamen-Logik, Diakritika
- **v1.3.3:** Kompakte Anzeige, Sterbeort-Integration
- **v1.3.6:** Zukunftsdaten, Fixes für Neuanlagen
- **v1.3.8:** Geschlechts-Heuristiken & AJAX-Fixes
