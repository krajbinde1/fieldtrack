<?php

namespace App\Filament\Concerns;

trait DeniesOrdersOnlyFilamentUsers
{
    public static function canAccess(): bool
    {
        return parent::canAccess();
    }
}
