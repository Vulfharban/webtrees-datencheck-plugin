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
}
