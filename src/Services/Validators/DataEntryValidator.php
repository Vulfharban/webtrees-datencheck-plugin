<?php

namespace Wolfrum\Datencheck\Services\Validators;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Wolfrum\Datencheck\Helpers\DateParser;

class DataEntryValidator extends AbstractValidator
{
    private const GEDCOM_MONTHS = [
        'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC',
        'VEND', 'BRUM', 'FRIM', 'NIVO', 'PLUV', 'VENT', 'GERM', 'FLOR', 'PRAI', 'MESS', 'THER', 'FRUC', 'COMP',
        'TSH', 'CSH', 'KSL', 'TVT', 'SHV', 'ADR', 'ADS', 'NSN', 'IYR', 'SVN', 'TMZ', 'AAV', 'ELL',
    ];

    private const GEDCOM_KEYWORDS = ['ABT', 'CAL', 'EST', 'AFT', 'BEF', 'BET', 'AND', 'FROM', 'TO', 'INT', 'B.C.', 'BC'];

    /** Lowercase name particles/prefixes */
    public const NAME_PARTICLES = [
        'von', 'vom', 'zu', 'zur', 'van', 'de', 'den', 'der', 'het', 'ten', 'ter',
        'da', 'do', 'dos', 'das', 'del', 'della', 'di', 'du', 'le', 'la', 'y', 'e',
        'und', 'auf', 'aus', 'in', "'t",
    ];

    /** Placeholders for unknown names */
    private const PLACEHOLDERS = [
        '?', '??', '???', 'nn', 'n.n.', 'n. n.', 'unbekannt', 'unknown', '-', '...', '…',
    ];

    // ---------------------------------------------------------------------
    // Level 1: Pure string analysis (unit testable without DB)
    // ---------------------------------------------------------------------

    /**
     * Check if a name part is an informal unknown placeholder (e.g. "?", "N.N.", "unbekannt").
     *
     * @param string $namePart
     * @return bool
     */
    public static function isPlaceholderName(string $namePart): bool
    {
        return in_array(mb_strtolower(trim($namePart), 'UTF-8'), self::PLACEHOLDERS, true);
    }

    /**
     * Analyze special characters in a name part.
     * Note: Asterisk (*) in given names denotes the "Rufname" (preferred name) in Webtrees and is allowed.
     *
     * @param string $namePart
     * @param bool $isGivenName
     * @return array<string, string> Keyed by category: 'question', 'brackets', 'slash', 'quotes', 'digits', 'symbols'
     */
    public static function analyzeSpecialChars(string $namePart, bool $isGivenName = false): array
    {
        $namePart = trim($namePart);
        if ($namePart === '' || str_starts_with($namePart, '@')) {
            return []; // Skip GEDCOM standard placeholders like @N.N. or @P.N.
        }

        $groups = [
            'question' => '/\?/u',
            'brackets' => '/[()\[\]{}]/u',
            'slash'    => '/[\/\\\\|]/u',
            'quotes'   => '/["“”„«»]/u',
            'digits'   => '/\d/u',
            'symbols'  => $isGivenName ? '/[#+!=<>@%&;:]/u' : '/[*#+!=<>@%&;:]/u',
        ];

        $found = [];
        foreach ($groups as $key => $regex) {
            if (preg_match($regex, $namePart)) {
                $found[$key] = $key;
            }
        }

        return $found;
    }

