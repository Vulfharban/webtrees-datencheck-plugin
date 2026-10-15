<?php

namespace Wolfrum\Datencheck\Helpers;

class StringHelper
{
    /**

     * Normalize a name for international comparison:
     * - Removes GEDCOM slashes (/Surname/) and punctuation.
     * - Strips accents, diacritics, and special characters from Spanish (ñ), Catalan (ç, l·l),
     *   Czech/Slovak (č, ď, ě, ř, š, ť, ž, ů), Polish (ą, ć, ę, ł, ń, ś, ź, ż),
     *   German (ä, ö, ü, ß), Scandinavian (å, æ, ø), French, etc.
     * - Reduces multiple whitespaces to a single space and converts to lowercase ASCII.
     *
     * @param string $name
     * @return string
     */
    public static function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        // 1. Remove GEDCOM slashes
        $name = str_replace('/', '', $name);

        // 2. Special ligatures and language-specific letter mappings
        $specialMap = [
            'ß' => 'ss', 'ẞ' => 'ss',
            'æ' => 'ae', 'Æ' => 'ae',
            'œ' => 'oe', 'Œ' => 'oe',
            'ð' => 'd',  'Ð' => 'd',
            'þ' => 'th', 'Þ' => 'th',
            'ł' => 'l',  'Ł' => 'l',
            'ø' => 'o',  'Ø' => 'o',
            'đ' => 'd',  'Đ' => 'd',
            // Catalan middle dot: l·l -> ll
            '·' => '',
        ];
        $name = strtr($name, $specialMap);

        // 3. Canonical Unicode decomposition (NFD) + strip Combining Diacritical Marks
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($name, \Normalizer::FORM_D);
            if ($decomposed !== false) {
                // Remove all combining diacritical marks (accents, tildes, carons, cedillas, ogoneks, etc.)
                $name = preg_replace('/\p{Mn}+/u', '', $decomposed);
            }
        } elseif (function_exists('iconv')) {
            $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
            if ($translit !== false) {
                $name = $translit;
            }
        }

        // 4. Fallback table for any remaining diacritics
        $search = [
            'á','à','â','ã','ä','å','ą','ā','ă',
            'é','è','ê','ë','ě','ę','ē','ė','ĕ',
            'í','ì','î','ï','ī','ĭ','į',
            'ó','ò','ô','õ','ö','ø','ō','ŏ','ő',
            'ú','ù','û','ü','ů','ū','ŭ','ų','ű',
            'ý','ỳ','ŷ','ÿ',
            'č','ć','ç','ĉ','ċ',
            'ď','đ',
            'ň','ñ','ń','ņ',
            'ř','ŕ','ŗ',
            'š','ś','ş','ŝ','ș',
            'ť','ţ','ț',
            'ž','ź','ż'
        ];
        $replace = [
            'a','a','a','a','a','a','a','a','a',
            'e','e','e','e','e','e','e','e','e',
            'i','i','i','i','i','i','i',
            'o','o','o','o','o','o','o','o','o',
            'u','u','u','u','u','u','u','u','u',
            'y','y','y','y',
            'c','c','c','c','c',
            'd','d',
            'n','n','n','n',
            'r','r','r',
            's','s','s','s','s',
            't','t','t',
            'z','z','z'
        ];
        $name = mb_strtolower($name, 'UTF-8');
        $name = str_replace($search, $replace, $name);

        // 5. Keep only lowercase alphanumeric characters and single spaces
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);

        return trim(preg_replace('/\s+/', ' ', $name));
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
        
        $dist = levenshtein($str1, $str2);
        return $dist >= 0 ? $dist : self::levenshteinLong($str1, $str2);
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
