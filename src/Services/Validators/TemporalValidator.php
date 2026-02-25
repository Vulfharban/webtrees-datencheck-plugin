<?php

namespace Wolfrum\Datencheck\Services\Validators;

use Fisharebest\Webtrees\Individual;
use Fisharebest\ExtCalendar\GregorianCalendar;
use Wolfrum\Datencheck\Services\ValidationService;
use Fisharebest\Webtrees\I18N;
use Wolfrum\Datencheck\Helpers\DateParser;

class TemporalValidator extends AbstractValidator
{
    /**
     * Check if person born after their own death
     */
    public static function checkBirthAfterDeath(?Individual $person, string $overrideBirth = '', string $overrideDeath = '', string $overrideBurial = ''): ?array
    {
        $birthMinJD = ValidationService::getEffectiveJD($person, 'BIRT', $overrideBirth);
        $deathMaxJD = ValidationService::getEffectiveMaxJD($person, 'DEAT', $overrideDeath);
        $burialMaxJD = ValidationService::getEffectiveMaxJD($person, 'BURI', $overrideBurial);

        if (!$birthMinJD) return null;

        $endMaxJD = $deathMaxJD ?? $burialMaxJD;
        $endType = $deathMaxJD ? 'DEAT' : 'BURI';

        // Definitely impossible
        if ($endMaxJD && $birthMinJD > $endMaxJD) {
            $birthYear = ValidationService::getYearFromJD($birthMinJD);
            $endYear = ValidationService::getYearFromJD($endMaxJD);

            $birthPrecise = ValidationService::isPreciseDate($person, 'BIRT', $overrideBirth);
            $endPrecise = ValidationService::isPreciseDate($person, $endType, $endType === 'DEAT' ? $overrideDeath : $overrideBurial);

            $isImpreciseConflict = (!$birthPrecise || !$endPrecise) && ($birthYear <= $endYear);

            return [
                'code' => $isImpreciseConflict ? 'IMPRECISE_DATE_CONFLICT_BIRTH_DEATH' : 'BIRTH_AFTER_DEATH',
                'type' => 'temporal_impossibility',
                'label' => self::translate('Check sequence'),
                'severity' => $isImpreciseConflict ? 'info' : 'error',
                'message' => $isImpreciseConflict 
                    ? self::translate(
                        'Birth/Death dates are imprecise (%s - %s). Exact dates are missing.',
                        self::formatDate($person, 'BIRT', $overrideBirth) ?: $birthYear,
                        $deathMaxJD ? (self::formatDate($person, 'DEAT', $overrideDeath) ?: $endYear) : (self::formatDate($person, 'BURI', $overrideBurial) ?: $endYear)
                    )
                    : self::translate(
                        'Birth date (%s) is after %s (%s)',
                        self::formatDate($person, 'BIRT', $overrideBirth) ?: $birthYear,
                        $deathMaxJD ? self::translate('the death date') : self::translate('the burial'),
                        $deathMaxJD ? (self::formatDate($person, 'DEAT', $overrideDeath) ?: $endYear) : (self::formatDate($person, 'BURI', $overrideBurial) ?: $endYear)
                    ),
            ];
        }

        return null;
    }

    /**
     * Check lifespan plausibility
     */
    public static function checkLifespanPlausibility(?Individual $person, ?object $module = null, string $overrideBirth = '', string $overrideDeath = '', string $overrideBurial = ''): ?array
    {
        $birthYear = $person ? ValidationService::getEffectiveYear($person, 'BIRT', $overrideBirth) : ValidationService::parseYearOnly($overrideBirth);
        $deathYear = $person ? ValidationService::getEffectiveYear($person, 'DEAT', $overrideDeath) : ValidationService::parseYearOnly($overrideDeath);
        $burialYear = $person ? ValidationService::getEffectiveYear($person, 'BURI', $overrideBurial) : ValidationService::parseYearOnly($overrideBurial);

        $endYear = $deathYear ?? $burialYear;

        if ($birthYear && $endYear) {
            $lifespan = $endYear - $birthYear;
            $maxLifespan = (int)ValidationService::getModuleSetting($module, 'max_lifespan', '120');

            if ($lifespan > $maxLifespan) {
                return [
                    'code' => 'LIFESPAN_TOO_HIGH',
                    'type' => 'temporal_implausibility',
                    'label' => self::translate('Check age'),
                    'severity' => 'warning',
                    'message' => self::translate(
                        'Person%s lived %d years (Born %s - Died %s)',
                        $person ? ' "' . $person->fullName() . '"' : '',
                        $lifespan,
                        self::formatDate($person, 'BIRT', $overrideBirth) ?: $birthYear,
                        self::formatDate($person, 'DEAT', $overrideDeath) ?: (self::formatDate($person, 'BURI', $overrideBurial) ?: $endYear)
                    ),
                ];
            }
        }

        return null;
    }

