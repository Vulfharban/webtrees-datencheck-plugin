<?php

namespace Wolfrum\Datencheck\Services;

use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\DB;
use Wolfrum\Datencheck\Helpers\StringHelper;
use Wolfrum\Datencheck\Helpers\PhoneticHelper;
use Wolfrum\Datencheck\Helpers\DateParser;
use Wolfrum\Datencheck\Helpers\NameHelper;

/**
 * Database Service for webtrees data checks
 */
class DatabaseService
{
    /**
     * Find potential duplicate persons based on name and birth date
     *
     * @param Tree $tree
     * @param string $given Given name
     * @param string $surname Surname
     * @param string $birthDate Birth date string
     * @param int $fuzzyDiffHighAge Fuzzy threshold for high age
     * @param int $fuzzyDiffDefault Fuzzy threshold default
     * @return array
     */
    public static function findDuplicatePerson(
        Tree $tree,
        string $given,
        string $surname,
        string $birthDate,
        int $fuzzyDiffHighAge,
        int $fuzzyDiffDefault,
        string $deathDate = '',
        string $baptismDate = '',
        string $sex = '',
        string $marriedSurname = ''
    ): array {
        $inputGivenNormalized = StringHelper::normalizeName($given);
        $inputGivenParts = array_filter(explode(' ', $inputGivenNormalized));
        $inputSurnameNormalized = StringHelper::normalizeName($surname);
        
        $parsedBirth = DateParser::parseGedcomDate($birthDate);
        $parsedDeath = DateParser::parseGedcomDate($deathDate);
        $parsedBaptism = DateParser::parseGedcomDate($baptismDate);
        
        // Search for candidates with similar surname or matching full name
        $surnamePattern = '%' . $surname . '%';
        $marriedPattern = $marriedSurname ? '%' . $marriedSurname . '%' : null;
        $treeId = (int)$tree->id();
        
        $rows = DB::table('name')
            ->where('n_file', '=', $treeId)
            ->where(function($query) use ($surnamePattern, $marriedPattern) {
                $query->where('n_surname', 'LIKE', $surnamePattern)
                      ->orWhere('n_full', 'LIKE', $surnamePattern);
                if ($marriedPattern) {
                    $query->orWhere('n_surname', 'LIKE', $marriedPattern)
                          ->orWhere('n_full', 'LIKE', $marriedPattern);
                }
            })
            ->select(['n_id', 'n_full'])
            ->get();
        
        $possibleDuplicates = [];
        
        foreach ($rows as $row) {
            $candidateId = $row->n_id;
            $candidateName = $row->n_full;
            
            // 1. Fetch GEDCOM to check sex and other dates
            $gedcomRow = DB::table('individuals')
                ->where('i_file', '=', $treeId)
                ->where('i_id', '=', $candidateId)
                ->select(['i_gedcom'])
                ->first();
                
            if (!$gedcomRow) {
                continue;
            }
            
            $gedcom = $gedcomRow->i_gedcom;

            // 2. Gender Check
            if (!empty($sex)) {
                $candidateSex = '';
                if (preg_match('/^1 SEX (.+)$/m', $gedcom, $sexMatch)) {
                    $candidateSex = trim($sexMatch[1]);
                }
                if ($candidateSex !== '' && $candidateSex !== 'U' && $sex !== 'U' && $candidateSex !== $sex) {
                    continue;
                }
            }

            // 3. Given Name Check (At least one given name must match or have phonetic overlap)
            $normalizedCandidate = StringHelper::normalizeName($candidateName);
            $candParts = explode(' ', $normalizedCandidate);
            $candSurname = array_pop($candParts);
            $candGivenParts = array_filter($candParts);
            $candGiven = implode(' ', $candGivenParts);
            
            $nameOverlap = false;
            
            // Prepare phonetics for input parts
            $inputPartPhonetics = [];
            foreach ($inputGivenParts as $part) {
                $p = PhoneticHelper::cologneEncode($part);
                if ($p) $inputPartPhonetics[] = $p;
            }

            foreach ($candGivenParts as $cPart) {
                // Exact match
                if (in_array($cPart, $inputGivenParts)) {
                    $nameOverlap = true;
                    break;
                }
                
                // Phonetic match (handles Elisabeth/Elizabeth, Friedrich/Fridrich etc.)
                $cPhonetic = PhoneticHelper::cologneEncode($cPart);
                if ($cPhonetic && in_array($cPhonetic, $inputPartPhonetics)) {
                    $nameOverlap = true;
                    break;
                }
            }
            
            // Also check phonetic match of the full name as final fallback
            $inputFullPhonetic = PhoneticHelper::cologneEncode($inputGivenNormalized . ' ' . $inputSurnameNormalized);
            $candidateFullPhonetic = PhoneticHelper::cologneEncode($normalizedCandidate);
            $phoneticMatch = !empty($inputFullPhonetic) && $inputFullPhonetic === $candidateFullPhonetic;
            
            // Also check NameHelper for defined equivalences (Jan == Johann, Adalbert == Wojciech, etc.)
            $equivalentMatch = NameHelper::areNamesEquivalent($given, $candGiven);
            
            if (!$nameOverlap && !$phoneticMatch && !$equivalentMatch) {
                continue;
            }

            // 4. Date Check (Month and Year must match for AT LEAST ONE date)
            $dateOverlap = false;
            
            // Candidate dates
            $candBirth = self::extractDateFromGedcom($gedcom, 'BIRT');
            $candBaptism = self::extractDateFromGedcom($gedcom, 'CHR') ?: self::extractDateFromGedcom($gedcom, 'BAPM');
            $candDeath = self::extractDateFromGedcom($gedcom, 'DEAT');

            // Compare Birth
            if ($parsedBirth['year'] && $parsedBirth['month'] && $candBirth['year'] && $candBirth['month']) {
                if ($parsedBirth['year'] === $candBirth['year'] && $parsedBirth['month'] === $candBirth['month']) {
                    $dateOverlap = true;
                }
            } elseif ($parsedBirth['year'] && $candBirth['year'] && $parsedBirth['year'] === $candBirth['year']) {
                $dateOverlap = true;
            }

            // Compare Baptism
            if (!$dateOverlap && $parsedBaptism['year'] && $parsedBaptism['month'] && $candBaptism['year'] && $candBaptism['month']) {
                if ($parsedBaptism['year'] === $candBaptism['year'] && $parsedBaptism['month'] === $candBaptism['month']) {
                    $dateOverlap = true;
                }
            }

            // Compare Death
            if (!$dateOverlap && $parsedDeath['year'] && $parsedDeath['month'] && $candDeath['year'] && $candDeath['month']) {
                if ($parsedDeath['year'] === $candDeath['year'] && $parsedDeath['month'] === $candDeath['month']) {
                    $dateOverlap = true;
                }
            }
            
            // If no month/year match, check if at least years are very close (fallback for imprecise entries)
            if (!$dateOverlap) {
                $yearsMatch = false;
                if ($parsedBirth['year'] && $candBirth['year'] && $parsedBirth['year'] === $candBirth['year']) $yearsMatch = true;
                if ($parsedDeath['year'] && $candDeath['year'] && $parsedDeath['year'] === $candDeath['year']) $yearsMatch = true;
                
                if ($yearsMatch) {
                    $dateOverlap = true;
                }
            }

            if ($dateOverlap) {
                $extract = function($tag, $subtag, $gedcom) {
                    if (preg_match('/1 ' . $tag . '(.*?)(?=\n1 |$)/s', $gedcom, $block)) {
                        if (preg_match('/2 ' . $subtag . ' ([^\n\r]+)/', $block[1], $match)) {
                            return trim($match[1]);
                        }
                    }
                    return '';
                };

                $possibleDuplicates[] = [
                    'id2' => $candidateId,
                    'name2' => $candidateName,
                    'birth' => [
                        'date' => $extract('BIRT', 'DATE', $gedcom),
                        'place' => $extract('BIRT', 'PLAC', $gedcom)
                    ],
                    'death' => [
                        'date' => $extract('DEAT', 'DATE', $gedcom),
                        'place' => $extract('DEAT', 'PLAC', $gedcom)
                    ],
                    'distance' => StringHelper::levenshteinDistance($inputGivenNormalized . ' ' . $inputSurnameNormalized, $normalizedCandidate),
                    'phonetic_match' => $phoneticMatch,
                    'families' => self::getPersonFamilies($tree, $candidateId),
                ];
            }
        }
        
        return [
            'check_type' => 'interactive_duplicates',
            'description' => 'Found ' . count($possibleDuplicates) . ' potential matches',
            'data' => $possibleDuplicates,
        ];
    }