    /**
     * Analyze capitalization of a name part (given or surname).
     *
     * @param string $namePart
     * @return 'caps'|'lower'|null
     */
    public static function analyzeCase(string $namePart): ?string
    {
        $namePart = trim($namePart);
        if ($namePart === '' || str_starts_with($namePart, '@')) {
            return null;
        }

        // Script without uppercase/lowercase (CJK, Arabic, Hebrew, etc.)
        if (mb_strtoupper($namePart, 'UTF-8') === mb_strtolower($namePart, 'UTF-8')) {
            return null;
        }

        // Split by spaces, hyphens and slashes
        $rawWords = preg_split('/[\s\-\/]+/u', $namePart, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($rawWords) || empty($rawWords)) {
            return null;
        }

        $words = array_values(array_filter($rawWords, static function (string $w): bool {
            $l = mb_strtolower($w, 'UTF-8');
            $pureLetters = preg_replace('/[^\p{L}]/u', '', $w);
            return mb_strlen($pureLetters, 'UTF-8') >= 3
                && !in_array($l, self::NAME_PARTICLES, true)
                && !preg_match('/^[IVXLCDM]+$/i', $w);
        }));

        if (empty($words)) {
            return null;
        }

        // 1. Check ALL CAPS: all letters in all non-particle words are uppercase
        $allLetters = preg_replace('/[^\p{L}]/u', '', implode('', $words));
        if (mb_strlen($allLetters, 'UTF-8') >= 3 && $allLetters === mb_strtoupper($allLetters, 'UTF-8')) {
            return 'caps';
        }

        // 2. Check ALL LOWERCASE: every word starts with a lowercase letter
        // If at least one word has a valid uppercase initial, it's not entirely lowercase.
        foreach ($words as $w) {
            $first = mb_substr(preg_replace('/^[^\p{L}]+/u', '', $w), 0, 1, 'UTF-8');
            if ($first !== '' && $first !== mb_strtolower($first, 'UTF-8')) {
                return null;
            }
        }

        return 'lower';
    }

