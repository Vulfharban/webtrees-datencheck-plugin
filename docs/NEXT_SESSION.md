# Nächste Session - Webtrees Datencheck Plugin

## Status Quo
- **Version:** 1.5.2 (Stable)
- **Letzte Änderungen:**
  - "Likely Dead" Heuristik implementiert.
  - Orphaned Facts Check finalisiert (Blacklist-System + Tag-Normalisierung + i18n für 26 Sprachen).

## Offene Punkte / Nächste Schritte
1. **Generations-Check**:
   - Statistische Analyse auf Ausreißer (z.B. ungewöhnlich viele Kinder in kurzem Abstand).
2. **Erweiterte Quellenprüfung**:
   - Qualitative Prüfung (Repositories, Seitenzahlen, Konsistenz Check).

## Technische Notizen
- Die "Orphaned Facts" Prüfung nutzt ein robustes Tag-Normalisierungs-System, um Präfixe wie `INDI:` zu ignorieren.
- Blacklist umfasst nun: CHAN, UID, SEX, NAME, BURI, FAMS, FAMC und webtrees-interne `_`-Tags.
