<?php

namespace Wolfrum\Datencheck\Services;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Registry;
use Fisharebest\ExtCalendar\GregorianCalendar;
use Wolfrum\Datencheck\Helpers\DateParser;
use Wolfrum\Datencheck\Services\IgnoredErrorService;

/**
 * Validation Service for data plausibility checks
 */
class ValidationService
{
    public static function validatePerson(?Individual $person, ?object $module = null, string $overrideBirth = '', string $overrideDeath = '', string $overrideBurial = '', string $overrideHusb = '', string $overrideWife = '', string $overrideFam = '', ?Tree $tree = null, string $overrideMarr = '', string $relType = 'child', string $overrideGiven = '', string $overrideSurname = ''): array
    {
        $issues = [];
        $debug = [];
        $tree = $tree ?: ($person ? $person->tree() : \Fisharebest\Webtrees\Registry::treeFactory()->all()->first());
        
        // 0. Get Ignored Errors (if person exists)
        $ignoredCodes = [];
        if ($person) {
            $ignoredCodes = IgnoredErrorService::getIgnoredCodesForPerson($tree->id(), $person->xref());
        }

        // Biological plausibility checks (only if not a spouse relationship)
        $detectedParents = [];
        $resLog = [];
        if ($relType !== 'spouse') {
            // Get parents for checking (biological checks returns parents it found)
            $parentsResult = self::checkBiologicalPlausibility($person, $module, $overrideBirth, $overrideDeath, $overrideBurial, $overrideHusb, $overrideWife, $overrideFam, $tree, $overrideGiven, $overrideSurname, $debug);
            $issues = array_merge($issues, $parentsResult['issues']);
            $detectedParents = $parentsResult['parents'];
            $resLog = $parentsResult['res_log'];
        }

        // Temporal plausibility checks
        $issues = array_merge($issues, self::checkTemporalPlausibility($person, $module, $overrideBirth, $overrideDeath, $overrideBurial));

        // Marriage plausibility checks
        $issues = array_merge($issues, self::checkMarriagePlausibilityInteractive($person, $module, $overrideBirth, $overrideDeath, $overrideHusb, $overrideWife, $overrideFam, $tree, $overrideMarr, $relType));
        if ($person) {
            $issues = array_merge($issues, self::checkMarriagePlausibility($person, $module));
            $issues = array_merge($issues, self::checkGenderConsistency($person));
        }

        // Optional checks (only if enabled in module settings)
        if ($module) {
            if ($module->getPreference('enable_missing_data_checks', '0') === '1') {
                $issues = array_merge($issues, self::checkMissingData($person));
            }

            if ($module->getPreference('enable_geographic_checks', '0') === '1') {
                $issues = array_merge($issues, self::checkGeographicPlausibility($person));
            }

            if ($module->getPreference('enable_name_consistency_checks', '0') === '1') {
                $issues = array_merge($issues, self::checkNameConsistency($person, $overrideGiven, $overrideSurname, $detectedParents));
            }

            if ($module->getPreference('enable_source_checks', '0') === '1') {
                $issues = array_merge($issues, self::checkSourceQuality($person));
            }
        }

        // FILTER: Remove ignored issues
        if (!empty($ignoredCodes)) {
            $issues = array_filter($issues, function($issue) use ($ignoredCodes) {
                // Keep if no code (legacy) or code not in ignored list
                return !isset($issue['code']) || !in_array($issue['code'], $ignoredCodes);
            });
            // Re-index array
            $issues = array_values($issues);
        }

        $debug = array_merge($debug, [
            'person' => $person ? $person->fullName() : 'New Individual',
            'birth_year' => $person ? self::getEffectiveYear($person, 'BIRT', $overrideBirth) : self::parseYearOnly($overrideBirth),
            'tree' => $tree ? $tree->name() : 'none',
            'res_log' => $resLog,
            'overrides' => [
                'husb' => $overrideHusb,
                'wife' => $overrideWife,
                'fam' => $overrideFam,
                'rel' => $relType
            ],
            'parents' => array_map(function($pair) {
                return [
                    'wife' => $pair['mother'] ? $pair['mother']->fullName() . ' (' . $pair['mother']->xref() . ')' : 'none',
                    'husband' => $pair['father'] ? $pair['father']->fullName() . ' (' . $pair['father']->xref() . ')' : 'none',
                ];
            }, $detectedParents)
        ]);

        return ['issues' => $issues, 'debug' => $debug];
    }

    /**
     * Format date for display (e.g. 01.05.1980 or just 1980 if day/month missing)
     */
    private static function formatDate(?Individual $person, string $tag, string $override = ''): string
    {
        if ($override) {
            return $override;
        }

        if (!$person) {
            return '';
        }

        $fact = $person->facts([$tag])->first();
        if ($fact && $fact->date()->isOK()) {
            // Use Webtrees date object which handles localization and formatting (e.g. "01 May 1980")
            // Or fallback to YMD
            return $fact->date()->display();
        }

        return '';
    }

