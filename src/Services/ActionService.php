<?php

namespace Wolfrum\Datencheck\Services;

use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\I18N;

class ActionService
{
    /**
     * Mark a person as dead by adding a DEAT record.
     *
     * @param Tree   $tree
     * @param string $xref
     * @return array<string,string|bool>
     */
    public static function markAsDead(Tree $tree, string $xref): array
    {
        $xref = strtoupper(trim($xref, " @\t\n\r\0\x0B"));
        $individual = Registry::individualFactory()->make($xref, $tree);

        if (!$individual instanceof Individual) {
            return ['success' => false, 'message' => I18N::translate('Person not found')];
        }

        if (!$individual->canEdit()) {
            return ['success' => false, 'message' => I18N::translate('Permission denied')];
        }

        // Check if death already exists (double safety)
        if ($individual->facts(['DEAT', 'BURI'])->first()) {
             return ['success' => false, 'message' => I18N::translate('Death record already exists')];
        }

        try {
            // Adds '1 DEAT Y' to indicate the person is deceased
            $individual->updateFact('', '1 DEAT Y', true);
            return ['success' => true, 'message' => I18N::translate('Person marked as dead')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * General entry point for quick fixes.
     *
     * @param Tree   $tree
     * @param string $xref
     * @param string $fixType
     * @return array
     */
    public static function applyFix(Tree $tree, string $xref, string $fixType): array
    {
        $xref = strtoupper(trim($xref, " @\t\n\r\0\x0B"));
        $individual = Registry::individualFactory()->make($xref, $tree);
        if (!$individual instanceof Individual) {
            return ['success' => false, 'message' => I18N::translate('Person not found')];
        }

        if (!$individual->canEdit()) {
            return ['success' => false, 'message' => I18N::translate('Permission denied')];
        }

        switch ($fixType) {
            case 'SWAP_BIRT_CHR':
            case 'SWAP_BIRT_BAPM':
                return self::swapDates($individual, 'BIRT', 'CHR');
            case 'SWAP_DEAT_BURI':
                return self::swapDates($individual, 'DEAT', 'BURI');
            case 'SWAP_BIRT_DEAT':
                return self::swapDates($individual, 'BIRT', 'DEAT');
            default:
                return ['success' => false, 'message' => I18N::translate('Unknown fix type: %s', $fixType)];
        }
    }

    /**
     * Swap dates between two tags.
     *
     * @param Individual $individual
     * @param string     $tag1
     * @param string     $tag2
     * @return array
     */
    private static function swapDates(Individual $individual, string $tag1, string $tag2): array
    {
        try {
            $f1 = $individual->facts([$tag1])->first();
            $f2 = $individual->facts([$tag2, 'BAPM' === $tag2 ? 'CHR' : $tag2])->first();

            if (!$f1 || !$f2) {
                return ['success' => false, 'message' => I18N::translate('Tags not found for swapping')];
            }

            // Get full GEDCOM of both facts
            $gedcom1 = $f1->gedcom();
            $gedcom2 = $f2->gedcom();

            // Extract the raw DATE value from GEDCOM to avoid localization (e.g. MAY vs MAI)
            preg_match('/2 DATE (.*)/', $gedcom1, $matches1);
            preg_match('/2 DATE (.*)/', $gedcom2, $matches2);

            $date1 = trim($matches1[1] ?? '');
            $date2 = trim($matches2[1] ?? '');

            if (empty($date1) || empty($date2)) {
                return ['success' => false, 'message' => I18N::translate('Dates are empty')];
            }

            // Replace DATE in each GEDCOM string
            $newGedcom1 = preg_replace('/2 DATE .*/', '2 DATE ' . $date2, $gedcom1);
            $newGedcom2 = preg_replace('/2 DATE .*/', '2 DATE ' . $date1, $gedcom2);

            // Update facts
            $individual->updateFact($f1->id(), $newGedcom1, true);
            
            // Re-fetch individual to refresh state
            $individual = Registry::individualFactory()->make($individual->xref(), $individual->tree());
            
            // Re-fetch f2 because reload invalidates objects
            $f2 = $individual->facts([$tag2, 'BAPM' === $tag2 ? 'CHR' : $tag2])->first();
            if ($f2) {
                $individual->updateFact($f2->id(), $newGedcom2, true);
            }

            return ['success' => true, 'message' => I18N::translate('Dates swapped successfully')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
