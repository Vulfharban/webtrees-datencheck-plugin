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
}