    public static function checkBaptismBeforeBirth(?Individual $person, string $overrideBirth = '', string $overrideBap = ''): ?array
    {
        $birthMinJD = ValidationService::getEffectiveJD($person, 'BIRT', $overrideBirth);
        $bapMaxJD = ValidationService::getEffectiveMaxJD($person, 'CHR', $overrideBap);

        if (!$birthMinJD || !$bapMaxJD) return null;

        if ($bapMaxJD < $birthMinJD) {
            return [
                'code' => 'BAPTISM_BEFORE_BIRTH',
                'type' => 'chronological_inconsistency',
                'label' => self::translate('Check sequence'),
                'severity' => 'error',
                'message' => self::translate(
                    'Baptism (%s) is before birth (%s).',
                    self::formatDate($person, 'CHR', $overrideBap),
                    self::formatDate($person, 'BIRT', $overrideBirth)
                ),
            ];
        }

        return null;
    }

    /**
     * Check if burial is before death
     */
    public static function checkBurialBeforeDeath(?Individual $person, string $overrideDeath = '', string $overrideBurial = ''): ?array
    {
        $deathMinJD = ValidationService::getEffectiveJD($person, 'DEAT', $overrideDeath);
        $burialMaxJD = ValidationService::getEffectiveMaxJD($person, 'BURI', $overrideBurial);

        if (!$deathMinJD || !$burialMaxJD) return null;

        if ($burialMaxJD < $deathMinJD) {
            return [
                'code' => 'BURIAL_BEFORE_DEATH',
                'type' => 'chronological_inconsistency',
                'label' => self::translate('Check sequence'),
                'severity' => 'error',
                'message' => self::translate('Burial is before death.'),
            ];
        }

        return null;
    }

    /**
     * Check if a date is in the future
     */
    public static function checkFutureDate(?Individual $person, string $tag, string $overrideDate = ''): ?array
    {
        $dateMinJD = ValidationService::getEffectiveJD($person, $tag, $overrideDate);
        if (!$dateMinJD) return null;

        $now = new \DateTime();
        $currentJD = gregoriantojd((int)$now->format('n'), (int)$now->format('j'), (int)$now->format('Y'));

        if ($dateMinJD > $currentJD) {
            $year = ValidationService::getYearFromJD($dateMinJD);
            return [
                'code' => 'FUTURE_DATE_' . $tag,
                'type' => 'temporal_impossibility',
                'label' => self::translate('Check date'),
                'severity' => 'error',
                'message' => self::translate(
                    'The date for %s (%s) is in the future.',
                    I18N::translate($tag),
                    ValidationService::formatDate($person, $tag, $overrideDate) ?: $year
                ),
            ];
        }

        return null;
    }

    /**
     * Check if a person is likely dead
     */
    public static function checkLikelyDead(?Individual $person, ?object $module = null, string $overrideBirth = '', string $overrideDeath = ''): ?array
    {
        if (!$person && !$overrideBirth) return null;
        if ($overrideDeath) return null;

        if ($person && ($person->facts(['DEAT'])->first() || $person->facts(['BURI'])->first())) {
            return null;
        }

        $birthYear = $person ? ValidationService::getEffectiveYear($person, 'BIRT', $overrideBirth) : ValidationService::parseYearOnly($overrideBirth);
        if (!$birthYear) return null;

        $currentYear = (int)date('Y');
        $age = $currentYear - $birthYear;
        if ($age > 110) {
            $lastSignalYear = $birthYear;
            if ($person) {
                foreach ($person->facts() as $fact) {
                    $tag = strtoupper(trim($fact->tag()));
                    if (in_array($tag, ['CHAN', '_CHAN', 'RESN', 'REFN', 'RIN', 'UID', '_UID', 'OBJE', 'NOTE', 'SOUR', 'ASSO', 'BIRT', 'DEAT', 'BURI'])) continue;
                    $factDate = $fact->date();
                    if ($factDate->isOK()) {
                        $year = $factDate->minimumDate()->year();
                        if ($year > $lastSignalYear) $lastSignalYear = $year;
                    }
                }
            }
            return [
                'code' => 'LIKELY_DEAD',
                'type' => 'missing_data',
                'label' => self::translate('Likely dead'),
                'severity' => 'warning',
                'message' => self::translate(
                    'Person born %d years ago (%d) has no death record. Last known life signal: %d.',
                    $age,
                    $birthYear,
                    $lastSignalYear
                ),
            ];
        }
        return null;
    }

