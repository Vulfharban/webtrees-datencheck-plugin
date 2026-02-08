<?php

namespace Wolfrum\Datencheck\Helpers;

/**
 * Phonetic Helper using Kölner Phonetik (Cologne Phonetics)
 * 
 * This is a phonetic algorithm designed specifically for German language.
 * It encodes words based on their pronunciation.
 * 
 * @see https://de.wikipedia.org/wiki/K%C3%B6lner_Phonetik
 */
class PhoneticHelper
{
    /**
     * Encode a string using Kölner Phonetik algorithm
     *
     * @param string $text
     * @return string Numeric phonetic code
     */
    public static function cologneEncode(string $text): string
    {
        if (empty($text)) {
            return '';
        }
        
        // Convert to uppercase and remove non-alphabetic characters
        $text = mb_strtoupper($text);
        $text = preg_replace('/[^A-ZÄÖÜ]/', '', $text);
        
        if (empty($text)) {
            return '';
        }
        
        // Replace umlauts
        $text = str_replace(['Ä', 'Ö', 'Ü'], ['AE', 'OE', 'UE'], $text);
        
        $len = mb_strlen($text);
        $code = '';
        
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1);
            $prevChar = $i > 0 ? mb_substr($text, $i - 1, 1) : '';
            $nextChar = $i < $len - 1 ? mb_substr($text, $i + 1, 1) : '';
            
            $digit = self::encodeChar($char, $prevChar, $nextChar);
            
            // Append if not empty and different from last digit
            if ($digit !== '' && ($code === '' || substr($code, -1) !== $digit)) {
                $code .= $digit;
            }
        }
        
        // Remove all '0' except at the beginning
        if (strlen($code) > 1) {
            $first = substr($code, 0, 1);
            $rest = str_replace('0', '', substr($code, 1));
            $code = $first . $rest;
        }
        
        return $code;
    }
    
    /**
     * Encode a single character according to Kölner Phonetik rules
     *
     * @param string $char Current character
     * @param string $prevChar Previous character
     * @param string $nextChar Next character
     * @return string Encoded digit
     */
    private static function encodeChar(string $char, string $prevChar, string $nextChar): string
    {
        switch ($char) {
            case 'A':
            case 'E':
            case 'I':
            case 'J':
            case 'O':
            case 'U':
            case 'Y':
                return '0';
                
            case 'B':
                return '1';
                
            case 'P':
                // P is '1' except before 'H'
                return $nextChar === 'H' ? '3' : '1';
                
            case 'D':
            case 'T':
                // D/T before C, S, Z = '8', otherwise '2'
                return in_array($nextChar, ['C', 'S', 'Z']) ? '8' : '2';
                
            case 'F':
            case 'V':
            case 'W':
                return '3';
                
            case 'G':
            case 'K':
            case 'Q':
                return '4';
                
            case 'C':
                // Complex rules for C
                if ($prevChar === '') {
                    // At the beginning
                    if (in_array($nextChar, ['A', 'H', 'K', 'L', 'O', 'Q', 'R', 'U', 'X'])) {
                        return '4';
                    } else {
                        return '8';
                    }
                } else {
                    // Not at beginning
                    if (in_array($prevChar, ['S', 'Z'])) {
                        return '8';
                    } elseif ($nextChar === 'H') {
                        return '4';
                    } elseif (in_array($nextChar, ['A', 'H', 'K', 'O', 'Q', 'U', 'X'])) {
                        return '4';
                    } else {
                        return '8';
                    }
                }
                
            case 'X':
                // X after C, K, Q = '8', otherwise '48'
                if (in_array($prevChar, ['C', 'K', 'Q'])) {
                    return '8';
                } else {
                    return '48';
                }
                
            case 'L':
                return '5';
                
            case 'M':
            case 'N':
                return '6';
                
            case 'R':
                return '7';
                
            case 'S':
            case 'Z':
                return '8';
                
            case 'H':
                // H is only coded if not between vowels
                return '';
                
            default:
                return '';
        }
    }
}
