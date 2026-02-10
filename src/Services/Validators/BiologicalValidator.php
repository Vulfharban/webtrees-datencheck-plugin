<?php

namespace Wolfrum\Datencheck\Services\Validators;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Tree;
use Wolfrum\Datencheck\Services\ValidationService;

class BiologicalValidator extends AbstractValidator
{
    /**
     * Check mother's age at birth
     */
    public static function checkMotherAgeAtBirth(?Individual $child, Individual $mother, ?object $module = null, string $overrideBirth = ''): ?array
    {
        $childYear = $child ? ValidationService::getEffectiveYear($child, 'BIRT', $overrideBirth) : ValidationService::parseYearOnly($overrideBirth);
        $motherYear = ValidationService::getEffectiveYear($mother, 'BIRT');

        if ($childYear && $motherYear) {
            $motherAge = $childYear - $motherYear;
            
            $minAge = (int)ValidationService::getModuleSetting($module, 'min_mother_age', '14');
            $maxAge = (int)ValidationService::getModuleSetting($module, 'max_mother_age', '50');

            if ($motherAge < $minAge) {
                return [
                    'code' => 'MOTHER_TOO_YOUNG',
                    'type' => 'biological_implausibility',
                    'label' => self::translate('Mother too young at birth'),
                    'severity' => 'error',
                    'message' => self::translate(
                        'Mother "%s" was only %d years old at birth (%s) (Mother born %s)',
                        $mother->fullName(),
                        $motherAge,
                        self::formatDate($child, 'BIRT', $overrideBirth) ?: $childYear,
                        self::formatDate($mother, 'BIRT') ?: $motherYear
                    ),
                ];
            }

            if ($motherAge > $maxAge) {
                return [
                    'code' => 'MOTHER_TOO_OLD',
                    'type' => 'biological_implausibility',
                    'label' => self::translate('Mother too old at birth'),
                    'severity' => 'warning',
                    'message' => self::translate(
                        'Mother "%s" was %d years old at birth (%s) (Mother born %s)',
                        $mother->fullName(),
                        $motherAge,
                        self::formatDate($child, 'BIRT', $overrideBirth) ?: $childYear,
                        self::formatDate($mother, 'BIRT') ?: $motherYear
                    ),
                ];
            }
        }

        return null;
    }

    /**
     * Check father's age at birth
     */
    public static function checkFatherAgeAtBirth(?Individual $child, Individual $father, ?object $module = null, string $overrideBirth = ''): ?array
    {
        $childYear = $child ? ValidationService::getEffectiveYear($child, 'BIRT', $overrideBirth) : ValidationService::parseYearOnly($overrideBirth);
        $fatherYear = ValidationService::getEffectiveYear($father, 'BIRT');

        if ($childYear && $fatherYear) {
            $fatherAge = $childYear - $fatherYear;
            
            $minAge = (int)ValidationService::getModuleSetting($module, 'min_father_age', '14');
            $maxAge = (int)ValidationService::getModuleSetting($module, 'max_father_age', '80');

            if ($fatherAge < $minAge) {
                return [
                    'code' => 'FATHER_TOO_YOUNG',
                    'type' => 'biological_implausibility',
                    'label' => self::translate('Father too young at birth'),
                    'severity' => 'error',
                    'message' => self::translate(
                        'Father "%s" was only %d years old at birth (%s) (Father born %s)',
                        $father->fullName(),
                        $fatherAge,
                        self::formatDate($child, 'BIRT', $overrideBirth) ?: $childYear,
                        self::formatDate($father, 'BIRT') ?: $fatherYear
                    ),
                ];
            }

            if ($fatherAge > $maxAge) {
                return [
                    'code' => 'FATHER_TOO_OLD',
                    'type' => 'biological_implausibility',
                    'label' => self::translate('Father too old at birth'),
                    'severity' => 'warning',
                    'message' => self::translate(
                        'Father "%s" was %d years old at birth (%s) (Father born %s)',
                        $father->fullName(),
                        $fatherAge,
                        self::formatDate($child, 'BIRT', $overrideBirth) ?: $childYear,
                        self::formatDate($father, 'BIRT') ?: $fatherYear
                    ),
                ];
            }
        }

        return null;
    }

    /**
     * Check if child was born after mother's death
     */
    public static function checkBirthAfterMotherDeath(?Individual $child, Individual $mother, string $overrideBirth = ''): ?array
    {
        $childYear = $child ? ValidationService::getEffectiveYear($child, 'BIRT', $overrideBirth) : ValidationService::parseYearOnly($overrideBirth);
        $deathYear = ValidationService::getEffectiveYear($mother, 'DEAT');
        $burialYear = ValidationService::getEffectiveYear($mother, 'BURI');
        
        $motherEndYear = $deathYear ?? $burialYear;

        if ($childYear && $motherEndYear && $childYear > $motherEndYear) {
            if ($childYear - $motherEndYear > 1) {
                return [
                    'code' => 'BIRTH_AFTER_MOTHER_DEATH',
                    'type' => 'biological_impossibility',
                    'label' => self::translate('Birth after mother\'s death'),
                    'severity' => 'error',
                    'message' => self::translate(
                        'Child born (%s) %d year(s) after death/burial (%s) of mother "%s"',
                        self::formatDate($child, 'BIRT', $overrideBirth) ?: $childYear,
                        $childYear - $motherEndYear,
                        $deathYear ? self::formatDate($mother, 'DEAT') : self::formatDate($mother, 'BURI'),
                        $mother->fullName()
                    ),
                ];
            }
        }

        return null;
    }

    /**
     * Check if child was born long after father's death (> 9 months)
     */
    public static function checkBirthLongAfterFatherDeath(?Individual $child, Individual $father, string $overrideBirth = ''): ?array
    {
        $childJD = ValidationService::getEffectiveJD($child, 'BIRT', $overrideBirth);
        $fatherDeathJD = ValidationService::getEffectiveJD($father, 'DEAT');
        $fatherBurialJD = ValidationService::getEffectiveJD($father, 'BURI');
        
        $fatherEndJD = $fatherDeathJD ?? $fatherBurialJD;

        if ($childJD && $fatherEndJD) {
            $diffDays = $childJD - $fatherEndJD;
            
            if ($diffDays > 280) {
                return [
                    'code' => 'BIRTH_AFTER_FATHER_DEATH',
                    'type' => 'biological_impossibility',
                    'label' => self::translate('Birth long after father\'s death'),
                    'severity' => 'error',
                    'message' => self::translate(
                        'Child born %d days after death/burial of father "%s" (Limit: 280 days)',
                        $diffDays,
                        $father->fullName()
                    ),
                ];
            }
        }

        return null;
    }
}
