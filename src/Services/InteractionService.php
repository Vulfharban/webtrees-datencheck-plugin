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
    public static function runInteractiveCheck(Tree $tree, string $given, string $surname, string $birth, int $fuzzyDiffHighAge, int $fuzzyDiffDefault): array
    {
        return DatabaseService::findDuplicatePerson(
            $tree, $given, $surname, $birth,
            $fuzzyDiffHighAge, $fuzzyDiffDefault
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
            return ['error' => 'Person not found'];
        }

        $gedcom = $gedcomRow->i_gedcom;

        // Extract name
        $name = '';
        if (preg_match('/1 NAME (.+)/m', $gedcom, $match)) {
            $name = trim($match[1]);
        }

        // Extract birth info
        $birth = ['date' => '', 'place' => ''];
        if (preg_match('/1 BIRT\s*\n2 DATE (.+)/m', $gedcom, $match)) {
            $birth['date'] = trim($match[1]);
        }
        if (preg_match('/1 BIRT.*\n(?:2 DATE[^\n]*\n)?2 PLAC (.+)/ms', $gedcom, $match)) {
            $birth['place'] = trim($match[1]);
        }

        // Extract death info
        $death = ['date' => '', 'place' => ''];
        if (preg_match('/1 DEAT\s*\n2 DATE (.+)/m', $gedcom, $match)) {
            $death['date'] = trim($match[1]);
        }
        if (preg_match('/1 DEAT.*\n(?:2 DATE[^\n]*\n)?2 PLAC (.+)/ms', $gedcom, $match)) {
            $death['place'] = trim($match[1]);
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
        ];

        // Get husband info
        if (!empty($famRow->f_husb)) {
            $husbName = self::getPersonName($tree, $famRow->f_husb);
            if ($husbName) {
                $result['husband'] = ['xref' => $famRow->f_husb, 'name' => $husbName];
            }
        }

        // Get wife info
        if (!empty($famRow->f_wife)) {
            $wifeName = self::getPersonName($tree, $famRow->f_wife);
            if ($wifeName) {
                $result['wife'] = ['xref' => $famRow->f_wife, 'name' => $wifeName];
            }
        }

        // Get children
        $children = DB::table('link')
            ->where('l_file', '=', $tree->id())
            ->where('l_to', '=', $famId)
            ->where('l_type', '=', 'CHIL')
            ->select(['l_from'])
            ->get();

        foreach ($children as $child) {
            $childName = self::getPersonName($tree, $child->l_from);
            if ($childName) {
                $result['children'][] = ['xref' => $child->l_from, 'name' => $childName];
            }
        }

        return $result;
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
}
