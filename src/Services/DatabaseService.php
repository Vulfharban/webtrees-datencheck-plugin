<?php

namespace Wolfrum\Datencheck\Services;

use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\DB;
use Wolfrum\Datencheck\Helpers\StringHelper;
use Wolfrum\Datencheck\Helpers\PhoneticHelper;
use Wolfrum\Datencheck\Helpers\DateParser;

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
        int $fuzzyDiffDefault
    ): array {
        $fullInput = trim($given . ' ' . $surname);
        $normalizedInput = StringHelper::normalizeName($fullInput);
        $inputPhonetic = PhoneticHelper::cologneEncode($normalizedInput);
        
        $parsedDate = DateParser::parseGedcomDate($birthDate);
        $targetYear = $parsedDate['year'];
        
        // Search for candidates with similar surname
        $surnamePattern = '%' . $surname . '%';
        
        $rows = DB::table('name')
            ->where('n_file', '=', $tree->id())
            ->where('n_surname', 'LIKE', $surnamePattern)
            ->select(['n_id', 'n_full'])
            ->get();
        
        $possibleDuplicates = [];
        
        foreach ($rows as $row) {
            $candidateId = $row->n_id;
            $candidateName = $row->n_full;
            
            $normalizedCandidate = StringHelper::normalizeName($candidateName);
            
            // Separate given and surname check for better precision
            $inputGiven = StringHelper::normalizeName($given);
            $inputSurname = StringHelper::normalizeName($surname);
            
            // Extract candidate names (very rough estimation by space)
            $parts = explode(' ', $normalizedCandidate);
            $candSurname = array_pop($parts);
            $candGiven = implode(' ', $parts);
            
            $distGiven = StringHelper::levenshteinDistance($inputGiven, $candGiven);
            $distSurname = StringHelper::levenshteinDistance($inputSurname, $candSurname);
            
            $candidatePhonetic = PhoneticHelper::cologneEncode($normalizedCandidate);
            $phoneticMatch = !empty($inputPhonetic) && $inputPhonetic === $candidatePhonetic;
            
            // Match criteria:
            // 1. Phonetic match (very strong)
            // 2. Both names are very similar (distGiven < 3 AND distSurname < 2)
            // 3. Overall distance is very small (total dist < 4)
            // 4. Genannt-Namen match (Westphalian aliases)
            $genanntMatch = StringHelper::isGenanntNameMatch($inputSurname, $candSurname);
            $stringMatch = ($distGiven < 3 && $distSurname < 2) || (StringHelper::levenshteinDistance($normalizedInput, $normalizedCandidate) < 4) || $genanntMatch;

            if ($stringMatch || $phoneticMatch) {
                // Fetch GEDCOM to check birth date
                $gedcomRow = DB::table('individuals')
                    ->where('i_file', '=', $tree->id())
                    ->where('i_id', '=', $candidateId)
                    ->select(['i_gedcom'])
                    ->first();
                
                $birthMatch = true;
                
                if ($gedcomRow && $targetYear !== null) {
                    $gedcom = $gedcomRow->i_gedcom;
                    
                    // Extract birth year from GEDCOM
                    $candidateBirthYear = self::extractBirthYearFromGedcom($gedcom);
                    
                    // Extract death info for age calculation
                    $deathInfo = self::extractDeathInfoFromGedcom($gedcom);
                    $candidateDeathYear = $deathInfo['year'];
                    $explicitAge = $deathInfo['age'];
                    
                    // If no birth year, try to estimate from death year and age
                    if ($candidateBirthYear === null && $candidateDeathYear !== null && $explicitAge !== null) {
                        $candidateBirthYear = $candidateDeathYear - (int)round($explicitAge);
                    }
                    
                    // Check birth year plausibility
                    if ($candidateBirthYear !== null) {
                        // Calculate age at death for uncertainty logic
                        $deathAge = null;
                        if ($explicitAge !== null) {
                            $deathAge = $explicitAge;
                        } elseif ($candidateDeathYear !== null && $candidateBirthYear !== null) {
                            $deathAge = (float)($candidateDeathYear - $candidateBirthYear);
                        }
                        
                        if (!DateParser::isDatePlausible($targetYear, $candidateBirthYear, $deathAge, $fuzzyDiffHighAge, $fuzzyDiffDefault)) {
                            $birthMatch = false;
                        }
                    }
                }
                
                if ($birthMatch) {
                    $possibleDuplicates[] = [
                        'id2' => $candidateId,
                        'name2' => $candidateName,
                        'distance' => StringHelper::levenshteinDistance($normalizedInput, $normalizedCandidate),
                        'phonetic_match' => $phoneticMatch,
                    ];
                }
            }
        }
        
        return [
            'check_type' => 'interactive_duplicates',
            'description' => 'Found ' . count($possibleDuplicates) . ' potential matches',
            'data' => $possibleDuplicates,
        ];
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
                
                // Extract name
                if (preg_match('/1 NAME (.+)/m', $gedcom, $nameMatch)) {
                    $candidateName = trim($nameMatch[1]);
                    $normalizedCandidate = StringHelper::normalizeName($candidateName);
                    $distance = StringHelper::levenshteinDistance($normalizedInput, $normalizedCandidate);
                    $candidatePhonetic = PhoneticHelper::cologneEncode($normalizedCandidate);
                    $phoneticMatch = !empty($inputPhonetic) && $inputPhonetic === $candidatePhonetic;
                    $genanntMatch = StringHelper::isGenanntNameMatch($normalizedInput, $normalizedCandidate);
                    
                    if ($distance < 5 || $phoneticMatch || $genanntMatch) {
                        $dateMatch = true;
                        
                        if ($targetYear !== null) {
                            $candidateBirthYear = self::extractBirthYearFromGedcom($gedcom);
                            
                            if ($candidateBirthYear !== null) {
                                if (!DateParser::isDatePlausible($targetYear, $candidateBirthYear, null, $fuzzyDiffHighAge, $fuzzyDiffDefault)) {
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
     * Extract birth year from GEDCOM text
     *
     * @param string $gedcom
     * @return int|null
     */
    private static function extractBirthYearFromGedcom(string $gedcom): ?int
    {
        // Look for "1 BIRT\n2 DATE ..."
        if (preg_match('/1 BIRT\s*\n2 DATE (.+)/m', $gedcom, $match)) {
            $dateStr = trim($match[1]);
            $parsed = DateParser::parseGedcomDate($dateStr);
            return $parsed['year'];
        }
        
        return null;
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
}
