<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdmissionTargetPerformanceWidget;
use App\Filament\Widgets\DirectorAdminStatsWidget;
use App\Filament\Widgets\DirectorConfirmedAdmissionsByCenterWidget;
use App\Filament\Widgets\DirectorTodayTeamActivityWidget;
use App\Filament\Widgets\FieldTrackStatsWidget;
use App\Filament\Widgets\FieldTrackWelcomeWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            FieldTrackWelcomeWidget::class,
            DirectorAdminStatsWidget::class,
            FieldTrackStatsWidget::class,
            AdmissionTargetPerformanceWidget::class,
            DirectorConfirmedAdmissionsByCenterWidget::class,
            DirectorTodayTeamActivityWidget::class,
        ];
    }
}
