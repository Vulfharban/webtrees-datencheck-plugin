# Nächste Session - Webtrees Datencheck Plugin

## Status Quo
- **Version:** 1.5.1 (Stable)
- **Letzte Änderungen:**
  - Live-Archiv-Check (Repositories) hinzugefügt.
  - Umfangreiches Keyword-Mapping (DE/EN) für 20+ Quellenkategorien.
  - Berücksichtigung von Autoren (AUTH) beim Quellen-Abgleich.
  - Feld-Erkennungs-Fix für Quellen in Modals und Neuanlagen.

## Offene Punkte / Nächste Schritte
1. **"Likely Dead" Heuristik**:
   - Implementierung der Logik für Personen ohne Sterbedatum, die über 110 Jahre alt wären.
   - Einbeziehung "letzter Lebenszeichen" (z.B. Geburt eines Kindes, Zeuge bei Heirat) zur Verfeinerung.
2. **Generations-Check**:
   - Statistische Analyse auf Ausreißer (z.B. ungewöhnlich viele Kinder in kurzem Abstand über die gesamte Fruchtbarkeitsphase).
3. **Quick-Fix UI**:
   - Erste Experimente mit Buttons in der Analyse-Tabelle (z.B. "Als verstorben markieren").
4. **Erweiterte Quellenprüfung**:
   - Konsistenzprüfung: Passt der Quellentyp zum Fakt? (z.B. Geburtsurkunde für einen Tod-Fakt).

## Technische Notizen
- Der Live-Quellen-Check nutzt `StringHelper::levenshteinDistance` und eine Übersetzungstabelle für Begriffe wie "Birth/Geburt".
- Die AJAX-Validierung ist nun sehr stabil und deckt fast alle relevanten Felder ab.