    /**
     * Check biological plausibility (parent ages, birth after death, etc.)
     *
     * @param Individual|null $person
     * @param object|null $module
     * @param string $overrideBirth
     * @param string $overrideDeath
     * @param string $overrideBurial
     * @param string $overrideHusb
     * @param string $overrideWife
     * @param string $overrideFam
     * @param Tree|null $tree
     * @param string $overrideGiven
     * @param string $overrideSurname
     * @param array $debug
     * @return array
     */
    private static function checkBiologicalPlausibility(?Individual $person, ?object $module = null, string $overrideBirth = '', string $overrideDeath = '', string $overrideBurial = '', string $overrideHusb = '', string $overrideWife = '', string $overrideFam = '', ?Tree $tree = null, string $overrideGiven = '', string $overrideSurname = '', array &$debug = []): array
    {
        $issues = [];
        $resLog = [];
        $registry = \Fisharebest\Webtrees\Registry::individualFactory();
        $tree = $tree ?: ($person ? $person->tree() : \Fisharebest\Webtrees\Registry::treeFactory()->all()->first());

        // 1. Identify Parents to check
        $parents = []; // [['mother' => Individual|null, 'father' => Individual|null]]

        // From DB
        if ($person) {
            foreach ($person->childFamilies() as $family) {
                $parents[] = ['mother' => $family->wife(), 'father' => $family->husband()];
            }
        }

        // From overrides (if no parents from DB or if we want to be explicit)
        if (empty($parents) && ($overrideHusb || $overrideWife || $overrideFam)) {
            $hId = trim($overrideHusb, '@ ');
            $wId = trim($overrideWife, '@ ');
            $fId = trim($overrideFam, '@ ');

            $husb = null;
            if ($hId) {
                // Aggressive resolution: Try multiple formats
                $hObj = null;
                $formats = [$hId, '@' . $hId . '@'];
                
                // If it starts with X, try I; if it starts with I, try X
                if (str_starts_with($hId, 'X')) $formats[] = 'I' . substr($hId, 1);
                if (str_starts_with($hId, 'I')) $formats[] = 'X' . substr($hId, 1);

                foreach ($formats as $f) {
                    $obj = $registry->make($f, $tree) ?: $registry->make('@' . $f . '@', $tree);
                    if ($obj && $obj->fullName() && $obj->fullName() !== 'Unknown') {
                        $hObj = $obj;
                        break;
                    }
                }

                if (!$hObj) {
                    // Fallback: Check if it's actually a family ID
                    if (\Fisharebest\Webtrees\DB::table('families')->where('f_id', $hId)->orWhere('f_id', '@' . $hId . '@')->exists()) {
                        $resLog[] = "Mapped $hId to family context.";
                        $fId = $hId;
                    } else {
                        $resLog[] = "Individual $hId not found.";
                    }
                } else {
                    $husb = $hObj;
                    $resLog[] = "Resolved husband $hId (" . $hObj->fullName() . ")";
                }
            }

            $wife = null;
            if ($wId) {
                // Aggressive resolution
                $wObj = null;
                $wFormats = [$wId, '@' . $wId . '@'];
                if (str_starts_with($wId, 'X')) $wFormats[] = 'I' . substr($wId, 1);
                if (str_starts_with($wId, 'I')) $wFormats[] = 'X' . substr($wId, 1);

                foreach ($wFormats as $f) {
                    $obj = $registry->make($f, $tree) ?: $registry->make('@' . $f . '@', $tree);
                    if ($obj && $obj->fullName() && $obj->fullName() !== 'Unknown') {
                        $wObj = $obj;
                        break;
                    }
                }

                if (!$wObj) {
                    if (\Fisharebest\Webtrees\DB::table('families')->where('f_id', $wId)->orWhere('f_id', '@' . $wId . '@')->exists()) {
                        $resLog[] = "Mapped $wId to family context.";
                        $fId = $wId;
                    } else {
                        $resLog[] = "Individual $wId not found.";
                    }
                } else {
                    $wife = $wObj;
                    $resLog[] = "Resolved wife $wId (" . $wObj->fullName() . ")";
                }
            }
            
            if ($fId) {
                $fFactory = \Fisharebest\Webtrees\Registry::familyFactory();
                $family = $fFactory->make($fId, $tree) ?: $fFactory->make('@' . $fId . '@', $tree);
                // Simple check if proxy has data
                if ($family && ($family->husband() || $family->wife())) {
                    $resLog[] = "Resolved family $fId";
                    $husb = $husb ?: $family->husband();
                    $wife = $wife ?: $family->wife();
                } else {
                    $otherF = \Fisharebest\Webtrees\DB::table('families')
                        ->where('f_id', $fId)
                        ->orWhere('f_id', '@' . $fId . '@')
                        ->value('f_file');
                    if ($otherF) {
                        $resLog[] = "Family $fId in tree $otherF, current " . $tree->id();
                    } else {
                        $resLog[] = "Family $fId not found.";
                    }
                }
            }
            
            if ($husb || $wife) {
                $parents[] = ['mother' => $wife, 'father' => $husb];
            }
        }

        // 2. Run checks for each identified parent pair
        foreach ($parents as $pair) {
            $motherCalculable = false;
            $mother = $pair['mother'];
            if ($mother) {
                // Check if we can calculate the mother's age
                $mBirth = self::getEffectiveYear($mother, 'BIRT');
                if ($mBirth !== null) {
                    $issue = self::checkMotherAgeAtBirth($person, $mother, $module, $overrideBirth);
                    if ($issue) $issues[] = $issue;
                    $motherCalculable = true;
                }

                $deathIssue = self::checkBirthAfterMotherDeath($person, $mother, $overrideBirth);
                if ($deathIssue) $issues[] = $deathIssue;
            }

            $father = $pair['father'];
            if ($father) {
                // Only check father as fallback if mother's age was not calculable
                if (!$mother || !$motherCalculable) {
                    $issue = self::checkFatherAgeAtBirth($person, $father, $module, $overrideBirth);
                    if ($issue) $issues[] = $issue;
                }

                // NEW: Check birth after father's death (> 9 months)
                $fDeathIssue = self::checkBirthLongAfterFatherDeath($person, $father, $overrideBirth);
                if ($fDeathIssue) $issues[] = $fDeathIssue;
            }

            // Check sibling spacing
            if ($mother || $father) {
                $thresholdMonths = $module ? (int)$module->getPreference('min_sibling_spacing_warning', '9') : 9;
                $issues = array_merge($issues, self::checkSiblingsSpacingInteractive($person, $mother, $father, $overrideBirth, $tree, $thresholdMonths, $debug, $overrideGiven));
            }
        }

        return ['issues' => $issues, 'parents' => $parents, 'res_log' => $resLog];
    }

    /**
     * Check temporal plausibility (dates consistency)
     *
     * @param Individual $person
     * @param object|null $module
     * @param string $overrideBirth
     * @param string $overrideDeath
     * @return array
     */
    private static function checkTemporalPlausibility(?Individual $person, ?object $module = null, string $overrideBirth = '', string $overrideDeath = '', string $overrideBurial = ''): array
    {
        $issues = [];

        // Check birth after death/burial
        $issue = self::checkBirthAfterDeath($person, $overrideBirth, $overrideDeath, $overrideBurial);
        if ($issue) {
            $issues[] = $issue;
        }

        // NEW: Check baptism before birth
        $issue = self::checkBaptismBeforeBirth($person, $overrideBirth);
        if ($issue) $issues[] = $issue;

        // NEW: Check burial before death
        $issue = self::checkBurialBeforeDeath($person, $overrideDeath, $overrideBurial);
        if ($issue) $issues[] = $issue;

        // Check lifespan plausibility
        $issue = self::checkLifespanPlausibility($person, $module, $overrideBirth, $overrideDeath, $overrideBurial);
        if ($issue) {
            $issues[] = $issue;
        }

        // Check marriage timing (only if person exists)
        if ($person) {
            foreach ($person->spouseFamilies() as $family) {
                $issue = self::checkMarriageBeforeBirth($person, $family, $overrideBirth);
                if ($issue) {
                    $issue['label'] = 'Heirat prüfen';
                    $issues[] = $issue;
                }

                $issue = self::checkMarriageAfterDeath($person, $family, $overrideDeath);
                if ($issue) {
                    $issue['label'] = 'Heirat prüfen';
                    $issues[] = $issue;
                }
            }
        }

        return $issues;
    }

    /**
     * Check gender consistency in family roles
     *
     * @param Individual $person
     * @return array
     */
    private static function checkGenderConsistency(?Individual $person): array
    {
        $issues = [];
        if (!$person) return $issues;

        foreach ($person->spouseFamilies() as $family) {
            $issue = self::checkGenderInFamily($person, $family);
            if ($issue) {
                $issue['label'] = 'Geschlecht prüfen';
                $issues[] = $issue;
            }
        }

        return $issues;
    }

