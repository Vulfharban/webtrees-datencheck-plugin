<?php

namespace Wolfrum\Datencheck\Helpers;

/**
 * Date Parser for GEDCOM dates
 * 
 * Supports various date formats:
 * - 13.01.2026
 * - 13 JAN 2026
 * - 13 Januar 2026
 * - ABT 1980
 */
class DateParser
{
    /**
     * Month name to GEDCOM code mapping
     */
    private const MONTH_MAP = [
        'januar' => 'JAN', 'january' => 'JAN', 'jan' => 'JAN', '01' => 'JAN', '1' => 'JAN',
        'februar' => 'FEB', 'february' => 'FEB', 'feb' => 'FEB', '02' => 'FEB', '2' => 'FEB',
        'märz' => 'MAR', 'march' => 'MAR', 'mar' => 'MAR', '03' => 'MAR', '3' => 'MAR',
        'april' => 'APR', 'apr' => 'APR', '04' => 'APR', '4' => 'APR',
        'mai' => 'MAY', 'may' => 'MAY', '05' => 'MAY', '5' => 'MAY',
        'juni' => 'JUN', 'june' => 'JUN', 'jun' => 'JUN', '06' => 'JUN', '6' => 'JUN',
        'juli' => 'JUL', 'july' => 'JUL', 'jul' => 'JUL', '07' => 'JUL', '7' => 'JUL',
        'august' => 'AUG', 'aug' => 'AUG', '08' => 'AUG', '8' => 'AUG',
        'september' => 'SEP', 'sep' => 'SEP', '09' => 'SEP', '9' => 'SEP',
        'oktober' => 'OCT', 'october' => 'OCT', 'oct' => 'OCT', '10' => 'OCT',
        'november' => 'NOV', 'nov' => 'NOV', '11' => 'NOV',
        'dezember' => 'DEC', 'december' => 'DEC', 'dec' => 'DEC', '12' => 'DEC',
    ];
    
    /**
     * Parse a GEDCOM date string
     *
     * @param string $dateStr Date string (e.g., "13 JAN 1980", "13.01.1980", "ABT 1980")
     * @return array{year: int|null, month: string|null, day: int|null}
     */
    public static function parseGedcomDate(string $dateStr): array
    {
        $result = ['year' => null, 'month' => null, 'day' => null];
        
        if (empty(trim($dateStr))) {
            return $result;
        }
        
        // Strip GEDCOM escape sequences like @#DJULIAN@ or @#DFRENCH R@
        $dateStr = preg_replace('/@#D[A-Z ]+@\s*/', '', $dateStr);
        
        // Replace separators with spaces and normalize
        $s = str_replace(['.', '-', '/'], ' ', trim($dateStr));
        $parts = preg_split('/\s+/', $s);
        
        // Try to find a 4-digit year
        foreach ($parts as $part) {
            $cleanPart = preg_replace('/[^0-9]/', '', $part);
            if (preg_match('/^\d{4}$/', $cleanPart)) {
                $result['year'] = (int)$cleanPart;
                break;
            }
        }
        
        // Try to find month and day
        foreach ($parts as $part) {
            // Try to parse as day
            if (preg_match('/^(\d{1,2})$/', $part, $matches)) {
                $num = (int)$matches[1];
                if ($num >= 1 && $num <= 31 && $result['day'] === null) {
                    $result['day'] = $num;
                    continue;
                }
            }
            
            // Try to parse as month
            $lowerPart = mb_strtolower($part);
            if (isset(self::MONTH_MAP[$lowerPart]) && $result['month'] === null) {
                $result['month'] = self::MONTH_MAP[$lowerPart];
            }
        }
        
        return $result;
    }