    private static function extractDateFromGedcom(string $gedcom, string $tag): array {
        $lines = explode("\n", str_replace("\r", "", $gedcom));
        $inTag = false;
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, "1 " . $tag)) {
                $inTag = true;
                continue;
            }
            if ($inTag) {
                if (str_starts_with($line, "1 ")) {
                    break;
                }
                if (str_starts_with($line, "2 DATE ")) {
                    $dateStr = trim(substr($line, 7));
                    $p = DateParser::parseGedcomDate($dateStr);
                    return ['year' => $p['year'], 'month' => $p['month']];
                }
            }
        }
        return ['year' => null, 'month' => null];
    }
    
    /**
     * Find existing families with given husband and wife
     *
     * @param Tree $tree
     * @param string $husbandId Husband ID (with or without @)
     * @param string $wifeId Wife ID (with or without @)
     * @return array
     */
    public static function findExistingFamily(Tree $tree, string $husbandId, string $wifeId): array
    {
        // Remove @ symbols
        $husbandId = trim($husbandId, '@');
        $wifeId = trim($wifeId, '@');
        
        $query = DB::table('families')
            ->where('f_file', '=', $tree->id())
            ->where('f_husb', '=', $husbandId)
            ->where('f_wife', '=', $wifeId)
            ->select(['f_id'])
            ->get();
        
        $familyIds = [];
        foreach ($query as $row) {
            $familyIds[] = $row->f_id;
        }
        
        return [
            'check_type' => 'family_check',
            'description' => 'Found ' . count($familyIds) . ' existing families',
            'data' => $familyIds,
        ];
    }
    
    /**
     * Find duplicate siblings in the same family
     *
     * @param Tree $tree
     * @param string $husbandId
     * @param string $wifeId
     * @param string $childGiven
     * @param string $childSurname
     * @param string $childBirth
     * @param int $fuzzyDiffHighAge
     * @param int $fuzzyDiffDefault
     * @return array
     */
    public static function findDuplicateSibling(
        Tree $tree,
        string $husbandId,
        string $wifeId,
        string $childGiven,
        string $childSurname,
        string $childBirth,
        int $fuzzyDiffHighAge,
        int $fuzzyDiffDefault
    ): array {
        // Remove @ symbols
        $husbandId = trim($husbandId, '@');
        $wifeId = trim($wifeId, '@');
        
        if ((empty($husbandId) && empty($wifeId)) || (empty($childGiven) && empty($childSurname))) {
            return [
                'check_type' => 'sibling_check',
                'description' => 'Insufficient data',
                'data' => [],
            ];
        }
        
        // Find the family
        $query = DB::table('families')
            ->where('f_file', '=', $tree->id());
        
        if (!empty($husbandId) && !empty($wifeId)) {
            $query->where('f_husb', '=', $husbandId)
                  ->where('f_wife', '=', $wifeId);
        } elseif (!empty($husbandId)) {
            $query->where('f_husb', '=', $husbandId);
        } else {
            $query->where('f_wife', '=', $wifeId);
        }
        
        $families = $query->select(['f_id'])->get();
        
        if ($families->isEmpty()) {
            return [
                'check_type' => 'sibling_check',
                'description' => 'No family found',
                'data' => [],
            ];
        }
        
        $inputName = trim($childGiven . ' ' . $childSurname);
        $normalizedInput = StringHelper::normalizeName($inputName);
        $inputPhonetic = PhoneticHelper::cologneEncode($normalizedInput);
        $parsedDate = DateParser::parseGedcomDate($childBirth);
        $targetYear = $parsedDate['year'];
        
        $matches = [];
        
        foreach ($families as $family) {
            $familyId = $family->f_id;
            
            // Find all children of this family
            $children = DB::table('link')
                ->where('l_file', '=', $tree->id())
                ->where('l_to', '=', $familyId)
                ->where('l_type', '=', 'CHIL')
                ->select(['l_from'])
                ->get();
            
            foreach ($children as $child) {
                $childId = $child->l_from;
                
                // Get child's GEDCOM
                $gedcomRow = DB::table('individuals')
                    ->where('i_file', '=', $tree->id())
                    ->where('i_id', '=', $childId)
                    ->select(['i_gedcom'])
                    ->first();
                
                if (!$gedcomRow) {
                    continue;
                }
                
                $gedcom = $gedcomRow->i_gedcom;
                
                // Extract name more robustly
                $candidateName = self::extractNameFromGedcom($gedcom);
                if ($candidateName) {
                    $normalizedCandidate = StringHelper::normalizeName($candidateName);
                    $distance = StringHelper::levenshteinDistance($normalizedInput, $normalizedCandidate);
                    $candidatePhonetic = PhoneticHelper::cologneEncode($normalizedCandidate);
                    $phoneticMatch = !empty($inputPhonetic) && $inputPhonetic === $candidatePhonetic;
                    $genanntMatch = StringHelper::isGenanntNameMatch($normalizedInput, $normalizedCandidate);
                    $equivalentMatch = NameHelper::areNamesEquivalent($childGiven, $candidateName);
                    
                    if ($distance < 5 || $phoneticMatch || $genanntMatch || $equivalentMatch) {
                        $dateMatch = true;
                        
                        // If we have a target year, we only match if dates are within +/- 2 years 
                        // or if the candidate has no birth date at all (to avoid false negatives).
                        if ($targetYear !== null) {
                            $candBirth = self::extractDateFromGedcom($gedcom, 'BIRT');
                            if ($candBirth['year'] === null) {
                                // Fallback to baptism
                                $candBirth = self::extractDateFromGedcom($gedcom, 'CHR') ?: self::extractDateFromGedcom($gedcom, 'BAPM');
                            }
                            
                            if ($candBirth['year'] !== null) {
                                // Apply specific sibling tolerance (+/- 2 years)
                                if (abs($targetYear - $candBirth['year']) > 2) {
                                    $dateMatch = false;
                                }
                            }
                        }
                        
                        if ($dateMatch) {
                            $matches[] = [
                                'id2' => $childId,
                                'name2' => $candidateName,
                                'distance' => $distance,
                                'phonetic_match' => $phoneticMatch,
                                'family_id' => $familyId,
                            ];
                        }
                    }
                }
            }
        }
        
        return [
            'check_type' => 'sibling_check',
            'description' => 'Found ' . count($matches) . ' potential duplicate siblings',
            'data' => $matches,
        ];
    }
    
    /**
     * Extract name from GEDCOM text
     *
     * @param string $gedcom
     * @return string
     */
    private static function extractNameFromGedcom(string $gedcom): string
    {
        if (preg_match('/^1 NAME ([^\n\r]+)/m', $gedcom, $match)) {
            return trim($match[1]);
        }
        
        return '';
    }

    private static function extractBirthYearFromGedcom(string $gedcom): ?int
    {
        $d = self::extractDateFromGedcom($gedcom, 'BIRT');
        return $d['year'];
    }
    
    /**
     * Extract death info (year and age) from GEDCOM text
     *
     * @param string $gedcom
     * @return array{year: int|null, age: float|null}
     */
    private static function extractDeathInfoFromGedcom(string $gedcom): array
    {
        $year = null;
        $age = null;
        
        // Look for "1 DEAT\n2 DATE ..."
        if (preg_match('/1 DEAT\s*\n2 DATE (.+)/m', $gedcom, $match)) {
            $dateStr = trim($match[1]);
            $parsed = DateParser::parseGedcomDate($dateStr);
            $year = $parsed['year'];
        }
        
        // Look for "2 AGE ..."
        if (preg_match('/2 AGE (.+)/m', $gedcom, $match)) {
            $ageStr = trim($match[1]);
            $age = DateParser::parseAgeToYears($ageStr);
        }
        
        return ['year' => $year, 'age' => $age];
    }

    /**
     * Get all family IDs where this person is a spouse
     *
     * @param Tree   $tree
     * @param string $personId
     * @return array
     */
    private static function getPersonFamilies(Tree $tree, string $personId): array
    {
        $personId = trim($personId, '@');
        
        $results = DB::table('families')
            ->where('f_file', '=', $tree->id())
            ->where(function($query) use ($personId) {
                $query->where('f_husb', '=', $personId)
                      ->orWhere('f_wife', '=', $personId);
            })
            ->select(['f_id'])
            ->get();
            
        $ids = [];
        foreach ($results as $row) {
            $ids[] = $row->f_id;
        }
        return $ids;
    }
}