    /**
     * Check mother's age at birth
     *
     * @param Individual $child
     * @param Individual $mother
     * @param object|null $module
     * @param string $overrideBirth
     * @return array|null
     */
    private static function checkMotherAgeAtBirth(?Individual $child, Individual $mother, ?object $module = null, string $overrideBirth = ''): ?array
    {
        $childYear = $child ? self::getEffectiveYear($child, 'BIRT', $overrideBirth) : self::parseYearOnly($overrideBirth);
        $motherYear = self::getEffectiveYear($mother, 'BIRT');

        if ($childYear && $motherYear) {
            $motherAge = $childYear - $motherYear;
            
            // Get threshold from module or use defaults
            $minAge = $module ? (int)$module->getPreference('min_mother_age', '14') : 14;
            $maxAge = $module ? (int)$module->getPreference('max_mother_age', '50') : 50;

            if ($motherAge < $minAge) {
                return [
                    'code' => 'MOTHER_TOO_YOUNG',
                    'type' => 'biological_implausibility',
                    'label' => 'Alter der Mutter',
                    'severity' => 'error',
                    'message' => sprintf(
                        'Mutter "%s" war bei Geburt (%s) nur %d Jahre alt (Mutter geb. %s)',
                        $mother->fullName(),
                        self::formatDate($child, 'BIRT', $overrideBirth) ?: $childYear,
                        $motherAge,
                        self::formatDate($mother, 'BIRT') ?: $motherYear
                    ),
                    'details' => [
                        'mother_name' => $mother->fullName(),
                        'mother_birth' => $motherYear,
                        'child_birth' => $childYear,
                        'calculated_age' => $motherAge,
                    ],
                ];
            }

            if ($motherAge > $maxAge) {
                return [
                    'code' => 'MOTHER_TOO_OLD',
                    'type' => 'biological_implausibility',
                    'label' => 'Alter der Mutter',
                    'severity' => 'warning',
                    'message' => sprintf(
                        'Mutter "%s" war bei Geburt (%s) %d Jahre alt (Mutter geb. %s)',
                        $mother->fullName(),
                        self::formatDate($child, 'BIRT', $overrideBirth) ?: $childYear,
                        $motherAge,
                        self::formatDate($mother, 'BIRT') ?: $motherYear
                    ),
                    'details' => [
                        'mother_name' => $mother->fullName(),
                        'mother_birth' => $motherYear,
                        'child_birth' => $childYear,
                        'calculated_age' => $motherAge,
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * Check father's age at birth
     *
     * @param Individual $child
     * @param Individual $father
     * @param object|null $module
     * @param string $overrideBirth
     * @return array|null
     */
    private static function checkFatherAgeAtBirth(?Individual $child, Individual $father, ?object $module = null, string $overrideBirth = ''): ?array
    {
        $childYear = $child ? self::getEffectiveYear($child, 'BIRT', $overrideBirth) : self::parseYearOnly($overrideBirth);
        $fatherYear = self::getEffectiveYear($father, 'BIRT');

        if ($childYear && $fatherYear) {
            $fatherAge = $childYear - $fatherYear;
            
            // Get threshold from module or use defaults
            $minAge = $module ? (int)$module->getPreference('min_father_age', '14') : 14;
            $maxAge = $module ? (int)$module->getPreference('max_father_age', '80') : 80;

            if ($fatherAge < $minAge) {
                return [
                    'code' => 'FATHER_TOO_YOUNG',
                    'type' => 'biological_implausibility',
                    'label' => 'Alter des Vaters',
                    'severity' => 'error',
                    'message' => sprintf(
                        'Vater "%s" war bei Geburt (%s) nur %d Jahre alt (Vater geb. %s)',
                        $father->fullName(),
                        self::formatDate($child, 'BIRT', $overrideBirth) ?: $childYear,
                        $fatherAge,
                        self::formatDate($father, 'BIRT') ?: $fatherYear
                    ),
                    'details' => [
                        'father_name' => $father->fullName(),
                        'father_birth' => $fatherYear,
                        'child_birth' => $childYear,
                        'calculated_age' => $fatherAge,
                    ],
                ];
            }

            if ($fatherAge > $maxAge) {
                return [
                    'code' => 'FATHER_TOO_OLD',
                    'type' => 'biological_implausibility',
                    'severity' => 'warning',
                    'message' => sprintf(
                        'Vater "%s" war bei Geburt (%s) %d Jahre alt (Vater geb. %s)',
                        $father->fullName(),
                        self::formatDate($child, 'BIRT', $overrideBirth) ?: $childYear,
                        $fatherAge,
                        self::formatDate($father, 'BIRT') ?: $fatherYear
                    ),
                    'details' => [
                        'father_name' => $father->fullName(),
                        'father_birth' => $fatherYear,
                        'child_birth' => $childYear,
                        'calculated_age' => $fatherAge,
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * Check if child was born after mother's death
     *
     * @param Individual $child
     * @param Individual $mother
     * @param string $overrideBirth
     * @return array|null
     */
    private static function checkBirthAfterMotherDeath(?Individual $child, Individual $mother, string $overrideBirth = ''): ?array
    {
        $childYear = $child ? self::getEffectiveYear($child, 'BIRT', $overrideBirth) : self::parseYearOnly($overrideBirth);
        $deathYear = self::getEffectiveYear($mother, 'DEAT');
        $burialYear = self::getEffectiveYear($mother, 'BURI');
        
        $motherEndYear = $deathYear ?? $burialYear;

        if ($childYear && $motherEndYear && $childYear > $motherEndYear) {
            // Allow up to 1 year difference (posthumous birth possible within ~9 months)
            if ($childYear - $motherEndYear > 1) {
                return [
                    'code' => 'BIRTH_AFTER_MOTHER_DEATH',
                    'type' => 'biological_impossibility',
                    'label' => 'Unmögliche Geburt',
                    'severity' => 'error',
                    'message' => sprintf(
                        'Kind geboren (%s) %d Jahr(e) nach Tod/Bestattung (%s) der Mutter "%s"',
                        self::formatDate($child, 'BIRT', $overrideBirth) ?: $childYear,
                        $childYear - $motherEndYear,
                        $deathYear ? self::formatDate($mother, 'DEAT') : self::formatDate($mother, 'BURI'),
                        $mother->fullName()
                    ),
                    'details' => [
                        'mother_name' => $mother->fullName(),
                        'mother_end' => $motherEndYear,
                        'child_birth' => $childYear,
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * Check if child was born long after father's death (> 9 months)
     */
    private static function checkBirthLongAfterFatherDeath(?Individual $child, Individual $father, string $overrideBirth = ''): ?array
    {
        $childJD = self::getEffectiveJD($child, 'BIRT', $overrideBirth);
        $fatherDeathJD = self::getEffectiveJD($father, 'DEAT');
        $fatherBurialJD = self::getEffectiveJD($father, 'BURI');
        
        $fatherEndJD = $fatherDeathJD ?? $fatherBurialJD;

        if ($childJD && $fatherEndJD) {
            $diffDays = $childJD - $fatherEndJD;
            
            // Posthumous birth possible up to ~280 days (9 months)
            if ($diffDays > 280) {
                return [
                    'code' => 'BIRTH_AFTER_FATHER_DEATH',
                    'type' => 'biological_impossibility',
                    'label' => 'Unmögliche Geburt',
                    'severity' => 'error',
                    'message' => sprintf(
                        'Kind geboren %d Tage nach Tod/Bestattung des Vaters "%s" (Limit: 280 Tage)',
                        $diffDays,
                        $father->fullName()
                    ),
                    'details' => [
                        'father_name' => $father->fullName(),
                        'father_end_jd' => $fatherEndJD,
                        'child_birth_jd' => $childJD,
                        'diff_days' => $diffDays,
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * Check if baptism is before birth
     */
    private static function checkBaptismBeforeBirth(?Individual $person, string $overrideBirth = ''): ?array
    {
        $birthJD = self::getEffectiveJD($person, 'BIRT', $overrideBirth);
        
        // Check for Baptism/Christening
        $bapFact = $person ? $person->facts(['CHR', 'BAPM'])->first() : null;
        $bapJD = $bapFact && $bapFact->date()->isOK() ? $bapFact->date()->minimumJulianDay() : null;

        if ($birthJD && $bapJD && $bapJD < $birthJD) {
            return [
                'code' => 'BAPTISM_BEFORE_BIRTH',
                'type' => 'chronological_inconsistency',
                'label' => 'Reihenfolge prüfen',
                'severity' => 'error',
                'message' => 'Taufe liegt vor der Geburt.',
            ];
        }

        return null;
    }

    /**
     * Check if burial is before death
     */
    private static function checkBurialBeforeDeath(?Individual $person, string $overrideDeath = '', string $overrideBurial = ''): ?array
    {
        $deathJD = self::getEffectiveJD($person, 'DEAT', $overrideDeath);
        $burialJD = self::getEffectiveJD($person, 'BURI', $overrideBurial);

        if ($deathJD && $burialJD && $burialJD < $deathJD) {
            return [
                'code' => 'BURIAL_BEFORE_DEATH',
                'type' => 'chronological_inconsistency',
                'label' => 'Reihenfolge prüfen',
                'severity' => 'error',
                'message' => 'Bestattung liegt vor dem Tod.',
            ];
        }

        return null;
    }

    /**
     * Check spacing between siblings
     *
     * @param Individual $child1
     * @param Individual $child2
     * @return array|null
     */
    private static function checkSiblingSpacing(Individual $child1, Individual $child2): ?array
    {
        $birth1 = $child1->getBirthDate();
        $birth2 = $child2->getBirthDate();

        if (!$birth1->isOK() || !$birth2->isOK()) {
            return null;
        }

        $year1 = $birth1->minimumDate()->year();
        $year2 = $birth2->minimumDate()->year();

        if ($year1 && $year2) {
            $yearDiff = abs($year1 - $year2);

            // If born in same year or consecutive years, check months
            if ($yearDiff < 1) {
                // For simplicity, we'll just warn if born in same year (could be twins, or issue)
                // A more sophisticated check would compare exact dates
                // We'll only report if we can determine it's less than 9 months
                
                // For now, skip same-year births (could be twins)
                return null;
            }
        }

        return null;
    }

    /**
     * Check if person born after their own death
     *
     * @param Individual|null $person
     * @param string $overrideBirth
     * @param string $overrideDeath
     * @param string $overrideBurial
     * @return array|null
     */
    private static function checkBirthAfterDeath(?Individual $person, string $overrideBirth = '', string $overrideDeath = '', string $overrideBurial = ''): ?array
    {
        $birthJD = self::getEffectiveJD($person, 'BIRT', $overrideBirth);
        $deathJD = self::getEffectiveJD($person, 'DEAT', $overrideDeath);
        $burialJD = self::getEffectiveJD($person, 'BURI', $overrideBurial);

        // Prefer death, fallback to burial
        $endJD = $deathJD ?? $burialJD;
        $label = $deathJD ? 'Todesdatum' : 'Bestattungsdatum';

        if ($birthJD && $endJD && $birthJD > $endJD) {
            $birthYear = self::getYearFromJD($birthJD);
            $endYear = self::getYearFromJD($endJD);
            
            return [
                'code' => 'BIRTH_AFTER_DEATH',
                'type' => 'temporal_impossibility',
                'label' => 'Datumskonflikt',
                'severity' => 'error',
                'message' => sprintf(
                    'Geburtsdatum (%s) liegt nach %s (%s)',
                    self::formatDate($person, 'BIRT', $overrideBirth) ?: $birthYear,
                    $label === 'Todesdatum' ? 'dem Todesdatum' : 'der Bestattung',
                    $label === 'Todesdatum' ? (self::formatDate($person, 'DEAT', $overrideDeath) ?: $endYear) : (self::formatDate($person, 'BURI', $overrideBurial) ?: $endYear)
                ),
            ];
        }

        return null;
    }

    /**
     * Check lifespan plausibility
     *
     * @param Individual $person
     * @param object|null $module
     * @param string $overrideBirth
     * @param string $overrideDeath
     * @return array|null
     */
    private static function checkLifespanPlausibility(?Individual $person, ?object $module = null, string $overrideBirth = '', string $overrideDeath = '', string $overrideBurial = ''): ?array
    {
        $birthYear = $person ? self::getEffectiveYear($person, 'BIRT', $overrideBirth) : self::parseYearOnly($overrideBirth);
        $deathYear = $person ? self::getEffectiveYear($person, 'DEAT', $overrideDeath) : self::parseYearOnly($overrideDeath);
        $burialYear = $person ? self::getEffectiveYear($person, 'BURI', $overrideBurial) : self::parseYearOnly($overrideBurial);

        $endYear = $deathYear ?? $burialYear;

        if ($birthYear && $endYear) {
            $lifespan = $endYear - $birthYear;
            
            // Get threshold from module or use default
            $maxLifespan = $module ? (int)$module->getPreference('max_lifespan', '120') : 120;

            if ($lifespan > $maxLifespan) {
                return [
                    'code' => 'LIFESPAN_TOO_HIGH',
                    'type' => 'temporal_implausibility',
                    'label' => 'Alter prüfen',
                    'severity' => 'warning',
                    'message' => sprintf(
                        'Person%s lebte %d Jahre (Geb. %s - Tod %s)',
                        $person ? ' "' . $person->fullName() . '"' : '',
                        $lifespan,
                        self::formatDate($person, 'BIRT', $overrideBirth) ?: $birthYear,
                        self::formatDate($person, 'DEAT', $overrideDeath) ?: (self::formatDate($person, 'BURI', $overrideBurial) ?: $endYear)
                    ),
                    'details' => [
                        'birth_date' => $birthYear,
                        'death_date' => $deathYear,
                        'lifespan' => $lifespan,
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * Check if marriage before birth
     *
     * @param Individual $person
     * @param Family $family
     * @param string $overrideBirth
     * @return array|null
     */
    private static function checkMarriageBeforeBirth(Individual $person, Family $family, string $overrideBirth = ''): ?array
    {
        $birthYear = self::getEffectiveYear($person, true, $overrideBirth);
        $marriage = $family->getMarriageDate();

        if ($birthYear && $marriage->isOK()) {
            $marriageYear = $marriage->minimumDate()->year();
            return [
                'code' => 'MARRIAGE_BEFORE_BIRTH',
                'type' => 'temporal_impossibility',
                'severity' => 'error',
                'message' => sprintf(
                    'Heirat (%s) vor Geburt (%s) von "%s"',
                    $marriage->display(),
                    self::formatDate($person, 'BIRT', $overrideBirth) ?: $birthYear,
                    $person->fullName()
                ),
                'details' => [
                    'birth_date' => $birthYear,
                    'marriage_date' => $marriageYear,
                ],
            ];
        }

        return null;
    }

    /**
     * Check if marriage after death
     *
     * @param Individual $person
     * @param Family $family
     * @param string $overrideDeath
     * @return array|null
     */
    private static function checkMarriageAfterDeath(Individual $person, Family $family, string $overrideDeath = ''): ?array
    {
        $deathYear = self::getEffectiveYear($person, false, $overrideDeath);
        $marriage = $family->getMarriageDate();

        if ($deathYear && $marriage->isOK()) {
            $marriageYear = $marriage->minimumDate()->year();
            return [
                'code' => 'MARRIAGE_AFTER_DEATH',
                'type' => 'temporal_impossibility',
                'severity' => 'error',
                'message' => sprintf(
                    'Heirat (%s) nach Tod (%s) von "%s"',
                    $marriage->display(),
                    self::formatDate($person, 'DEAT', $overrideDeath) ?: $deathYear,
                    $person->fullName()
                ),
                'details' => [
                    'death_date' => $deathYear,
                    'marriage_date' => $marriageYear,
                ],
            ];
        }

        return null;
    }

    /**
     * Check gender consistency in family
     *
     * @param Individual $person
     * @param Family $family
     * @return array|null
     */
    private static function checkGenderInFamily(Individual $person, Family $family): ?array
    {
        $sex = $person->sex();

        // Check if person is husband
        if ($family->husband() && $family->husband()->xref() === $person->xref()) {
            if ($sex === 'F') {
                return [
                    'code' => 'GENDER_MISMATCH_HUSBAND',
                    'type' => 'gender_inconsistency',
                    'severity' => 'error',
                    'message' => sprintf(
                        'Person "%s" ist als Ehemann eingetragen, aber weiblich',
                        $person->fullName()
                    ),
                    'details' => [
                        'person_sex' => 'F',
                        'role' => 'HUSB',
                    ],
                ];
            }
        }

        // Check if person is wife
        if ($family->wife() && $family->wife()->xref() === $person->xref()) {
            if ($sex === 'M') {
                return [
                    'code' => 'GENDER_MISMATCH_WIFE',
                    'type' => 'gender_inconsistency',
                    'severity' => 'error',
                    'message' => sprintf(
                        'Person "%s" ist als Ehefrau eingetragen, aber männlich',
                        $person->fullName()
                    ),
                    'details' => [
                        'person_sex' => 'M',
                        'role' => 'WIFE',
                    ],
                ];
            }
        }

        return null;
    }

    /**
     * Check marriage plausibility for interactive 'Add Spouse' flow or general edits
     */
    private static function checkMarriagePlausibilityInteractive(?Individual $person, ?object $module, string $birth, string $death, string $husbXref, string $wifeXref, string $famXref, ?Tree $tree, string $marrDate, string $relType): array
    {
        $issues = [];
        $mYear = self::parseYearOnly($marrDate);
        if ($mYear === null) return $issues;

        $registry = \Fisharebest\Webtrees\Registry::individualFactory();
        $husb = $husbXref ? $registry->make($husbXref, $tree) : null;
        $wife = $wifeXref ? $registry->make($wifeXref, $tree) : null;

        $partners = [];
        if ($husb) $partners[] = ['indiv' => $husb, 'role' => 'Partner'];
        if ($wife) $partners[] = ['indiv' => $wife, 'role' => 'Partner'];
        
        $subjBirth = self::parseYearOnly($birth);
        $subjDeath = self::parseYearOnly($death);
        $maxMarrAge = $module ? (int)$module->getPreference('max_marriage_age_warning', '100') : 100;
        $minMarrAge = $module ? (int)$module->getPreference('min_marriage_age_warning', '15') : 15;

        foreach ($partners as $p) {
            $indiv = $p['indiv'];
            $pBirth = self::getEffectiveYear($indiv, 'BIRT');
            $pDeath = self::getEffectiveYear($indiv, 'DEAT');

            if ($pBirth) {
                if ($mYear < $pBirth) {
                    $issues[] = [
                        'code' => 'MARRIAGE_BEFORE_PARTNER_BIRTH',
                        'type' => 'marr_before_birth',
                        'label' => 'Heirat prüfen',
                        'severity' => 'error',
                        'message' => sprintf('Heirat (%d) liegt vor der Geburt von %s (%d)', $mYear, $indiv->fullName(), $pBirth),
                    ];
                } else {
                    $age = $mYear - $pBirth;
                    if ($age < $minMarrAge) {
                        $issues[] = [
                            'code' => 'MARRIAGE_PARTNER_TOO_YOUNG',
                            'type' => 'marr_unusually_early',
                            'label' => 'Heirat prüfen',
                            'severity' => 'warning',
                            'message' => sprintf('%s "%s" war bei Heirat erst %d Jahre alt', $p['role'], $indiv->fullName(), $age),
                        ];
                    } elseif ($age > $maxMarrAge) {
                        $issues[] = [
                            'code' => 'MARRIAGE_PARTNER_TOO_OLD',
                            'type' => 'marr_unusually_late',
                            'label' => 'Heirat prüfen',
                            'severity' => 'warning',
                            'message' => sprintf('%s "%s" war bei Heirat bereits %d Jahre alt', $p['role'], $indiv->fullName(), $age),
                        ];
                    }
                }
            }
            if ($pDeath && $mYear > $pDeath) {
                $issues[] = [
                    'code' => 'MARRIAGE_AFTER_PARTNER_DEATH',
                    'type' => 'marr_after_death',
                    'label' => 'Heirat prüfen',
                    'severity' => 'error',
                    'message' => sprintf('Heirat (%d) liegt nach dem Tod von %s (%d)', $mYear, $indiv->fullName(), $pDeath),
                ];
            }
        }

        // Check subject birth/death vs marriage
        if ($subjBirth) {
            if ($mYear < $subjBirth) {
                $issues[] = [
                    'code' => 'MARRIAGE_BEFORE_BIRTH',
                    'type' => 'marr_before_birth',
                    'label' => 'Heirat prüfen',
                    'severity' => 'error',
                    'message' => sprintf('Heirat (%d) liegt vor der eigenen Geburt (%d)', $mYear, $subjBirth),
                ];
            } else {
                $age = $mYear - $subjBirth;
                if ($age < $minMarrAge) {
                    $issues[] = [
                        'code' => 'MARRIAGE_TOO_YOUNG',
                        'type' => 'marr_unusually_early',
                        'label' => 'Heirat prüfen',
                        'severity' => 'warning',
                        'message' => sprintf('Person war bei Heirat erst %d Jahre alt', $age),
                    ];
                } elseif ($age > $maxMarrAge) {
                    $issues[] = [
                        'code' => 'MARRIAGE_TOO_OLD',
                        'type' => 'marr_unusually_late',
                        'label' => 'Heirat prüfen',
                        'severity' => 'warning',
                        'message' => sprintf('Person war bei Heirat bereits %d Jahre alt', $age),
                    ];
                }
            }
        }
        if ($subjDeath && $mYear > $subjDeath) {
            $issues[] = [
                'code' => 'MARRIAGE_AFTER_DEATH',
                'type' => 'marr_after_death',
                'label' => 'Heirat prüfen',
                'severity' => 'error',
                'message' => sprintf('Heirat (%d) liegt nach dem eigenen Tod (%d)', $mYear, $subjDeath),
            ];
        }

        // Gender role checks
        if ($husb && $husb->sex() === 'F') {
            $issues[] = [
                'code' => 'GENDER_MISMATCH_HUSBAND',
                'type' => 'gender_inconsistency',
                'label' => 'Geschlecht prüfen',
                'severity' => 'error',
                'message' => sprintf('Ehemann "%s" ist als weiblich markiert', $husb->fullName()),
            ];
        }
        if ($wife && $wife->sex() === 'M') {
            $issues[] = [
                'code' => 'GENDER_MISMATCH_WIFE',
                'type' => 'gender_inconsistency',
                'label' => 'Geschlecht prüfen',
                'severity' => 'error',
                'message' => sprintf('Ehefrau "%s" ist als männlich markiert', $wife->fullName()),
            ];
        }

        return $issues;
    }

    /**
     * Check marriage plausibility (overlapping marriages, too many marriages)
     *
     * @param Individual $person
     * @param object|null $module
     * @return array
     */
    private static function checkMarriagePlausibility(?Individual $person, ?object $module = null): array
    {
        $issues = [];
        if (!$person) return $issues;

        $families = $person->spouseFamilies();

        // Check for too many marriages
        $maxMarriages = $module ? (int)$module->getPreference('max_marriages_warning', '5') : 5;
        $marriageCount = $families->count();

        if ($marriageCount > $maxMarriages) {
            $issues[] = [
                'code' => 'TOO_MANY_MARRIAGES',
                'type' => 'marriage_unusually_many',
                'label' => 'Anzahl Ehen',
                'severity' => 'info',
                'message' => sprintf(
                    'Person "%s" hat %d Ehen (ungewöhnlich viele)',
                    $person->fullName(),
                    $marriageCount
                ),
                'details' => [
                    'marriage_count' => $marriageCount,
                    'threshold' => $maxMarriages,
                ],
            ];
        }

        // Check for overlapping marriages
        $marriageDates = [];
        foreach ($families as $family) {
            $marriageDate = $family->getMarriageDate();
            if ($marriageDate->isOK()) {
                $marriageDates[] = [
                    'family' => $family,
                    'year' => $marriageDate->minimumDate()->year(),
                ];
            }
        }

        // Sort by year
        usort($marriageDates, function($a, $b) {
            return $a['year'] <=> $b['year'];
        });

        // Check for overlaps (simple check: marriage before previous spouse's death)
        for ($i = 1; $i < count($marriageDates); $i++) {
            $prevFamily = $marriageDates[$i - 1]['family'];
            $currentMarriageYear = $marriageDates[$i]['year'];

            // Get the other spouse from previous family
            $prevSpouse = $prevFamily->husband() && $prevFamily->husband()->xref() !== $person->xref()
                ? $prevFamily->husband()
                : $prevFamily->wife();

            if ($prevSpouse) {
                $prevSpouseDeath = $prevSpouse->getDeathDate();

                // If previous spouse has no death date or died after current marriage
                if (!$prevSpouseDeath->isOK()) {
                    // Can't determine - might be overlapping
                    $issues[] = [
                        'code' => 'MARRIAGE_POSSIBLY_OVERLAPPING',
                        'type' => 'marriage_possibly_overlapping',
                        'label' => 'Zeitliche Überschneidung',
                        'severity' => 'warning',
                        'message' => sprintf(
                            'Heirat (%d) möglicherweise während bestehender Ehe mit "%s" (Todesdatum unbekannt)',
                            $currentMarriageYear,
                            $prevSpouse->fullName()
                        ),
                        'details' => [
                            'marriage_year' => $currentMarriageYear,
                            'previous_spouse' => $prevSpouse->fullName(),
                        ],
                    ];
                } else {
                    $prevSpouseDeathYear = $prevSpouseDeath->maximumDate()->year();
                    if ($currentMarriageYear < $prevSpouseDeathYear) {
                        $issues[] = [
                            'code' => 'MARRIAGE_OVERLAPPING',
                            'type' => 'marriage_overlapping',
                            'label' => 'Zeitliche Überschneidung',
                            'severity' => 'error',
                            'message' => sprintf(
                                'Heirat (%d) vor Tod (%d) des vorherigen Ehepartners "%s"',
                                $currentMarriageYear,
                                $prevSpouseDeathYear,
                                $prevSpouse->fullName()
                            ),
                            'details' => [
                                'marriage_year' => $currentMarriageYear,
                                'previous_spouse' => $prevSpouse->fullName(),
                                'previous_spouse_death' => $prevSpouseDeathYear,
                            ],
                        ];
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * Check for missing data
     *
     * @param Individual|null $person
     * @return array
     */
    private static function checkMissingData(?Individual $person): array
    {
        $issues = [];
        if (!$person) return $issues;

        $birth = $person->getBirthDate();
        $death = $person->getDeathDate();
        $hasChildren = $person->spouseFamilies()->reduce(function($carry, $family) {
            return $carry || $family->children()->count() > 0;
        }, false);

        // Person has children but no birth date
        if ($hasChildren && !$birth->isOK()) {
            $issues[] = [
                'code' => 'MISSING_BIRTH_DATE',
                'type' => 'missing_birth_date',
                'label' => 'Daten vervollständigen',
                'severity' => 'info',
                'message' => sprintf(
                    'Person "%s" hat Kinder, aber kein Geburtsdatum',
                    $person->fullName()
                ),
            ];
        }

        // Person has death date but no birth date
        if ($death->isOK() && !$birth->isOK()) {
            $issues[] = [
                'code' => 'DEATH_WITHOUT_BIRTH',
                'type' => 'death_without_birth',
                'label' => 'Daten vervollständigen',
                'severity' => 'warning',
                'message' => sprintf(
                    'Person "%s" hat Todesdatum aber kein Geburtsdatum',
                    $person->fullName()
                ),
            ];
        }

        return $issues;
    }

    /**
     * Check geographic plausibility
     *
     * @param Individual|null $person
     * @return array
     */
    /**
     * Check geographic plausibility (Distance and Travel Speed)
     *
     * @param Individual|null $person
     * @return array
     */
    private static function checkGeographicPlausibility(?Individual $person): array
    {
        $issues = [];
        if (!$person) return $issues;

        // Get birth and death places
        $birthCtx = self::getPlaceContext($person, 'BIRT');
        $deathCtx = self::getPlaceContext($person, 'DEAT');

        if (!$birthCtx || !$deathCtx) return $issues;

        $distKm = self::calculateHaversineDistance(
            $birthCtx['lat'], $birthCtx['lon'],
            $deathCtx['lat'], $deathCtx['lon']
        );

        if ($distKm > 0) {
            // Check 1: Excessive distance check (Info)
            if ($distKm > 1000) {
                $issues[] = [
                    'code' => 'LONG_DISTANCE_MIGRATION',
                    'type' => 'geographic_info',
                    'label' => 'Große Distanz',
                    'severity' => 'info',
                    'message' => sprintf(
                        'Zwischen Geburt (%s) und Tod (%s) liegen ca. %d km.',
                        $birthCtx['name'],
                        $deathCtx['name'],
                        round($distKm)
                    ),
                    'details' => [
                        'from' => $birthCtx['name'],
                        'to' => $deathCtx['name'],
                        'km' => round($distKm)
                    ]
                ];
            }

            // Check 2: Travel Speed (Teleportation Check)
            // Need dates for this
            $bJD = $person->getBirthDate()->isOK() ? $person->getBirthDate()->minimumJulianDay() : null;
            $dJD = $person->getDeathDate()->isOK() ? $person->getDeathDate()->minimumJulianDay() : null;

            if ($bJD && $dJD) {
                $days = $dJD - $bJD;
                
                // If events are essentially on the same day or 1 day apart
                if ($days >= 0 && $days < 2) {
                    // Maximum plausible travel in 1 day?
                    // 1800s: Horse ~50km/day
                    // Modern: Plane ~15000km/day
                    // Checking for obvious errors like "Same date, different continent"
                    
                    $limit = 500; // Generic safe limit for < 2 days
                    
                    if ($distKm > $limit) {
                        $issues[] = [
                            'code' => 'IMPOSSIBLE_TRAVEL_SPEED',
                            'type' => 'geographic_implausibility',
                            'label' => 'Ort/Zeit prüfen',
                            'severity' => 'error',
                            'message' => sprintf(
                                'Unmögliche Reise: %d km in < 2 Tagen zwischen "%s" und "%s".',
                                round($distKm),
                                $birthCtx['name'],
                                $deathCtx['name']
                            ),
                        ];
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * Helper to get place name and coordinates
     */
    private static function getPlaceContext(Individual $person, string $factType): ?array
    {
        $fact = $person->facts([$factType])->first();
        if (!$fact) return null;

        $placeVal = $fact->place();
        if (!$placeVal) return null;

        $placeName = $placeVal->gedcomName();
        // Lookup coordinates via Webtrees Location factory (PlaceFactory deprecated/removed)
        $placeObj = Registry::locationFactory()->make($placeName, $person->tree());
        
        if ($placeObj && $placeObj->latitude() !== null && $placeObj->longitude() !== null) {
            return [
                'name' => $placeName,
                'lat'  => (float) $placeObj->latitude(),
                'lon'  => (float) $placeObj->longitude()
            ];
        }

        return null;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     * Returns distance in Kilometers
     */
    private static function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check name consistency
     *
     * @param Individual|null $person
     * @param string $overrideGiven
     * @param string $overrideSurname
     * @param array $parents
     * @return array
     */
    private static function checkNameConsistency(?Individual $person, string $overrideGiven = '', string $overrideSurname = '', array $parents = []): array
    {
        $issues = [];
        $allNames = $person ? $person->getAllNames() : [];
        
        // If we have overrides, incorporate them (might be multiple pipe-separated)
        if (!empty($overrideGiven)) {
            $givenList = explode('|', $overrideGiven);
            $surnameList = explode('|', $overrideSurname);
            foreach ($givenList as $i => $g) {
                // For new individuals or those without names, the first override becomes "primary"
                // For existing ones, it's just one of the names to check
                $allNames[] = [
                    'type' => $i === 0 ? 'NAME' : 'NAME_OVERRIDE',
                    'givn' => $g,
                    'surn' => $surnameList[$i] ?? ($surnameList[0] ?? ''),
                    'full' => $g . ' ' . ($surnameList[$i] ?? ($surnameList[0] ?? ''))
                ];
            }
        }

        if (empty($allNames)) return $issues;

        $primaryName = $allNames[0];
        $fullName = $primaryName['full'] ?? '';
        $givenNamePrimary = mb_strtolower($primaryName['givn'] ?? '');
        $surnamePrimary = $primaryName['surn'] ?? '';

        // Check for basic consistency (missing parts)
        if (empty($givenNamePrimary) && !empty($surnamePrimary)) {
            $issues[] = [
                'code' => 'MISSING_GIVEN_NAME',
                'type' => 'missing_given_name',
                'label' => 'Vorname fehlt',
                'severity' => 'warning',
                'message' => 'Person hat einen Nachnamen, aber keinen Vornamen',
            ];
        }

        // Compare with other names (e.g. Married Name)
        foreach ($allNames as $index => $name) {
            if ($index === 0) continue;

            $type = strtoupper($name['type'] ?? '');
            $givenOther = mb_strtolower($name['givn'] ?? '');
            
            // We focus on Married Names (_MARNM) or any secondary NAME tag
            if (!empty($givenOther) && $givenOther !== $givenNamePrimary) {
                // Similarity check (Maria vs Elisabeth should fail)
                $lev = levenshtein($givenNamePrimary, $givenOther);
                $maxLen = max(strlen($givenNamePrimary), strlen($givenOther));
                
                // If more than 30% or 40% different
                if ($maxLen > 0 && ($lev / $maxLen) > 0.4) {
                    $typeName = ($type === '_MARNM' || str_contains($type, 'MARRIED')) ? 'Ehename' : 'Alternativer Name';
                    $issues[] = [
                        'code' => 'NAME_MISMATCH',
                        'type' => 'name_mismatch',
                        'label' => 'Vorname prüfen',
                        'severity' => 'warning',
                        'message' => sprintf(
                            'Unterschiedliche Vornamen entdeckt: "%s" (%s) vs. "%s" (Geburtsname)',
                            $name['givn'],
                            $typeName,
                            $primaryName['givn']
                        ),
                    ];
                }
            }
        }

        // Check for special characters
        if (preg_match('/[\x00-\x1F\x7F]/', $fullName)) {
            $issues[] = [
                'code' => 'NAME_ENCODING_ISSUE',
                'type' => 'name_encoding_issue',
                'label' => 'Zeichensatz prüfen',
                'severity' => 'warning',
                'message' => sprintf('Name "%s" enthält ungültige Zeichen (Encoding-Problem?)', $fullName),
            ];
        }

        // Parent-Child Surname consistency
        if (!empty($parents) && !empty($surnamePrimary)) {
            $childSurnNorm = str_replace('ß', 'ss', mb_strtolower(trim(strip_tags($surnamePrimary)), 'UTF-8'));
            $fatherMatch = false;
            $motherMatch = false;
            $fathersFound = 0;

            foreach ($parents as $pair) {
                $father = $pair['father'];
                $mother = $pair['mother'];

                if ($father) {
                    $fathersFound++;
                    $fSurn = self::getIndividualSurname($father);
                    $fSurnNorm = str_replace('ß', 'ss', mb_strtolower(trim(strip_tags($fSurn)), 'UTF-8'));
                    if ($childSurnNorm === $fSurnNorm || str_contains($fSurnNorm, $childSurnNorm) || str_contains($childSurnNorm, $fSurnNorm)) {
                        $fatherMatch = true;
                    }
                }

                if ($mother) {
                    $mSurn = self::getIndividualSurname($mother);
                    $mSurnNorm = str_replace('ß', 'ss', mb_strtolower(trim(strip_tags($mSurn)), 'UTF-8'));
                    if ($childSurnNorm === $mSurnNorm || str_contains($mSurnNorm, $childSurnNorm) || str_contains($childSurnNorm, $mSurnNorm)) {
                        $motherMatch = true;
                    }
                }
            }

            if ($fathersFound > 0 && !$fatherMatch) {
                if ($motherMatch) {
                    $issues[] = [
                        'code' => 'SURNAME_MISMATCH_MOTHER',
                        'type' => 'surname_mismatch_mother',
                        'label' => 'Nachname prüfen',
                        'severity' => 'info',
                        'message' => sprintf('Nachname "%s" entspricht der Mutter, weicht aber vom Vater ab.', $surnamePrimary),
                    ];
                } else {
                    $issues[] = [
                        'code' => 'SURNAME_MISMATCH_FATHER',
                        'type' => 'surname_mismatch_father',
                        'label' => 'Nachname prüfen',
                        'severity' => 'warning',
                        'message' => sprintf('Nachname "%s" weicht vom Vater ab.', $surnamePrimary),
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Get the year from either the override date string or the person's stored data
     *
     * @param Individual $person
     * @param string $type Event type ('BIRT', 'DEAT', 'BURI')
     * @param string $override
     * @return int|null
     */
    private static function getEffectiveYear(?Individual $person, string $type, string $override = ''): ?int
    {
        if (!empty(trim($override))) {
            return self::parseYearOnly($override);
        }

        if (!$person) {
            return null;
        }

        switch($type) {
            case 'BIRT':
                $date = $person->getBirthDate();
                break;
            case 'DEAT':
                $date = $person->getDeathDate();
                break;
            case 'BURI':
                // Individual doesn't have a direct getBurialDate() usually, 
                // but we can check the facts
                $fact = $person->facts(['BURI'])->first();
                $date = $fact ? $fact->date() : new \Fisharebest\Webtrees\Date('');
                break;
            default:
                return null;
        }

        return $date->isOK() ? (int)$date->minimumDate()->year() : null;
    }

    /**
     * Parse a year from a GEDCOM date string without needing an individual object
     *
     * @param string $date
     * @return int|null
     */
    private static function parseYearOnly(string $date): ?int
    {
        if (empty(trim($date))) {
            return null;
        }

        $parsed = DateParser::parseGedcomDate($date);
        return $parsed['year'];
    }
    /**
     * Check sibling spacing for interactive flow
     */
    private static function checkSiblingsSpacingInteractive(?Individual $person, ?Individual $mother, ?Individual $father, string $overrideBirth, \Fisharebest\Webtrees\Tree $tree, int $thresholdMonths = 9, array &$debug = [], string $overrideGiven = ''): array
    {
        $issues = [];
        $subjJD = self::getEffectiveJD($person, 'BIRT', $overrideBirth);
        if (!$subjJD) return $issues;
        
        // Convert months to days (approx)
        $thresholdDays = $thresholdMonths * 30.44;

        // 1. Gather all unique siblings from both parents
        $siblings = [];
        $families = [];
        if ($mother) {
            foreach ($mother->spouseFamilies() as $fam) {
                $husb = $fam->husband();
                // Compare XREFs instead of object identity
                if (!$father || ($husb && $husb->xref() === $father->xref())) {
                    $families[$fam->xref()] = $fam;
                }
            }
        } elseif ($father) {
            foreach ($father->spouseFamilies() as $fam) {
                $families[$fam->xref()] = $fam;
            }
        }

        foreach ($families as $fam) {
            foreach ($fam->children() as $child) {
                if ($person && $child->xref() === $person->xref()) continue;
                $siblings[$child->xref()] = $child;
            }
        }
        
        // 2. Compare birth dates
        foreach ($siblings as $sib) {
            $sibJD = null;
            $sibDisplayDate = '';
            $sibLabel = 'Geburt';
            
            $bDate = $sib->getBirthDate();
            if ($bDate->isOK()) {
                $sibJD = $bDate->minimumJulianDay();
                $sibDisplayDate = $bDate->display();
            } else {
                // Fallback to Baptism
                $fact = $sib->facts(['CHR', 'BAPM'])->first();
                if ($fact && $fact->date()->isOK()) {
                    $sibJD = $fact->date()->minimumJulianDay();
                    $sibDisplayDate = $fact->date()->display();
                    $sibLabel = 'Taufe';
                }
            }

            if (!$sibJD) continue;
        
        $diffDays = abs($subjJD - $sibJD);

        // 1. Duplicate detection (same name, same birth date/year)
        // Extract given names for subject
        $subjGiven = $person ? (method_exists($person, 'givenName') ? $person->givenName() : $person->fullName()) : '';
        if (empty($subjGiven) && !empty($overrideGiven)) $subjGiven = $overrideGiven;
        
        $sibGiven = method_exists($sib, 'givenName') ? $sib->givenName() : $sib->fullName();
        
        // Normalize for comparison: strip tags and handle special characters
        $subjNorm = str_replace('ß', 'ss', mb_strtolower(trim(strip_tags($subjGiven)), 'UTF-8'));
        $sibNorm = str_replace('ß', 'ss', mb_strtolower(trim(strip_tags($sibGiven)), 'UTF-8'));
        
        // Also check if they are in the same calendar year
        $subjYear = $person ? self::getEffectiveYear($person, 'BIRT', $overrideBirth) : self::parseYearOnly($overrideBirth);
        $sibYear = self::getEffectiveYear($sib, 'BIRT');
        $sameYear = ($subjYear !== null && $sibYear !== null && $subjYear === $sibYear);

        // Match if one name contains the other (multibyte safe)
        $nameMatch = !empty($subjNorm) && !empty($sibNorm) && (str_contains($sibNorm, $subjNorm) || str_contains($subjNorm, $sibNorm));

        // Diagnostic for duplicates
        if (!isset($debug['sibling_comp'])) $debug['sibling_comp'] = [];
        $debug['sibling_comp'][] = [
            'subj' => $subjGiven,
            'subj_norm' => $subjNorm,
            'sib' => $sibGiven,
            'sib_norm' => $sibNorm,
            'subj_xref' => $person ? $person->xref() : 'NEW',
            'sib_xref' => $sib->xref(),
            'diff_days' => $diffDays,
            'same_year' => $sameYear,
            'name_match' => $nameMatch
        ];

        // If names match and (birth dates very close OR same year OR within 5 years for similar names)
        if ($nameMatch) {
            // A 5-year window (1825 days) is reasonable for "Check if this is a successor or duplicate"
            if ($diffDays < 1825 || $sameYear) {
                $issues[] = [
                    'code' => 'DUPLICATE_SIBLING',
                    'type' => 'duplicate_check',
                    'label' => 'Mögliches Duplikat',
                    'severity' => 'warning',
                    'message' => sprintf(
                        'Geschwister "%s" hat einen identischen/ähnlichen Vornamen (%s).',
                        $sib->fullName(),
                        $sibDisplayDate
                    ),
                ];
            }
        }

        // Spacing rules:
        // 0: Same day (likely twins) - OK
        // 1 to threshold: Suspiciously close
        
        if ($diffDays > 1 && $diffDays < $thresholdDays) {
                $months = round($diffDays / 30.44, 1);
                $issues[] = [
                    'code' => 'SIBLING_TOO_CLOSE',
                    'type' => 'sibling_spacing',
                    'label' => 'Geschwister prüfen',
                    'severity' => 'warning',
                    'message' => sprintf(
                        'Abstand zu Geschwister "%s" (%s: %s) ist mit %s Monaten ungewöhnlich kurz.',
                        $sib->fullName(),
                        $sibLabel,
                        $sibDisplayDate,
                        $months
                    ),
                ];
            }
        }

        return $issues;
    }

    /**
     * Get effective Julian Day for an event
     */
    private static function getEffectiveJD(?Individual $person, string $type, string $override = ''): ?int
    {
        if (!empty(trim($override))) {
            $normalized = DateParser::normalizeToGedcom($override);
            if ($normalized) {
                $date = new \Fisharebest\Webtrees\Date($normalized);
                if ($date->isOK()) return $date->minimumJulianDay();
            }
        }

        if (!$person) return null;

        $date = null;
        switch($type) {
            case 'BIRT': $date = $person->getBirthDate(); break;
            case 'DEAT': $date = $person->getDeathDate(); break;
            case 'BURI':
                $fact = $person->facts(['BURI'])->first();
                $date = $fact ? $fact->date() : null;
                break;
        }

        return ($date && $date->isOK()) ? $date->minimumJulianDay() : null;
    }

    /**
     * Convert Julian Day to Gregorian Year
     */
    private static function getYearFromJD(int $jd): int
    {
        $gregorian_calendar = new GregorianCalendar();
        [$year] = $gregorian_calendar->jdToYmd($jd);
        return $year;
    }

    /**
     * Get surname from individual
     */
    private static function getIndividualSurname(Individual $person): string
    {
        $names = $person->getAllNames();
        foreach ($names as $name) {
            if ($name['type'] === 'NAME' && !empty($name['surn'])) {
                return $name['surn'];
            }
        }
        
        // Fallback: extract from full name if possible
        $fullName = $person->fullName();
        if (preg_match('/\/(.*)\//', $fullName, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Check for missing sources
     *
     * @param Individual|null $person
     * @return array
     */
    private static function checkSourceQuality(?Individual $person): array
    {
        $issues = [];
        if (!$person) return $issues;
        
        $factsToCheck = ['BIRT' => 'Geburt', 'CHR' => 'Taufe', 'DEAT' => 'Tod', 'BURI' => 'Bestattung'];

        foreach ($factsToCheck as $tag => $label) {
            $facts = $person->facts([$tag]);
            if ($facts->count() === 0) continue;
            
            $fact = $facts->first();
            
            // Only complain if the fact has substantive data (Date or Place)
            $hasDate = $fact->date()->isOK();
            $hasPlace = $fact->place() && $fact->place()->gedcomName();

            // Check citations (Robust check)
            $hasSource = false;
            // 1. attributes() collection filter (Webtrees 2.1+)
            if (method_exists($fact, 'attributes')) {
                foreach ($fact->attributes() as $attr) {
                    if ($attr->tag() === 'SOUR') { $hasSource = true; break; }
                }
            }
            // 2. Fallback: string search (for inline sources or if attributes fail)
            if (!$hasSource && method_exists($fact, 'toGedcom')) {
                 if (strpos($fact->toGedcom(), 'SOUR') !== false) $hasSource = true;
            }

            // 4. Try linkedRecords (Standard Webtrees Relationship)
            if (!$hasSource && method_exists($fact, 'linkedRecords')) {
                 if ($fact->linkedRecords('SOUR')->count() > 0) $hasSource = true;
            }

            // Final Source Check (Proven via Debugging)
            if (!$hasSource && method_exists($fact, 'attribute')) {
                if ($fact->attribute('SOUR')) $hasSource = true;
            }
            if (!$hasSource && method_exists($fact, 'gedcom')) {
                 if (strpos($fact->gedcom(), 'SOUR') !== false) $hasSource = true;
            }

            if (($hasDate || $hasPlace) && !$hasSource) {
                // Determine class safely
                $cls = is_object($fact) ? get_class($fact) : gettype($fact);
                
                $issues[] = [
                    'code' => 'MISSING_SOURCE_' . $tag,
                    'type' => 'missing_source',
                    'label' => 'Quelle fehlt',
                    'severity' => 'warning',
                    'message' => sprintf(
                        '%s hat keine Quellenangabe.',
                        $label
                    )
                ];
            }
        }

        // Check Families (Marriage)
        foreach ($person->spouseFamilies() as $family) {
            $facts = $family->facts(['MARR']);
            if ($facts->count() === 0) continue;
            
            $marr = $facts->first();
            $hasDate = $marr->date()->isOK();
            $hasPlace = $marr->place() && $marr->place()->gedcomName();

            // Check citations for marriage (Robust check)
            $hasMarrSource = false;
            if (method_exists($marr, 'attributes')) {
                foreach ($marr->attributes() as $attr) {
                    if ($attr->tag() === 'SOUR') { $hasMarrSource = true; break; }
                }
            }
            if (!$hasMarrSource && method_exists($marr, 'toGedcom')) {
                 if (strpos($marr->toGedcom(), 'SOUR') !== false) $hasMarrSource = true;
            }
            if (!$hasMarrSource && method_exists($marr, 'linkedRecords')) {
                 if ($marr->linkedRecords('SOUR')->count() > 0) $hasMarrSource = true;
            }

            // 5. Final Proven Check (gedcom string and attribute)
            if (!$hasMarrSource && method_exists($marr, 'attribute')) {
                if ($marr->attribute('SOUR')) $hasMarrSource = true;
            }
            if (!$hasMarrSource && method_exists($marr, 'gedcom')) {
                 if (strpos($marr->gedcom(), 'SOUR') !== false) $hasMarrSource = true;
            }

            if (($hasDate || $hasPlace) && !$hasMarrSource) {
                // Determine class safely
                $issues[] = [
                    'code' => 'MISSING_SOURCE_MARR',
                    'type' => 'missing_source',
                    'label' => 'Quelle fehlt',
                    'severity' => 'warning',
                    'message' => sprintf(
                        'Heirat (%s) hat keine Quellenangabe.',
                        $family->xref()
                    )
                    // Removing debug info
                ];
            }
        }

        return $issues;
    }
}
