<?php

namespace Wolfrum\Datencheck\Services\Validators;

use Fisharebest\Webtrees\Individual;
use Fisharebest\ExtCalendar\GregorianCalendar;
use Wolfrum\Datencheck\Services\ValidationService;

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
                'label' => self::translate('Date conflict'),
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

    /**
     * Check if baptism is before birth
     */
    public static function checkBaptismBeforeBirth(?Individual $person, string $overrideBirth = '', string $overrideBap = ''): ?array
    {
        $birthMinJD = ValidationService::getEffectiveJD($person, 'BIRT', $overrideBirth);
        $bapMinJD = ValidationService::getEffectiveJD($person, 'CHR', $overrideBap);
        $bapMaxJD = ValidationService::getEffectiveMaxJD($person, 'CHR', $overrideBap);

        if (!$birthMinJD || !$bapMinJD) return null;

        // Definitely impossible: Baptism range ends before birth range starts
        if ($bapMaxJD < $birthMinJD) {
            return [
                'code' => 'BAPTISM_BEFORE_BIRTH',
                'type' => 'chronological_inconsistency',
                'label' => self::translate('Check sequence'),
                'severity' => 'error',
                'message' => self::translate('Baptism is before birth.'),
            ];
        }

        // Potential conflict: Overlap or imprecise
        if ($bapMinJD < $birthMinJD) {
            $birthPrecise = ValidationService::isPreciseDate($person, 'BIRT', $overrideBirth);
            $bapPrecise = ValidationService::isPreciseDate($person, 'CHR', $overrideBap);

            if (!$birthPrecise || !$bapPrecise) {
                return [
                    'code' => 'IMPRECISE_DATE_CONFLICT_BAPTISM',
                    'type' => 'chronological_inconsistency',
                    'label' => self::translate('Check sequence'),
                    'severity' => 'info',
                    'message' => self::translate('Birth/Baptism dates are imprecise. Exact dates are missing.'),
                ];
            }
        }

        // Proximity check: Baptism should be close to birth (e.g. within 30 days for infant baptism)
        if ($birthMinJD && $bapMinJD && $bapMinJD > $birthMinJD) {
            $diff = $bapMinJD - $birthMinJD;
            if ($diff > 30 && $diff < 3650) { // More than 30 days but less than 10 years
                return [
                    'code' => 'BAPTISM_DELAYED',
                    'type' => 'chronological_inconsistency',
                    'label' => self::translate('Check sequence'),
                    'severity' => 'warning',
                    'message' => self::translate('Baptism is unusually long after birth (%d days).', $diff),
                ];
            }
        }

        return null;
    }

    /**
     * Check if burial is before death
     */
    public static function checkBurialBeforeDeath(?Individual $person, string $overrideDeath = '', string $overrideBurial = ''): ?array
    {
        $deathMinJD = ValidationService::getEffectiveJD($person, 'DEAT', $overrideDeath);
        $burialMinJD = ValidationService::getEffectiveJD($person, 'BURI', $overrideBurial);
        $burialMaxJD = ValidationService::getEffectiveMaxJD($person, 'BURI', $overrideBurial);

        if (!$deathMinJD || !$burialMinJD) return null;

        // Definitely impossible
        if ($burialMaxJD < $deathMinJD) {
            return [
                'code' => 'BURIAL_BEFORE_DEATH',
                'type' => 'chronological_inconsistency',
                'label' => self::translate('Check sequence'),
                'severity' => 'error',
                'message' => self::translate('Burial is before death.'),
            ];
        }

        // Potential conflict: Overlap or imprecise
        if ($burialMinJD < $deathMinJD) {
            $deathPrecise = ValidationService::isPreciseDate($person, 'DEAT', $overrideDeath);
            $burialPrecise = ValidationService::isPreciseDate($person, 'BURI', $overrideBurial);

            if (!$deathPrecise || !$burialPrecise) {
                return [
                    'code' => 'IMPRECISE_DATE_CONFLICT_BURIAL',
                    'type' => 'chronological_inconsistency',
                    'label' => self::translate('Check sequence'),
                    'severity' => 'info',
                    'message' => self::translate('Death/Burial dates are imprecise. Exact dates are missing.'),
                ];
            }
        }

    }

    /**
     * Check if a date is in the future (e.g. 2945 vs 1945)
     */
    public static function checkFutureDate(?Individual $person, string $tag, string $overrideDate = ''): ?array
    {
        $dateMinJD = ValidationService::getEffectiveJD($person, $tag, $overrideDate);
        if (!$dateMinJD) return null;

        // Current date JD
        $now = new \DateTime();
        $currentJD = gregoriantojd((int)$now->format('n'), (int)$now->format('j'), (int)$now->format('Y'));

        // Debug logging
        error_log("Datencheck Debug: Tag=$tag, DateJD=$dateMinJD, CurrentJD=$currentJD, Diff=" . ($dateMinJD - $currentJD));

        if ($dateMinJD > $currentJD) {
            $year = ValidationService::getYearFromJD($dateMinJD);
            
            $labels = [
                'BIRT' => self::translate('Birth'),
                'DEAT' => self::translate('Death'),
                'CHR'  => self::translate('Baptism'),
                'BURI' => self::translate('Burial'),
                'MARR' => self::translate('Marriage'),
            ];
            
            $label = $labels[$tag] ?? $tag;

            return [
                'code' => 'FUTURE_DATE_' . $tag,
                'type' => 'temporal_impossibility',
                'label' => self::translate('Check date'),
                'severity' => 'error',
                'message' => self::translate(
                    'The date for %s (%s) is in the future.',
                    $label,
                    ValidationService::formatDate($person, $tag, $overrideDate) ?: $year
                ),
            ];
        }

        return null;
    }
}
