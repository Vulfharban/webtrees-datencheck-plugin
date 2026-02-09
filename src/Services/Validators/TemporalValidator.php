<?php

namespace Wolfrum\Datencheck\Services\Validators;

use Fisharebest\Webtrees\Individual;
use Wolfrum\Datencheck\Services\ValidationService;

class TemporalValidator extends AbstractValidator
{
    /**
     * Check if person born after their own death
     */
    public static function checkBirthAfterDeath(?Individual $person, string $overrideBirth = '', string $overrideDeath = '', string $overrideBurial = ''): ?array
    {
        $birthJD = ValidationService::getEffectiveJD($person, 'BIRT', $overrideBirth);
        $deathJD = ValidationService::getEffectiveJD($person, 'DEAT', $overrideDeath);
        $burialJD = ValidationService::getEffectiveJD($person, 'BURI', $overrideBurial);

        $endJD = $deathJD ?? $burialJD;
        $label = $deathJD ? self::translate('Death date') : self::translate('Burial date');

        if ($birthJD && $endJD && $birthJD > $endJD) {
            $birthYear = ValidationService::getYearFromJD($birthJD);
            $endYear = ValidationService::getYearFromJD($endJD);
            
            return [
                'code' => 'BIRTH_AFTER_DEATH',
                'type' => 'temporal_impossibility',
                'label' => self::translate('Date conflict'),
                'severity' => 'error',
                'message' => self::translate(
                    'Birth date (%s) is after %s (%s)',
                    self::formatDate($person, 'BIRT', $overrideBirth) ?: $birthYear,
                    $deathJD ? self::translate('the death date') : self::translate('the burial'),
                    $deathJD ? (self::formatDate($person, 'DEAT', $overrideDeath) ?: $endYear) : (self::formatDate($person, 'BURI', $overrideBurial) ?: $endYear)
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
            $maxLifespan = $module ? (int)$module->getPreference('max_lifespan', '120') : 120;

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
    public static function checkBaptismBeforeBirth(?Individual $person, string $overrideBirth = ''): ?array
    {
        $birthJD = ValidationService::getEffectiveJD($person, 'BIRT', $overrideBirth);
        $bapFact = $person ? $person->facts(['CHR', 'BAPM'])->first() : null;
        $bapJD = $bapFact && $bapFact->date()->isOK() ? $bapFact->date()->minimumJulianDay() : null;

        if ($birthJD && $bapJD && $bapJD < $birthJD) {
            return [
                'code' => 'BAPTISM_BEFORE_BIRTH',
                'type' => 'chronological_inconsistency',
                'label' => self::translate('Check sequence'),
                'severity' => 'error',
                'message' => self::translate('Baptism is before birth.'),
            ];
        }

        return null;
    }

    /**
     * Check if burial is before death
     */
    public static function checkBurialBeforeDeath(?Individual $person, string $overrideDeath = '', string $overrideBurial = ''): ?array
    {
        $deathJD = ValidationService::getEffectiveJD($person, 'DEAT', $overrideDeath);
        $burialJD = ValidationService::getEffectiveJD($person, 'BURI', $overrideBurial);

        if ($deathJD && $burialJD && $burialJD < $deathJD) {
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
}
