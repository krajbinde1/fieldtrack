<?php

namespace App\Filament\Widgets;

use Filament\Widgets\FilamentInfoWidget as BaseFilamentInfoWidget;

class FilamentInfoWidget extends BaseFilamentInfoWidget
{
    public static function canView(): bool
    {
        return false;
    }
}
