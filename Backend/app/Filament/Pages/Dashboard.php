<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdmissionTargetPerformanceWidget;
use App\Filament\Widgets\DirectorAdminStatsWidget;
use App\Filament\Widgets\DirectorConfirmedAdmissionsByCenterWidget;
use App\Filament\Widgets\DirectorTodayTeamActivityWidget;
use App\Filament\Widgets\FieldTrackStatsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * @return array<string, mixed>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'ft-dashboard-page',
        ];
    }

    public function getWidgets(): array
    {
        return [
            DirectorAdminStatsWidget::class,
            FieldTrackStatsWidget::class,
            AdmissionTargetPerformanceWidget::class,
            DirectorConfirmedAdmissionsByCenterWidget::class,
            DirectorTodayTeamActivityWidget::class,
        ];
    }
}
