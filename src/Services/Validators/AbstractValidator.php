<?php

namespace Wolfrum\Datencheck\Services\Validators;

use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\I18N;

abstract class AbstractValidator
{
    /**
     * Translate a string using webtrees I18N
     * 
     * @param string $string
     * @param mixed ...$args
     * @return string
     */
    protected static function translate(string $string, ...$args): string
    {
        return I18N::translate($string, ...$args);
    }

    /**
     * Format date for display
     */
    protected static function formatDate(?Individual $person, string $tag, string $override = ''): string
    {
        if ($override) {
            return $override;
        }

        if (!$person) {
            return '';
        }

        $fact = $person->facts([$tag])->first();
        if ($fact && $fact->date()->isOK()) {
            return $fact->date()->display();
        }

        return '';
    }
}