    /**
     * Check for Orphaned Facts (biographical events before birth or after death).
     * Finalized for version 1.5.2.
     */
    public static function checkOrphanedFacts(Individual $person): array
    {
        $issues = [];
        
        // 1. Establish Lifespan Extremes (Years)
        $birthYear = null;
        $deathYear = null;

        // Internal helper: robust year extraction (bypass strict validation)
        $extractYear = function($fact) {
            if (!$fact) return null;
            $rawText = $fact->date()->display() . ' ' . $fact->value();
            $p = DateParser::parseGedcomDate($rawText);
            return $p['year'] ?: null;
        };

        $birthDate = $person->getBirthDate();
        if ($birthDate->isOK()) $birthYear = $birthDate->minimumDate()->year();
        foreach ($person->facts(['BIRT', 'CHR', 'BAPM']) as $f) {
            $y = $extractYear($f);
            if ($y && ($birthYear === null || $y < $birthYear)) $birthYear = $y;
        }

        $deathDate = $person->getDeathDate();
        if ($deathDate->isOK()) $deathYear = $deathDate->maximumDate()->year();
        foreach ($person->facts(['DEAT', 'BURI']) as $f) {
            $y = $extractYear($f);
            if ($y && ($deathYear === null || $y > $deathYear)) $deathYear = $y;
        }

        if ($birthYear === null && $deathYear === null) return [];

        // 2. Scan ALL Facts
        foreach ($person->facts() as $fact) {
            $tag = strtoupper(trim($fact->tag()));
            
            // Normalize tag (strip prefixes like INDI: or FAM:) for blacklist check
            if (str_contains($tag, ':')) {
                $tag = substr($tag, strrpos($tag, ':') + 1);
            }
            
            // Hard blacklist against technical and boundary noise
            $blackList = [
                'CHAN', '_CHAN', 'UID', '_UID', 'RIN', 'REFN', 'RESN', 'BIRT', 'DEAT', 
                'CHR', 'BAPM', 'BURI', 'NOTE', 'SOUR', 'OBJE', 'SEX', 'NAME', 
                'FAMS', 'FAMC', 'ALIA', 'ANCI', 'DESI', 'ASSO', 'ATTR'
            ];
            if (in_array($tag, $blackList)) continue;
            
            // Filter out webtrees-specific technical custom tags
            if (str_starts_with($tag, '_')) {
                $noise = ['_TODO', '_TASK', '_UPD', '_UPDATED', '_WT_USER', '_SCN', '_TYPE', '_CHANGES', '_DATES', '_UID'];
                if (in_array($tag, $noise)) continue;
            }

            // Extract Year
            $fYear = $extractYear($fact);
            if (!$fYear) continue;

            // Case A: Before Birth (Biographical event should not occur before life starts)
            if ($birthYear !== null && $fYear < $birthYear) {
                $issues[] = [
                    'code' => 'FACT_BEFORE_BIRTH',
                    'type' => 'chronological_inconsistency',
                    'label' => self::translate('Check sequence'),
                    'severity' => 'error',
                    'message' => self::translate(
                        'Event "%s" (%d) occurs before birth (%d).',
                        $fact->label(),
                        $fYear,
                        $birthYear
                    ),
                    'xref' => $person->xref()
                ];
            }

            // Case B: After Death (Biographical event should not occur after life ends)
            // Note: Posthumous events like BURI are handled via blacklist above.
            if ($deathYear !== null && $fYear > $deathYear) {
                $issues[] = [
                    'code' => 'FACT_AFTER_DEATH',
                    'type' => 'chronological_inconsistency',
                    'label' => self::translate('Check sequence'),
                    'severity' => 'error',
                    'message' => self::translate(
                        'Event "%s" (%d) occurs after death (%d).',
                        $fact->label(),
                        $fYear,
                        $deathYear
                    ),
                    'xref' => $person->xref()
                ];
            }
        }

        return $issues;
    }
}