    /**
     * Analyze a place string for swapped date/year contents.
     * 
     * @param string $place
     * @return string|null 'year' | 'date' | 'modifier' | null
     */
    public static function analyzePlace(string $place): ?string
    {
        $parts = array_map('trim', explode(',', $place));
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            // Standalone year (3-4 digits, 500-2099)
            if (preg_match('/^\d{3,4}$/', $part) && (int) $part >= 500 && (int) $part <= 2099) {
                return 'year';
            }

            // Numeric date formats: dd.mm.yyyy, dd/mm/yyyy, dd-mm-yyyy, yyyy-mm-dd
            if (preg_match('/^\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}$|^\d{4}-\d{1,2}-\d{1,2}$/', $part)) {
                return 'date';
            }

            $upper = mb_strtoupper($part, 'UTF-8');

            // GEDCOM modifier + number at start (e.g. ABT 1880, BEF 1900)
            if (preg_match('/^(ABT|CAL|EST|AFT|BEF|BET|FROM|TO)\s+\d/u', $upper)) {
                return 'modifier';
            }

            // German modifier abbreviations (e.g. ca. 1880, um 1880)
            if (preg_match('/^(CA\.?|UM|ETWA)\s+\d/iu', $part)) {
                return 'modifier';
            }

            // Month name + digit in this part (e.g. "15 AUG 1890", "Mai 1880")
            if (preg_match('/\d/u', $part)) {
                $tokens = preg_split('/[\s.]+/u', $part, -1, PREG_SPLIT_NO_EMPTY);
                if (is_array($tokens)) {
                    foreach ($tokens as $token) {
                        if (self::isMonthToken($token)) {
                            return 'date';
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Analyze a date string for suspicious words / text.
     * 
     * @param string $rawDate
     * @return array{leftover: string[], nonStandardMonth: bool}
     */
    public static function analyzeDate(string $rawDate): array
    {
        // 1. Remove calendar escapes like @#DGREGORIAN@, @#DJULIAN@
        $s = preg_replace('/@#D[A-Z ]+@/u', ' ', $rawDate);

        // 2. Remove parenthesized date phrases (allowed in GEDCOM, e.g. "(laut Kirchenbuch)", "(unbekannt)")
        $s = preg_replace('/\([^)]*\)/u', ' ', (string) $s);

        // 3. Tokenize by space, slashes, dots, commas, dashes
        $tokens = preg_split('/[\s\/.,-]+/u', trim((string) $s), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens)) {
            return ['leftover' => [], 'nonStandardMonth' => false];
        }

        $leftover = [];
        $nonStandardMonth = false;

        foreach ($tokens as $t) {
            $u = mb_strtoupper($t, 'UTF-8');

            // Allowed: pure numbers, GEDCOM months, GEDCOM keywords
            if (ctype_digit($t) || in_array($u, self::GEDCOM_MONTHS, true) || in_array($u, self::GEDCOM_KEYWORDS, true)) {
                continue;
            }

            // Non-standard months like Januar, März, January
            if (self::isMonthToken($t)) {
                $nonStandardMonth = true;
                continue;
            }

            // If token has at least 3 letters and contains alphabetic characters
            if (mb_strlen($t, 'UTF-8') >= 3 && preg_match('/\p{L}/u', $t)) {
                $leftover[] = $t;
            }
        }

        return [
            'leftover' => $leftover,
            'nonStandardMonth' => $nonStandardMonth,
        ];
    }

    private static function isMonthToken(string $token): bool
    {
        return DateParser::isMonthName($token);
    }

    // ---------------------------------------------------------------------
    // Level 2: Person & Live field inspection
    // ---------------------------------------------------------------------

    /**
     * Check for swapped field contents (dates in place field, place/text in date field).
     *
     * @param Individual|null $person
     * @param array<int, array{tag:string, field:string, value:string, label?:string}> $liveFields
     * @param Tree|null $tree
     * @return array<int, array<string, mixed>>
     */
    public static function checkFieldSwaps(?Individual $person, array $liveFields = [], ?Tree $tree = null): array
    {
        $entries = $liveFields;

        if ($person) {
            $facts = $person->facts();
            foreach ($person->spouseFamilies() as $family) {
                $facts = $facts->merge($family->facts(['MARR', 'DIV', 'ENGA', 'MARB', 'MARC']));
            }
            foreach ($facts as $fact) {
                $date = (string) $fact->attribute('DATE');
                $plac = (string) $fact->attribute('PLAC');
                if ($date !== '') {
                    $entries[] = [
                        'tag'   => $fact->tag(),
                        'field' => 'DATE',
                        'value' => $date,
                        'label' => $fact->label(),
                    ];
                }
                if ($plac !== '') {
                    $entries[] = [
                        'tag'   => $fact->tag(),
                        'field' => 'PLAC',
                        'value' => $plac,
                        'label' => $fact->label(),
                    ];
                }
            }
        }

        $issues = [];
        $seen = [];

        foreach ($entries as $e) {
            $key = $e['field'] . '|' . mb_strtolower($e['value'], 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $label = htmlspecialchars($e['label'] ?? $e['tag'], ENT_QUOTES, 'UTF-8');
            $value = htmlspecialchars($e['value'], ENT_QUOTES, 'UTF-8');

            if ($e['field'] === 'PLAC') {
                $match = self::analyzePlace($e['value']);
                if ($match !== null) {
                    $issues[] = self::issue(
                        'PLACE_CONTAINS_DATE',
                        'warning',
                        self::translate('Place of %s contains a date or year: "%s". Were date and place swapped?', $label, $value)
                    );
                }
            }

            if ($e['field'] === 'DATE') {
                $res = self::analyzeDate($e['value']);
                if (!empty($res['leftover'])) {
                    $looksLikePlace = $tree && self::existsAsPlace($tree, $res['leftover']);
                    $issues[] = self::issue(
                        'DATE_CONTAINS_TEXT',
                        'warning',
                        $looksLikePlace
                            ? self::translate('Date of %s contains a place name: "%s". Were date and place swapped?', $label, $value)
                            : self::translate('Date of %s contains text that cannot be interpreted: "%s".', $label, $value)
                    );
                } elseif ($res['nonStandardMonth']) {
                    $issues[] = self::issue(
                        'NON_STANDARD_MONTH_NAME',
                        'info',
                        self::translate('Non-standard month name found in %s: "%s". Expected GEDCOM standard (e.g. JAN, FEB).', $label, $value)
                    );
                }
            }
        }

        return $issues;
    }

    /**
     * Check name capitalization (Feature B: NAME_ALL_CAPS, NAME_ALL_LOWERCASE).
     *
     * @param Individual|null $person
     * @param string $overrideGiven
     * @param string $overrideSurname
     * @param object|null $module
     * @return array<int, array<string, mixed>>
     */
    public static function checkNameFormatting(?Individual $person, string $overrideGiven = '', string $overrideSurname = '', ?object $module = null): array
    {
        $checkGivenCaps   = !$module || (method_exists($module, 'getSetting') ? $module->getSetting('check_given_caps', '1') : $module->getPreference('check_given_caps', '1')) === '1';
        $checkSurnameCaps = $module && (method_exists($module, 'getSetting') ? $module->getSetting('check_surname_caps', '0') : $module->getPreference('check_surname_caps', '0')) === '1';

        $pairs = [];
        if ($person) {
            foreach ($person->getAllNames() as $n) {
                $pairs[] = [$n['givn'] ?? '', $n['surn'] ?? ''];
            }
        }

        if (!empty($overrideGiven) || !empty($overrideSurname)) {
            $gList = explode('|', $overrideGiven);
            $sList = explode('|', $overrideSurname);
            $count = max(count($gList), count($sList));
            for ($i = 0; $i < $count; $i++) {
                $pairs[] = [trim($gList[$i] ?? ''), trim($sList[$i] ?? '')];
            }
        }

        $issues = [];
        $seen = [];

        foreach ($pairs as [$given, $surname]) {
            foreach (['given' => $given, 'surname' => $surname] as $part => $value) {
                $value = trim($value);
                if ($value === '' || isset($seen[$part . '|' . mb_strtolower($value, 'UTF-8')])) {
                    continue;
                }
                $seen[$part . '|' . mb_strtolower($value, 'UTF-8')] = true;

                $v = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

                // a) Informal placeholder for unknown name (takes precedence over suspicious characters)
                if (self::isPlaceholderName($value)) {
                    $issues[] = self::issue(
                        'NAME_PLACEHOLDER_HINT',
                        'info',
                        $part === 'given'
                            ? self::translate('Unknown given name "%s": please use the placeholder @P.N.', $v)
                            : self::translate('Unknown surname "%s": please use the placeholder @N.N.', $v)
                    );
                    continue;
                }

                // b) Suspicious special characters (asterisk * allowed in given names as Rufname marker)
                $chars = self::analyzeSpecialChars($value, $part === 'given');
                if (!empty($chars)) {
                    $hint = isset($chars['quotes'])
                        ? self::translate('Nicknames belong in the nickname field (NICK).')
                        : self::translate('Notes or uncertainties should be recorded as a note or source.');
                    $issues[] = self::issue(
                        'NAME_SUSPICIOUS_CHARS',
                        'warning',
                        self::translate('Name "%s" contains suspicious characters.', $v) . ' ' . $hint
                    );
                }

                // c) Capitalization
                $case = self::analyzeCase($value);
                if ($case === 'caps' && (($part === 'given' && $checkGivenCaps) || ($part === 'surname' && $checkSurnameCaps))) {
                    $issues[] = self::issue(
                        'NAME_ALL_CAPS',
                        'info',
                        self::translate('Name "%s" is written entirely in capital letters.', $v)
                    );
                } elseif ($case === 'lower') {
                    $issues[] = self::issue(
                        'NAME_ALL_LOWERCASE',
                        'info',
                        self::translate('Name "%s" begins with a lowercase letter.', $v)
                    );
                }
            }
        }

        return $issues;
    }

    private static function existsAsPlace(Tree $tree, array $words): bool
    {
        try {
            return DB::table('places')
                ->where('p_file', '=', $tree->id())
                ->whereIn('p_place', $words)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function issue(string $code, string $severity, string $message): array
    {
        return [
            'code'     => $code,
            'type'     => 'data_entry',
            'severity' => $severity,
            'message'  => $message,
        ];
    }
}
