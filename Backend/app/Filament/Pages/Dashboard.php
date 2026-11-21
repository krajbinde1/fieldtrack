<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdmissionTargetPerformanceWidget;
use App\Filament\Widgets\DirectorAdminStatsWidget;
use App\Filament\Widgets\DirectorConfirmedAdmissionsByCenterWidget;
use App\Filament\Widgets\DirectorTodayTeamActivityWidget;
use App\Filament\Widgets\FieldTrackStatsWidget;
use App\Models\User;
use App\Services\AdminCenterContext;
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

    /**
     * @return int | array<string, ?int>
     */
    public static function getNavigationLabel(): string
    {
        $user = auth()->user();

        if ($user instanceof User && $user->isAdmin() && ! app(AdminCenterContext::class)->hasValidSelection($user)) {
            return 'Main Dashboard';
        }

        return 'Dashboard';
    }

    public function getTitle(): string
    {
        $user = auth()->user();

        if ($user instanceof User && $user->isAdmin()) {
            $center = app(AdminCenterContext::class)->centerFor($user);

            return $center ? $center->name.' Dashboard' : 'Main Admin Dashboard';
        }

        return 'Dashboard';
    }

    public function getColumns(): int | array
    {
        return 1;
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
