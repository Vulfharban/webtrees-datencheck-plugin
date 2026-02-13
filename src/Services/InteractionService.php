<?php

namespace Wolfrum\Datencheck\Services;

use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\DB;
use Wolfrum\Datencheck\Services\DatabaseService;

class InteractionService
{
    /**
     * @param Tree   $tree
     * @param string $husb
     * @param string $wife
     * @param string $given
     * @param string $surname
     * @param string $birth
     * @param int    $fuzzyDiffHighAge
     * @param int    $fuzzyDiffDefault
     * @return array
     */
    public static function runSiblingCheck(Tree $tree, string $husb, string $wife, string $given, string $surname, string $birth, int $fuzzyDiffHighAge, int $fuzzyDiffDefault): array
    {
        return DatabaseService::findDuplicateSibling(
            $tree, $husb, $wife, $given, $surname, $birth,
            $fuzzyDiffHighAge, $fuzzyDiffDefault
        );
    }

    /**
     * @param Tree   $tree
     * @param string $husb
     * @param string $wife
     * @return array
     */
    public static function runFamilyCheck(Tree $tree, string $husb, string $wife): array
    {
        return DatabaseService::findExistingFamily($tree, $husb, $wife);
    }

    /**
     * @param Tree   $tree
     * @param string $given
     * @param string $surname
     * @param string $birth
     * @param int    $fuzzyDiffHighAge
     * @param int    $fuzzyDiffDefault
     * @return array
     */
    public static function runInteractiveCheck(Tree $tree, string $given, string $surname, string $birth, int $fuzzyDiffHighAge, int $fuzzyDiffDefault, string $death = '', string $baptism = '', string $sex = '', string $marriedSurname = ''): array
    {
        return DatabaseService::findDuplicatePerson(
            $tree, $given, $surname, $birth,
            $fuzzyDiffHighAge, $fuzzyDiffDefault,
            $death, $baptism, $sex, $marriedSurname
        );
    }

    /**
     * Get detailed person information for comparison modal
     *
     * @param Tree   $tree
     * @param string $xref Person ID
     * @return array
     */
    public static function getPersonDetails(Tree $tree, string $xref): array
    {
        // Remove @ symbols if present
        $xref = trim($xref, '@');

        // Remove name index/suffix (e.g. X123:1 -> X123)
        if (str_contains($xref, ':')) {
            $xref = explode(':', $xref)[0];
        }

        // Fetch GEDCOM for this person
        $gedcomRow = DB::table('individuals')
            ->where('i_file', '=', $tree->id())
            ->where('i_id', '=', $xref)
            ->select(['i_gedcom'])
            ->first();

        if (!$gedcomRow) {
            return ['error' => \Fisharebest\Webtrees\I18N::translate('Person not found')];
        }

        $gedcom = $gedcomRow->i_gedcom;

        // Extract name
        $name = '';
        if (preg_match('/1 NAME ([^\n\r]+)/m', $gedcom, $match)) {
            $name = trim($match[1]);
        }

        $birth = [
            'date' => self::extractGedcomValue('BIRT', 'DATE', $gedcom),
            'place' => self::extractGedcomValue('BIRT', 'PLAC', $gedcom),
            'year' => null
        ];
        if (empty($birth['place'])) {
            $birth['place'] = self::extractGedcomValue('BIRT', 'PLACE', $gedcom);
        }

        if ($birth['date']) {
            $parsed = \Wolfrum\Datencheck\Helpers\DateParser::parseGedcomDate($birth['date']);
            $birth['year'] = $parsed['year'];
        }
        
        $death = [
            'date' => self::extractGedcomValue('DEAT', 'DATE', $gedcom),
            'place' => self::extractGedcomValue('DEAT', 'PLAC', $gedcom),
            'year' => null
        ];
        if (empty($death['place'])) {
            $death['place'] = self::extractGedcomValue('DEAT', 'PLACE', $gedcom);
        }

        if ($death['date']) {
            $parsed = \Wolfrum\Datencheck\Helpers\DateParser::parseGedcomDate($death['date']);
            $death['year'] = $parsed['year'];
        }

        $dates = '';
        if ($birth['year'] || $death['year']) {
            $dates = ($birth['year'] ?? '?') . ' - ' . ($death['year'] ?? '?');
        }

        // Extract parents (FAMC)
        $parents = [];
        if (preg_match_all('/1 FAMC @(.+)@/m', $gedcom, $matches)) {
            foreach ($matches[1] as $famId) {
                $family = self::getFamilyInfo($tree, $famId);
                if (!empty($family['husband'])) {
                    $parents[] = $family['husband'];
                }
                if (!empty($family['wife'])) {
                    $parents[] = $family['wife'];
                }
            }
        }

        // Extract families as spouse (FAMS)
        $families = [];
        if (preg_match_all('/1 FAMS @(.+)@/m', $gedcom, $matches)) {
            foreach ($matches[1] as $famId) {
                $famInfo = self::getFamilyInfo($tree, $famId);
                $families[] = $famInfo;
            }
        }

        return [
            'xref' => $xref,
            'name' => $name,
            'birth' => $birth,
            'death' => $death,
            'dates' => $dates,
            'parents' => $parents,
            'families' => $families,
        ];
    }

