<?php

namespace Wolfrum\Datencheck\Services;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Registry;
use Fisharebest\ExtCalendar\GregorianCalendar;
use Wolfrum\Datencheck\Helpers\DateParser;
use Wolfrum\Datencheck\Services\IgnoredErrorService;
use Wolfrum\Datencheck\Services\Validators;

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
                $issues = array_merge($issues, self::checkNameConsistency($person, $overrideGiven, $overrideSurname, $detectedParents, $module));
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
            $mother = $pair['mother'];
            $motherCalculable = false;
            if ($mother) {
                // Check if we can calculate the mother's age
                $mBirth = self::getEffectiveYear($mother, 'BIRT');
                if ($mBirth !== null) {
                    $issue = Validators\BiologicalValidator::checkMotherAgeAtBirth($person, $mother, $module, $overrideBirth);
                    if ($issue) $issues[] = $issue;
                    $motherCalculable = true;
                }

                $deathIssue = Validators\BiologicalValidator::checkBirthAfterMotherDeath($person, $mother, $overrideBirth);
                if ($deathIssue) $issues[] = $deathIssue;
            }

            $father = $pair['father'];
            if ($father) {
                // Only check father as fallback if mother's age was not calculable
                if (!$mother || !$motherCalculable) {
                    $issue = Validators\BiologicalValidator::checkFatherAgeAtBirth($person, $father, $module, $overrideBirth);
                    if ($issue) $issues[] = $issue;
                }

                // NEW: Check birth after father's death (> 9 months)
                $fDeathIssue = Validators\BiologicalValidator::checkBirthLongAfterFatherDeath($person, $father, $overrideBirth);
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
        $issue = Validators\TemporalValidator::checkBirthAfterDeath($person, $overrideBirth, $overrideDeath, $overrideBurial);
        if ($issue) {
            $issues[] = $issue;
        }

        // NEW: Check baptism before birth
        $issue = Validators\TemporalValidator::checkBaptismBeforeBirth($person, $overrideBirth);
        if ($issue) $issues[] = $issue;

        // NEW: Check burial before death
        $issue = Validators\TemporalValidator::checkBurialBeforeDeath($person, $overrideDeath, $overrideBurial);
        if ($issue) $issues[] = $issue;

        // Check lifespan plausibility
        $issue = Validators\TemporalValidator::checkLifespanPlausibility($person, $module, $overrideBirth, $overrideDeath, $overrideBurial);
        if ($issue) {
            $issues[] = $issue;
        }

        // Check marriage timing (only if person exists)
        if ($person) {
            foreach ($person->spouseFamilies() as $family) {
                $issue = self::checkMarriageBeforeBirth($person, $family, $overrideBirth);
                if ($issue) {
                    $issue['label'] = \Fisharebest\Webtrees\I18N::translate('Check marriage');
                    $issues[] = $issue;
                }

                $issue = self::checkMarriageAfterDeath($person, $family, $overrideDeath);
                if ($issue) {
                    $issue['label'] = \Fisharebest\Webtrees\I18N::translate('Check marriage');
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
                $issue['label'] = \Fisharebest\Webtrees\I18N::translate('Check gender');
                $issues[] = $issue;
            }
        }
        return $issues;
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
            
            if ($marriageYear >= $birthYear) {
                return null;
            }

            return [
                'code' => 'MARRIAGE_BEFORE_BIRTH',
                'type' => 'temporal_impossibility',
                'severity' => 'error',
                'message' => \Fisharebest\Webtrees\I18N::translate(
                    'Marriage (%s) before birth (%s) of "%s"',
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
            
            if ($marriageYear <= $deathYear) {
                return null;
            }

            return [
                'code' => 'MARRIAGE_AFTER_DEATH',
                'type' => 'temporal_impossibility',
                'severity' => 'error',
                'message' => \Fisharebest\Webtrees\I18N::translate(
                    'Marriage (%s) after death (%s) of "%s"',
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
                    'message' => \Fisharebest\Webtrees\I18N::translate(
                        'Person "%s" is registered as husband, but is female',
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
                    'message' => \Fisharebest\Webtrees\I18N::translate(
                        'Person "%s" is registered as wife, but is male',
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
                        'label' => \Fisharebest\Webtrees\I18N::translate('Check marriage'),
                        'severity' => 'error',
                        'message' => \Fisharebest\Webtrees\I18N::translate('Marriage (%d) before partner\'s birth (%s)', $mYear, self::formatDate($indiv, 'BIRT') ?: $pBirth),
                    ];
                } else {
                    $age = $mYear - $pBirth;
                    if ($age < $minMarrAge) {
                        $issues[] = [
                            'code' => 'MARRIAGE_PARTNER_TOO_YOUNG',
                            'type' => 'marr_unusually_early',
                            'label' => \Fisharebest\Webtrees\I18N::translate('Check marriage'),
                            'severity' => 'warning',
                            'message' => \Fisharebest\Webtrees\I18N::translate('Partner "%s" was only %d years old at marriage', $indiv->fullName(), $age),
                        ];
                    } elseif ($age > $maxMarrAge) {
                        $issues[] = [
                            'code' => 'MARRIAGE_PARTNER_TOO_OLD',
                            'type' => 'marr_unusually_late',
                            'label' => \Fisharebest\Webtrees\I18N::translate('Check marriage'),
                            'severity' => 'warning',
                            'message' => \Fisharebest\Webtrees\I18N::translate('Partner "%s" was already %d years old at marriage', $indiv->fullName(), $age),
                        ];
                    }
                }
            }
            if ($pDeath && $mYear > $pDeath) {
                $issues[] = [
                    'code' => 'MARRIAGE_AFTER_PARTNER_DEATH',
                    'type' => 'marr_after_death',
                    'label' => \Fisharebest\Webtrees\I18N::translate('Check marriage'),
                    'severity' => 'error',
                    'message' => \Fisharebest\Webtrees\I18N::translate('Marriage (%d) after partner\'s death (%d)', $mYear, $pDeath),
                ];
            }
        }

        // Check subject birth/death vs marriage
        if ($subjBirth) {
            if ($mYear < $subjBirth) {
                $issues[] = [
                    'code' => 'MARRIAGE_BEFORE_BIRTH',
                    'type' => 'marr_before_birth',
                    'label' => \Fisharebest\Webtrees\I18N::translate('Check marriage'),
                    'severity' => 'error',
                    'message' => \Fisharebest\Webtrees\I18N::translate('Marriage (%d) before own birth (%d)', $mYear, $subjBirth),
                ];
            } else {
                $age = $mYear - $subjBirth;
                if ($age < $minMarrAge) {
                    $issues[] = [
                        'code' => 'MARRIAGE_TOO_YOUNG',
                        'type' => 'marr_unusually_early',
                        'label' => \Fisharebest\Webtrees\I18N::translate('Check marriage'),
                        'severity' => 'warning',
                        'message' => \Fisharebest\Webtrees\I18N::translate('Person was only %d years old at marriage', $age),
                    ];
                } elseif ($age > $maxMarrAge) {
                    $issues[] = [
                        'code' => 'MARRIAGE_TOO_OLD',
                        'type' => 'marr_unusually_late',
                        'label' => \Fisharebest\Webtrees\I18N::translate('Check marriage'),
                        'severity' => 'warning',
                        'message' => \Fisharebest\Webtrees\I18N::translate('Person was already %d years old at marriage', $age),
                    ];
                }
            }
        }
        if ($subjDeath && $mYear > $subjDeath) {
            $issues[] = [
                'code' => 'MARRIAGE_AFTER_DEATH',
                'type' => 'marr_after_death',
                'label' => \Fisharebest\Webtrees\I18N::translate('Check marriage'),
                'severity' => 'error',
                'message' => \Fisharebest\Webtrees\I18N::translate('Marriage (%d) after own death (%d)', $mYear, $subjDeath),
            ];
        }

        // Gender role checks
        if ($husb && $husb->sex() === 'F') {
            $issues[] = [
                'code' => 'GENDER_MISMATCH_HUSBAND',
                'type' => 'gender_inconsistency',
                'label' => \Fisharebest\Webtrees\I18N::translate('Check gender'),
                'severity' => 'error',
                'message' => \Fisharebest\Webtrees\I18N::translate('Husband "%s" is marked as female', $husb->fullName()),
            ];
        }
        if ($wife && $wife->sex() === 'M') {
            $issues[] = [
                'code' => 'GENDER_MISMATCH_WIFE',
                'type' => 'gender_inconsistency',
                'label' => \Fisharebest\Webtrees\I18N::translate('Check gender'),
                'severity' => 'error',
                'message' => \Fisharebest\Webtrees\I18N::translate('Wife "%s" is marked as male', $wife->fullName()),
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
                'label' => \Fisharebest\Webtrees\I18N::translate('Marriage count'),
                'severity' => 'info',
                'message' => \Fisharebest\Webtrees\I18N::translate(
                    'Person "%s" has %d marriages (unusually many)',
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
                        'label' => \Fisharebest\Webtrees\I18N::translate('Date conflict'),
                        'severity' => 'warning',
                        'message' => \Fisharebest\Webtrees\I18N::translate(
                            'Marriage (%d) possibly during existing marriage with "%s" (Death date unknown)',
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
                            'label' => \Fisharebest\Webtrees\I18N::translate('Date conflict'),
                            'severity' => 'error',
                            'message' => \Fisharebest\Webtrees\I18N::translate(
                                'Marriage (%d) before death (%d) of previous spouse "%s"',
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
                'label' => \Fisharebest\Webtrees\I18N::translate('Complete data'),
                'severity' => 'info',
                'message' => \Fisharebest\Webtrees\I18N::translate(
                    'Person "%s" has children but no birth date',
                    $person->fullName()
                ),
            ];
        }

        // Person has death date but no birth date
        if ($death->isOK() && !$birth->isOK()) {
            $issues[] = [
                'code' => 'DEATH_WITHOUT_BIRTH',
                'type' => 'death_without_birth',
                'label' => \Fisharebest\Webtrees\I18N::translate('Complete data'),
                'severity' => 'warning',
                'message' => \Fisharebest\Webtrees\I18N::translate(
                    'Person "%s" has a death date but no birth date',
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
    private static function checkNameConsistency(?Individual $person, string $overrideGiven = '', string $overrideSurname = '', array $parents = [], ?object $module = null): array
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
                'label' => \Fisharebest\Webtrees\I18N::translate('Check name consistency'),
                'severity' => 'warning',
                'message' => \Fisharebest\Webtrees\I18N::translate('Name "%s" contains invalid characters (Encoding issue?)', $fullName),
            ];
        }

        // Check for prefixes in surname (German/Dutch/etc.) which should be separate
        if (!empty($surnamePrimary)) {
            $surnLower = mb_strtolower($surnamePrimary, 'UTF-8');
            $detectedPrefix = null;

            // Check complex prefixes first (longest match wins)
            $complexPrefixes = ['van den ', 'van der ', 'von dem ', 'von der ', 'van de ', 'in den ', 'in der ', 'aus der ', 'auf der ', 'von zu ', 'von und zu '];
            foreach ($complexPrefixes as $cp) {
                if (str_starts_with($surnLower, $cp)) {
                    $detectedPrefix = trim($cp);
                    break;
                }
            }

            if (!$detectedPrefix) {
                // Check single prefixes if no complex one found
                $prefixes = ['von ', 'vom ', 'zu ', 'zur ', 'van ', 'de ', 'den ', 'der ', 'het ', '\'t ', 'ten ', 'ter ', 'da ', 'do ', 'dos ', 'das '];
                foreach ($prefixes as $p) {
                    if (str_starts_with($surnLower, $p)) {
                        $detectedPrefix = trim($p);
                        break;
                    }
                }
            }

            if ($detectedPrefix) {
                $issues[] = [
                    'code' => 'SURNAME_INCLUDES_PREFIX',
                    'type' => 'surname_includes_prefix',
                    'label' => \Fisharebest\Webtrees\I18N::translate('Check name consistency'),
                    'severity' => 'info',
                    'message' => \Fisharebest\Webtrees\I18N::translate(
                        'The surname "%s" appears to contain a prefix "%s". In webtrees, this should be entered in the "Prefix" field.',
                        $surnamePrimary,
                        ucfirst($detectedPrefix)
                    ),
                ];
            }
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
                    } elseif ($module && $module->getPreference('enable_scandinavian_patronymics', '0') === '1') {
                        // Check for Scandinavian patronymics
                        if (self::isScandinavianPatronymicMatch($father, $surnamePrimary)) {
                            $fatherMatch = true;
                        }
                    } elseif ($module && $module->getPreference('enable_slavic_surnames', '0') === '1' && $person) {
                        if (self::isSlavicSurnameMatch($father, $surnamePrimary, $person)) {
                            $fatherMatch = true;
                        }
                    } elseif ($module && $module->getPreference('enable_greek_surnames', '0') === '1' && $person) {
                        if (self::isGreekSurnameMatch($father, $surnamePrimary, $person)) {
                            $fatherMatch = true;
                        }
                    } elseif ($module && $module->getPreference('enable_dutch_tussenvoegsels', '0') === '1') {
                        if (self::isDutchSurnameMatch(self::getIndividualSurname($father), $surnamePrimary)) {
                            $fatherMatch = true;
                        }
                    }
                }

                if ($mother) {
                    $mSurn = self::getIndividualSurname($mother);
                    $mSurnNorm = str_replace('ß', 'ss', mb_strtolower(trim(strip_tags($mSurn)), 'UTF-8'));
                    if ($childSurnNorm === $mSurnNorm || str_contains($mSurnNorm, $childSurnNorm) || str_contains($childSurnNorm, $mSurnNorm)) {
                        $motherMatch = true;
                    } elseif ($module && $module->getPreference('enable_scandinavian_patronymics', '0') === '1') {
                        // Check for Scandinavian patronymics (sometimes mother's name is used)
                        if (self::isScandinavianPatronymicMatch($mother, $surnamePrimary)) {
                            $motherMatch = true;
                        }
                    } elseif ($module && $module->getPreference('enable_slavic_surnames', '0') === '1' && $person) {
                        if (self::isSlavicSurnameMatch($mother, $surnamePrimary, $person)) {
                            $motherMatch = true;
                        }
                    }
                }

                // Check for Spanish Double Surnames
                if ($father && $mother && $module && $module->getPreference('enable_spanish_surnames', '0') === '1') {
                    if (self::isSpanishSurnameMatch($father, $mother, $surnamePrimary)) {
                        $fatherMatch = true; // Count as match if it follows double surname rule
                        $motherMatch = true;
                    }
                }
            }

            if ($fathersFound > 0 && !$fatherMatch) {
                if ($motherMatch) {
                    $issues[] = [
                        'code' => 'SURNAME_MISMATCH_MOTHER',
                        'type' => 'surname_mismatch_mother',
                        'label' => \Fisharebest\Webtrees\I18N::translate('Check name consistency'),
                        'severity' => 'info',
                        'message' => \Fisharebest\Webtrees\I18N::translate('Surname "%s" matches %s but differs from %s.', $surnamePrimary, \Fisharebest\Webtrees\I18N::translate('the mother'), \Fisharebest\Webtrees\I18N::translate('the father')),
                    ];
                } else {
                    $issues[] = [
                        'code' => 'SURNAME_MISMATCH_FATHER',
                        'type' => 'surname_mismatch_father',
                        'label' => \Fisharebest\Webtrees\I18N::translate('Check name consistency'),
                        'severity' => 'warning',
                        'message' => \Fisharebest\Webtrees\I18N::translate('Surname "%s" differs from %s.', $surnamePrimary, \Fisharebest\Webtrees\I18N::translate('the father')),
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
    public static function getEffectiveYear(?Individual $person, string $type, string $override = ''): ?int
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
    public static function parseYearOnly(string $date): ?int
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
                    'label' => \Fisharebest\Webtrees\I18N::translate('Possible duplicate'),
                    'severity' => 'warning',
                    'message' => \Fisharebest\Webtrees\I18N::translate(
                        'Sibling "%s" has an identical/similar given name (%s).',
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
                    'label' => \Fisharebest\Webtrees\I18N::translate('Check sibling'),
                    'severity' => 'warning',
                    'message' => \Fisharebest\Webtrees\I18N::translate(
                        'Distance to sibling "%s" (%s: %s) is unusually short at %s months.',
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
    public static function getEffectiveJD(?Individual $person, string $type, string $override = ''): ?int
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
    public static function getYearFromJD(int $jd): int
    {
        $gregorian_calendar = new GregorianCalendar();
        [$year] = $gregorian_calendar->jdToYmd($jd);
        return $year;
    }

    /**
     * Get surname from individual
     */
    public static function getIndividualSurname(Individual $person): string
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
                    'label' => \Fisharebest\Webtrees\I18N::translate('Missing source'),
                    'severity' => 'warning',
                    'message' => \Fisharebest\Webtrees\I18N::translate(
                        '%s has no source citation.',
                        \Fisharebest\Webtrees\I18N::translate($label)
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

            if (!$hasMarrSource && method_exists($marr, 'gedcom')) {
                 if (strpos($marr->gedcom(), 'SOUR') !== false) $hasMarrSource = true;
            }

            if (($hasDate || $hasPlace) && !$hasMarrSource) {
                $issues[] = [
                    'code' => 'MISSING_SOURCE_MARR',
                    'type' => 'missing_source',
                    'label' => \Fisharebest\Webtrees\I18N::translate('Missing source'),
                    'severity' => 'warning',
                    'message' => \Fisharebest\Webtrees\I18N::translate(
                        'Marriage (%s) has no source citation.',
                        $family->xref()
                    )
                ];
            }
        }

        return $issues;
    }

    /**
     * Check if child surname matches parent's given name as a Scandinavian patronymic
     *
     * @param Individual $parent
     * @param string $childSurname
     * @return bool
     */
    public static function isScandinavianPatronymicMatch(\Fisharebest\Webtrees\Individual $parent, string $childSurname): bool
    {
        $pGiven = mb_strtolower(trim(strip_tags(method_exists($parent, 'givenName') ? $parent->givenName() : $parent->fullName())), 'UTF-8');
        $cSurn = mb_strtolower(trim(strip_tags($childSurname)), 'UTF-8');

        if (empty($pGiven) || empty($cSurn)) return false;

        // Scandinavian patronymic suffixes
        // Danish/Norwegian: -sen, -datter
        // Swedish: -son, -dotter
        // Icelandic: -son, -dóttir
        $suffixes = ['sen', 'søn', 'datter', 'sdatter', 'son', 'dotter', 'dóttir'];

        foreach ($suffixes as $suffix) {
            // Match: ParentGivenName + suffix (e.g. Jørgen + sen = Jørgensen)
            if ($cSurn === $pGiven . $suffix) return true;
            
            // Match with genitive 's' (e.g. Niels + sen = Nielsen, Peter + son = Petersson)
            if (str_ends_with($pGiven, 's')) {
                if ($cSurn === substr($pGiven, 0, -1) . $suffix) return true;
                if ($cSurn === $pGiven . substr($suffix, 1)) return true;
            } else {
                if ($cSurn === $pGiven . 's' . $suffix) return true;
            }
            
            // Handle some variations like -esen vs -sen
            if ($cSurn === $pGiven . 'e' . $suffix) return true;
        }

        return false;
    }

    /**
     * Check if child surname matches parent's surname considering Slavic gendered endings
     */
    public static function isSlavicSurnameMatch(\Fisharebest\Webtrees\Individual $parent, string $childSurname, \Fisharebest\Webtrees\Individual $child): bool
    {
        $pSurn = mb_strtolower(self::getIndividualSurname($parent), 'UTF-8');
        $cSurn = mb_strtolower(trim(strip_tags($childSurname)), 'UTF-8');
        $isFemale = ($child->sex() === 'F');

        if (empty($pSurn) || empty($cSurn)) return false;

        // If they match exactly, it's already a match
        if ($pSurn === $cSurn) return true;

        if ($isFemale) {
            // Polish/Slavic -ski -> -ska
            if (str_ends_with($pSurn, 'ski') && str_ends_with($cSurn, 'ska')) {
                return substr($pSurn, 0, -3) === substr($cSurn, 0, -3);
            }
            // Polish -cki -> -cka, -dzki -> -dzka
            if (str_ends_with($pSurn, 'cki') && str_ends_with($cSurn, 'cka')) {
                return substr($pSurn, 0, -3) === substr($cSurn, 0, -3);
            }
            if (str_ends_with($pSurn, 'dzki') && str_ends_with($cSurn, 'dzka')) {
                return substr($pSurn, 0, -4) === substr($cSurn, 0, -4);
            }

            // Russian/Slavic -ov -> -ova, -ev -> -eva, -in -> -ina
            $suffixes = ['ov' => 'ova', 'ev' => 'eva', 'in' => 'ina', 'yn' => 'yna'];
            foreach ($suffixes as $m => $f) {
                if (str_ends_with($pSurn, $m) && str_ends_with($cSurn, $f)) {
                    return substr($pSurn, 0, -strlen($m)) === substr($cSurn, 0, -strlen($f));
                }
            }

            // Russian -sky -> -skaya
            if (str_ends_with($pSurn, 'sky') && str_ends_with($cSurn, 'skaya')) {
                return substr($pSurn, 0, -3) === substr($cSurn, 0, -5);
            }
        }

        return false;
    }

    /**
     * Check if child surname follows Spanish/Portuguese double surname convention
     */
    public static function isSpanishSurnameMatch(\Fisharebest\Webtrees\Individual $father, \Fisharebest\Webtrees\Individual $mother, string $childSurname): bool
    {
        $fSurn = mb_strtolower(self::getIndividualSurname($father), 'UTF-8');
        $mSurn = mb_strtolower(self::getIndividualSurname($mother), 'UTF-8');
        $cSurn = mb_strtolower(trim(strip_tags($childSurname)), 'UTF-8');

        if (empty($fSurn) || empty($mSurn) || empty($cSurn)) return false;

        // Get first part of each parent's surname
        $fParts = preg_split('/[\s-]+/', $fSurn);
        $mParts = preg_split('/[\s-]+/', $mSurn);
        $f1 = $fParts[0];
        $m1 = $mParts[0];

        // Traditional: [Father1] [Mother1]
        if (str_contains($cSurn, $f1) && str_contains($cSurn, $m1)) return true;

        return false;
    }

    /**
     * Check if surnames match ignoring Dutch tussenvoegsels
     */
    public static function isDutchSurnameMatch(string $parentSurname, string $childSurname): bool
    {
        $prefixes = ['van', 'de', 'der', 'den', 'van den', 'van der', 'vander', 'vanden', 'da', 'do', 'dos', 'das'];
        
        $pSurn = mb_strtolower(trim(strip_tags($parentSurname)), 'UTF-8');
        $cSurn = mb_strtolower(trim(strip_tags($childSurname)), 'UTF-8');

        foreach ($prefixes as $prefix) {
            $pSurn = preg_replace('/^' . preg_quote($prefix, '/') . '\s+/i', '', $pSurn);
            $cSurn = preg_replace('/^' . preg_quote($prefix, '/') . '\s+/i', '', $cSurn);
        }

        return trim($pSurn) === trim($cSurn);
    }

    /**
     * Check if child surname matches parent's surname considering Greek gendered endings
     */
    public static function isGreekSurnameMatch(\Fisharebest\Webtrees\Individual $parent, string $childSurname, \Fisharebest\Webtrees\Individual $child): bool
    {
        $pSurn = mb_strtolower(self::getIndividualSurname($parent), 'UTF-8');
        $cSurn = mb_strtolower(trim(strip_tags($childSurname)), 'UTF-8');
        $isFemale = ($child->sex() === 'F');

        if (empty($pSurn) || empty($cSurn)) return false;

        if ($isFemale) {
            // Greek male -is / -as / -os -> female -ou (possessive)
            // Example: Papaioannis -> Papaioannou, Papas -> Papou? Actually Papas -> Papa?
            // Common: -opoulos -> -opoulou
            if (str_ends_with($pSurn, 'opoulos') && str_ends_with($cSurn, 'opoulou')) {
                return substr($pSurn, 0, -2) === substr($cSurn, 0, -2);
            }
            if (str_ends_with($pSurn, 'is') && str_ends_with($cSurn, 'ou')) {
                return substr($pSurn, 0, -2) === substr($cSurn, 0, -2);
            }
            if (str_ends_with($pSurn, 'as') && str_ends_with($cSurn, 'a')) {
                return substr($pSurn, 0, -2) === substr($cSurn, 0, -1);
            }
            if (str_ends_with($pSurn, 'os') && str_ends_with($cSurn, 'ou')) {
                return substr($pSurn, 0, -2) === substr($cSurn, 0, -2);
            }
        }

        return false;
    }
}
