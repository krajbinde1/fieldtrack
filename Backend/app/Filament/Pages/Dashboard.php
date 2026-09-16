<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FieldTrackStatsWidget;
use App\Filament\Widgets\FieldTrackWelcomeWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            FieldTrackWelcomeWidget::class,
            FieldTrackStatsWidget::class,
        ];
    }
}
