<?php

namespace App\Support;

final class AdmissionLookups
{
    public const STATE_MAHARASHTRA = 'Maharashtra';

    /**
     * @return list<string>
     */
    public static function genders(): array
    {
        return ['Male', 'Female', 'Other'];
    }

    /**
     * @return list<string>
     */
    public static function religions(): array
    {
        return ['Hindu', 'Muslim', 'Buddhist', 'Christian', 'Sikh', 'Jain', 'Other'];
    }

    /**
     * @return list<string>
     */
    public static function castes(): array
    {
        return ['Open', 'OBC', 'SC', 'ST', 'VJNT', 'NT', 'SBC', 'Other'];
    }
}