    /**
     * Check if a date string contains a month that we can parse but is not in the GEDCOM JAN/FEB/... format
     */
    public static function hasNonStandardMonth(string $dateStr): bool
    {
        if (empty(trim($dateStr))) return false;

        // Strip GEDCOM escape sequences like @#DJULIAN@ or @#DGREGORIAN@
        $dateStr = preg_replace('/@#D[A-Z ]+@\s*/', '', $dateStr);

        $s = str_replace(['.', '-', '/'], ' ', trim($dateStr));
        $parts = preg_split('/\s+/', $s);

        $gedcomMonths = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
        $gedcomModifiers = ['ABT', 'CAL', 'EST', 'AFT', 'BEF', 'BET', 'AND', 'FROM', 'TO', 'INT'];

        foreach ($parts as $part) {
            $upper = mb_strtoupper(trim($part));
            if (empty($upper)) continue;

            // 1. If it's a standard month, it's fine
            if (in_array($upper, $gedcomMonths)) continue;

            // 2. If it's a standard GEDCOM modifier (ABT, BET, etc.), it's fine
            if (in_array($upper, $gedcomModifiers)) continue;

            // 3. If it's purely numeric (Day or Year), it's not a month name to check
            if (is_numeric($upper)) continue;

            // 4. If we can map it to a month but it wasn't caught by the 'JAN' check above,
            // it's a non-standard name like "Januar", "January", etc.
            $lower = mb_strtolower($upper);
            if (isset(self::MONTH_MAP[$lower])) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Parse age string like "56y 5m 3w 2d" or "56" to years
     *
     * @param string $ageStr Age string
     * @return float|null Age in years
     */
    public static function parseAgeToYears(string $ageStr): ?float
    {
        if (empty(trim($ageStr))) {
            return null;
        }
        
        $totalYears = 0.0;
        $found = false;
        
        // Match patterns like "56y", "5m", "3w", "2d"
        if (preg_match_all('/(\d+)\s*([ymwd]?)/', $ageStr, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $val = (float)$match[1];
                $unit = isset($match[2]) && !empty($match[2]) ? $match[2] : 'y';
                
                switch ($unit) {
                    case 'm':
                        $totalYears += $val / 12.0;
                        break;
                    case 'w':
                        $totalYears += $val / 52.0;
                        break;
                    case 'd':
                        $totalYears += $val / 365.0;
                        break;
                    default: // 'y' or no unit
                        $totalYears += $val;
                        break;
                }
                $found = true;
            }
        }
        
        return $found ? $totalYears : null;
    }
    
    /**
     * Check if two years are plausibly the same considering uncertainty
     *
     * @param int $targetYear Year being checked
     * @param int $candidateYear Year from database
     * @param float|null $contextAge Age at death (for uncertainty calculation)
     * @param int $fuzzyDiffHighAge Threshold for high age (>80 years)
     * @param int $fuzzyDiffDefault Default threshold
     * @return bool True if dates are plausibly the same
     */
    public static function isDatePlausible(
        int $targetYear,
        int $candidateYear,
        ?float $contextAge,
        int $fuzzyDiffHighAge,
        int $fuzzyDiffDefault
    ): bool {
        $diff = abs($targetYear - $candidateYear);
        
        // Higher age = higher uncertainty in historical records
        $maxDiff = $fuzzyDiffDefault;
        if ($contextAge !== null && $contextAge > 80.0) {
            $maxDiff = $fuzzyDiffHighAge;
        }
        
        return $diff <= $maxDiff;
    }

    /**
     * Normalize a date string to a standard GEDCOM format
     * 
     * @param string $dateStr Irregular date string
     * @return string|null Normalized GEDCOM string (e.g., "13 JAN 1980") or null
     */
    public static function normalizeToGedcom(string $dateStr): ?string
    {
        $dateStr = trim($dateStr);
        if (empty($dateStr)) {
            return null;
        }

        // Detect modifier
        $modifier = '';
        $gedcomModifiers = ['ABT', 'CAL', 'EST', 'AFT', 'BEF', 'BET', 'AND', 'FROM', 'TO', 'INT'];
        
        $upper = mb_strtoupper($dateStr);
        foreach ($gedcomModifiers as $m) {
            if (str_starts_with($upper, $m . ' ')) {
                $modifier = $m . ' ';
                $dateStr = trim(mb_substr($dateStr, mb_strlen($m)));
                break;
            }
        }

        $parsed = self::parseGedcomDate($dateStr);
        
        if ($parsed['year'] === null) {
            // Keep original if it's already a year or something we can't parse but might be a valid GEDCOM fragment
            return null;
        }
        
        $parts = [];
        if ($parsed['day'] !== null) {
            $parts[] = $parsed['day'];
        }
        if ($parsed['month'] !== null) {
            $parts[] = $parsed['month'];
        }
        $parts[] = $parsed['year'];
        
        return $modifier . implode(' ', $parts);
    }
}