    /**
     * Get family information including spouse and children
     *
     * @param Tree   $tree
     * @param string $famId Family ID
     * @return array
     */
    public static function getFamilyInfo(Tree $tree, string $famId): array
    {
        $famId = trim($famId, '@');

        $famRow = DB::table('families')
            ->where('f_file', '=', $tree->id())
            ->where('f_id', '=', $famId)
            ->select(['f_husb', 'f_wife', 'f_gedcom'])
            ->first();

        if (!$famRow) {
            return [];
        }

        $result = [
            'husband' => null,
            'wife' => null,
            'children' => [],
            'marriage' => ['date' => '', 'place' => ''],
        ];

        // Extrahiere Heiratsdaten mit mehreren Tag-Optionen
        $gedcom = $famRow->f_gedcom;
        $marrDate = '';
        $marrPlace = '';
        
        // Versuche verschiedene Tags nacheinander
        $tagsToTry = ['MARR', '_MARR', 'MARB', 'ENGA'];
        foreach ($tagsToTry as $tag) {
            $d = self::extractGedcomValue($tag, 'DATE', $gedcom);
            $p = self::extractGedcomValue($tag, 'PLAC', $gedcom) ?: self::extractGedcomValue($tag, 'PLACE', $gedcom);
            
            if ($d || $p) {
                $marrDate = $d;
                $marrPlace = $p;
                break;
            }
        }
        
        // Spezialfall: 1 EVEN mit 2 TYPE Marriage
        if (empty($marrDate) && empty($marrPlace)) {
            if (preg_match('/1 EVEN(.*?)(?=\n[01]|$)/is', $gedcom, $m)) {
                $evenBlock = $m[1];
                if (preg_match('/2 TYPE (Marriage|Heirat|Hochzeit)/i', $evenBlock)) {
                    $marrDate = self::extractGedcomValue('EVEN', 'DATE', $gedcom);
                    $marrPlace = self::extractGedcomValue('EVEN', 'PLAC', $gedcom);
                }
            }
        }

        $result['marriage']['date'] = $marrDate;
        $result['marriage']['place'] = $marrPlace;

        // Get husband info
        if (!empty($famRow->f_husb)) {
            $result['husband'] = self::getPersonSummary($tree, $famRow->f_husb);
        }

        // Get wife info
        if (!empty($famRow->f_wife)) {
            $result['wife'] = self::getPersonSummary($tree, $famRow->f_wife);
        }

        // Get children
        $children = DB::table('link')
            ->where('l_file', '=', $tree->id())
            ->where('l_from', '=', $famId)
            ->where('l_type', '=', 'CHIL')
            ->select(['l_to'])
            ->get();

        foreach ($children as $child) {
            $summary = self::getPersonSummary($tree, $child->l_to);
            if ($summary) {
                $result['children'][] = $summary;
            }
        }

        return $result;
    }

