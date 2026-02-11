<?php

namespace Wolfrum\Datencheck\Helpers;

class StringHelper
{
    /**
     * Normalize a name for comparison by removing slashes, extra spaces, and converting to lowercase.
     *
     * @param string $name
     * @return string
     */
    public static function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', str_replace('/', '', $name)));
    }

    /**
     * Calculate Levenshtein distance between two strings for fuzzy matching.
     * Returns the number of single-character edits needed to transform one string into another.
     *
     * @param string $str1
     * @param string $str2
     * @return int
     */
    public static function levenshteinDistance(string $str1, string $str2): int
    {
        $str1 = mb_strtolower($str1);
        $str2 = mb_strtolower($str2);
        
        // PHP's built-in levenshtein has a limit of 255 characters
        if (strlen($str1) > 255 || strlen($str2) > 255) {
            return self::levenshteinLong($str1, $str2);
        }
        
        return levenshtein($str1, $str2);
    }

    /**
     * Calculate Levenshtein distance for strings longer than 255 characters.
     * Uses Wagner-Fischer algorithm.
     *
     * @param string $str1
     * @param string $str2
     * @return int
     */
    private static function levenshteinLong(string $str1, string $str2): int
    {
        $len1 = mb_strlen($str1);
        $len2 = mb_strlen($str2);
        
        if ($len1 === 0) return $len2;
        if ($len2 === 0) return $len1;
        
        $d = array_fill(0, $len1 + 1, array_fill(0, $len2 + 1, 0));
        
        for ($i = 0; $i <= $len1; $i++) {
            $d[$i][0] = $i;
        }
        
        for ($j = 0; $j <= $len2; $j++) {
            $d[0][$j] = $j;
        }
        
        for ($i = 1; $i <= $len1; $i++) {
            for ($j = 1; $j <= $len2; $j++) {
                $cost = (mb_substr($str1, $i - 1, 1) === mb_substr($str2, $j - 1, 1)) ? 0 : 1;
                $d[$i][$j] = min(
                    $d[$i - 1][$j] + 1,     // deletion
                    $d[$i][$j - 1] + 1,     // insertion
                    $d[$i - 1][$j - 1] + $cost  // substitution
                );
            }
        }
        
        return $d[$len1][$len2];
    }

    public static function isGenanntNameMatch(string $name1, string $name2): bool
    {
        // Keywords for Genannt-Namen (Westphalian aliases, Polish "vel", etc.)
        $keywords = ['genannt', 'gen\.', 'vulgo', 'dictus', 'vel', 'alias', 'inaczej', 'zwany', 'zwana'];
        $pattern = '/\s+(' . implode('|', $keywords) . ')\s+/i';
        
        $n1 = mb_strtolower(trim(strip_tags($name1)), 'UTF-8');
        $n2 = mb_strtolower(trim(strip_tags($name2)), 'UTF-8');
        
        if (empty($n1) || empty($n2)) return false;
        
        if ($n1 === $n2) return true;

        $parts1 = preg_split($pattern, $n1);
        $parts2 = preg_split($pattern, $n2);
        
        $parts1 = array_map('trim', $parts1);
        $parts2 = array_map('trim', $parts2);
        
        foreach ($parts1 as $p1) {
            if (empty($p1)) continue;
            foreach ($parts2 as $p2) {
                if (empty($p2)) continue;
                if ($p1 === $p2) return true;
            }
        }
        
        return false;
    }
}
