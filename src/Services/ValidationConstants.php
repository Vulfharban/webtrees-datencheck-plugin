<?php

namespace Wolfrum\Datencheck\Services;

class ValidationConstants
{
    private static array $labels_de = [
        'MOTHER_TOO_YOUNG'              => 'Mutter zu jung bei Geburt',
        'MOTHER_TOO_OLD'                => 'Mutter zu alt bei Geburt',
        'FATHER_TOO_YOUNG'              => 'Vater zu jung bei Geburt',
        'FATHER_TOO_OLD'                => 'Vater zu alt bei Geburt',
        'BIRTH_AFTER_MOTHER_DEATH'      => 'Geburt nach Tod der Mutter',
        'BIRTH_AFTER_FATHER_DEATH'      => 'Geburt lange nach Tod des Vaters',
        'BAPTISM_BEFORE_BIRTH'          => 'Taufe vor der Geburt',
        'BURIAL_BEFORE_DEATH'           => 'Bestattung vor dem Tod',
        'BIRTH_AFTER_DEATH'             => 'Geburt nach eigenem Tod',
        'LIFESPAN_TOO_HIGH'             => 'Alter ungewöhnlich hoch',
        'MARRIAGE_BEFORE_BIRTH'         => 'Heirat vor der Geburt',
        'MARRIAGE_AFTER_DEATH'          => 'Heirat nach dem Tod',
        'GENDER_MISMATCH_HUSBAND'       => 'Ehemann hat falsches Geschlecht',
        'GENDER_MISMATCH_WIFE'          => 'Ehefrau hat falsches Geschlecht',
        'MARRIAGE_BEFORE_PARTNER_BIRTH' => 'Heirat vor Geburt des Partners',
        'MARRIAGE_PARTNER_TOO_YOUNG'    => 'Partner bei Heirat zu jung',
        'MARRIAGE_PARTNER_TOO_OLD'      => 'Partner bei Heirat zu alt',
        'MARRIAGE_AFTER_PARTNER_DEATH'  => 'Heirat nach Tod des Partners',
        'MARRIAGE_TOO_YOUNG'            => 'Zu jung bei Heirat',
        'MARRIAGE_TOO_OLD'              => 'Zu alt bei Heirat',
        'TOO_MANY_MARRIAGES'            => 'Zu viele Ehen',
        'MARRIAGE_POSSIBLY_OVERLAPPING' => 'Mögliche Überschneidung von Ehen',
        'MARRIAGE_OVERLAPPING'          => 'Überschneidung von Ehen',
        'MISSING_BIRTH_DATE'            => 'Geburtsdatum fehlt (trotz Kinder/Tod)',
        'DEATH_WITHOUT_BIRTH'           => 'Sterbedatum ohne Geburtsdatum',
        'INVALID_BIRTH_PLACE'           => 'Ungültiger Geburtsort',
        'INVALID_DEATH_PLACE'           => 'Ungültiger Sterbeort',
        'MISSING_GIVEN_NAME'            => 'Vorname fehlt',
        'NAME_MISMATCH'                 => 'Name weicht ab',
        'NAME_ENCODING_ISSUE'           => 'Namens-Kodierungsproblem',
        'SURNAME_MISMATCH_MOTHER'       => 'Nachname weicht von Mutter ab',
        'SURNAME_MISMATCH_FATHER'       => 'Nachname weicht von Vater ab',
        'DUPLICATE_SIBLING'             => 'Doppeltes Kind (Geschwister)',
        'SIBLING_TOO_CLOSE'             => 'Abstand zu Geschwister zu klein',
        'BAPTISM_DELAYED'               => 'Taufe ungewöhnlich spät',
    ];

    private static array $labels_en = [
        'MOTHER_TOO_YOUNG'              => 'Mother too young at birth',
        'MOTHER_TOO_OLD'                => 'Mother too old at birth',
        'FATHER_TOO_YOUNG'              => 'Father too young at birth',
        'FATHER_TOO_OLD'                => 'Father too old at birth',
        'BIRTH_AFTER_MOTHER_DEATH'      => 'Birth after mother\'s death',
        'BIRTH_AFTER_FATHER_DEATH'      => 'Birth long after father\'s death',
        'BAPTISM_BEFORE_BIRTH'          => 'Baptism before birth',
        'BURIAL_BEFORE_DEATH'           => 'Burial before death',
        'BIRTH_AFTER_DEATH'             => 'Birth after own death',
        'LIFESPAN_TOO_HIGH'             => 'Lifespan unusually high',
        'MARRIAGE_BEFORE_BIRTH'         => 'Marriage before birth',
        'MARRIAGE_AFTER_DEATH'          => 'Marriage after death',
        'GENDER_MISMATCH_HUSBAND'       => 'Husband has wrong gender',
        'GENDER_MISMATCH_WIFE'          => 'Wife has wrong gender',
        'MARRIAGE_BEFORE_PARTNER_BIRTH' => 'Marriage before partner\'s birth',
        'MARRIAGE_PARTNER_TOO_YOUNG'    => 'Partner too young at marriage',
        'MARRIAGE_PARTNER_TOO_OLD'      => 'Partner too old at marriage',
        'MARRIAGE_AFTER_PARTNER_DEATH'  => 'Marriage after partner\'s death',
        'MARRIAGE_TOO_YOUNG'            => 'Too young at marriage',
        'MARRIAGE_TOO_OLD'              => 'Too old at marriage',
        'TOO_MANY_MARRIAGES'            => 'Too many marriages',
        'MARRIAGE_POSSIBLY_OVERLAPPING' => 'Possibly overlapping marriages',
        'MARRIAGE_OVERLAPPING'          => 'Overlapping marriages',
        'MISSING_BIRTH_DATE'            => 'Missing birth date (has children/death)',
        'DEATH_WITHOUT_BIRTH'           => 'Death date without birth date',
        'INVALID_BIRTH_PLACE'           => 'Invalid birth place',
        'INVALID_DEATH_PLACE'           => 'Invalid death place',
        'MISSING_GIVEN_NAME'            => 'Missing given name',
        'NAME_MISMATCH'                 => 'Name mismatch',
        'NAME_ENCODING_ISSUE'           => 'Name encoding issue',
        'SURNAME_MISMATCH_MOTHER'       => 'Surname mismatch with mother',
        'SURNAME_MISMATCH_FATHER'       => 'Surname mismatch with father',
        'DUPLICATE_SIBLING'             => 'Duplicate child (sibling)',
        'SIBLING_TOO_CLOSE'             => 'Sibling spacing too small',
        'BAPTISM_DELAYED'               => 'Baptism unusually late',
    ];

    public static function getLabel(string $code, string $lang = 'en'): string
    {
        $label = self::$labels_en[$code] ?? $code;
        return \Fisharebest\Webtrees\I18N::translate($label);
    }
}