    /**
     * Get person summary (name and dates)
     *
     * @param Tree   $tree
     * @param string $xref
     * @return array|null
     */
    public static function getPersonSummary(Tree $tree, string $xref): ?array
    {
        $xref = trim($xref, '@');

        $gedcomRow = DB::table('individuals')
            ->where('i_file', '=', $tree->id())
            ->where('i_id', '=', $xref)
            ->select(['i_gedcom'])
            ->first();

        if (!$gedcomRow) {
            return null;
        }

        $gedcom = $gedcomRow->i_gedcom;

        // Extract name
        $name = '';
        if (preg_match('/1 NAME ([^\n\r]+)/m', $gedcom, $match)) {
            $name = trim($match[1]);
        }

        $birthDate = self::extractGedcomValue('BIRT', 'DATE', $gedcom);
        $birthPlace = self::extractGedcomValue('BIRT', 'PLAC', $gedcom);
        if (empty($birthPlace)) {
            $birthPlace = self::extractGedcomValue('BIRT', 'PLACE', $gedcom);
        }

        $deathDate = self::extractGedcomValue('DEAT', 'DATE', $gedcom);
        $deathPlace = self::extractGedcomValue('DEAT', 'PLAC', $gedcom);
        if (empty($deathPlace)) {
            $deathPlace = self::extractGedcomValue('DEAT', 'PLACE', $gedcom);
        }

        $birthYear = null;
        if ($birthDate) {
            $parsed = \Wolfrum\Datencheck\Helpers\DateParser::parseGedcomDate($birthDate);
            $birthYear = $parsed['year'];
        }
        $deathYear = null;
        if ($deathDate) {
            $parsed = \Wolfrum\Datencheck\Helpers\DateParser::parseGedcomDate($deathDate);
            $deathYear = $parsed['year'];
        }

        $dates = '';
        if ($birthYear || $deathYear) {
            $dates = ($birthYear ?? '?') . ' - ' . ($deathYear ?? '?');
        }

        return [
            'xref' => $xref,
            'name' => $name,
            'birth' => ['date' => $birthDate, 'place' => $birthPlace],
            'death' => ['date' => $deathDate, 'place' => $deathPlace],
            'dates' => $dates
        ];
    }

    /**
     * Get person name by xref
     *
     * @param Tree   $tree
     * @param string $xref
     * @return string|null
     */
    public static function getPersonName(Tree $tree, string $xref): ?string
    {
        $xref = trim($xref, '@');

        $row = DB::table('name')
            ->where('n_file', '=', $tree->id())
            ->where('n_id', '=', $xref)
            ->where('n_type', '=', 'NAME')
            ->select(['n_full'])
            ->first();

        return $row ? $row->n_full : null;
    }

    /**
     * Internal helper to extract GEDCOM values robustly
     *
     * @param string $tag
     * @param string $subtag
     * @param string $gedcom
     * @return string
     */
    /**
     * Internal helper to extract GEDCOM values robustly
     *
     * @param string $tag
     * @param string $subtag
     * @param string $gedcom
     * @return string
     */
    private static function extractGedcomValue(string $tag, string $subtag, string $gedcom): string
    {
        $tag = strtoupper($tag);
        $subtag = strtoupper($subtag);
        
        // Normalize line endings to \n
        $gedcom = str_replace(["\r\n", "\r"], "\n", $gedcom);
        
        // Strategy 1: Isolated block search
        // Find "1 TAG", take everything until next "0 " or "1 "
        if (preg_match('/(?:\n|^)1\s+_?' . preg_quote($tag, '/') . '(?:\s+|$|\n)(.*?)(?=\n[01]\s+|\n\z|\z)/is', $gedcom, $m)) {
            $block = $m[1];
            // Search subtag in block
            if (preg_match('/^\s*[23]\s+_?' . preg_quote($subtag, '/') . '\s+(.+)$/im', $block, $sm)) {
                return trim($sm[1]);
            }
            
            // Sub-strategy: Same line fallback if no subtag found
            $lines = explode("\n", $block);
            $firstLine = trim($lines[0] ?? '');
            if (!empty($firstLine) && !preg_match('/^[0-9]\s+[A-Z]/', $firstLine) && !preg_match('/^(Y|YES)$/i', $firstLine)) {
                return $firstLine;
            }
        }

        // Strategy 2: Global proximity search (backup)
        $regex = '/(?:\n|^)1\s+_?' . preg_quote($tag, '/') . '.*?\n\s*[23]\s+_?' . preg_quote($subtag, '/') . '\s+([^\n]+)/is';
        if (preg_match($regex, $gedcom, $m)) {
            $match = $m[0];
            // Ensure no other level 1 tag in between
            $middle = substr($match, strpos($match, "\n") + 1);
            if (!preg_match('/\n1\s+/', $middle)) {
                return trim($m[1]);
            }
        }

        return '';
    }
}
