<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdmissionTargetPerformanceWidget;
use App\Filament\Widgets\DirectorAdminStatsWidget;
use App\Filament\Widgets\DirectorConfirmedAdmissionsByCenterWidget;
use App\Filament\Widgets\DirectorTodayTeamActivityWidget;
use App\Filament\Widgets\FieldTrackStatsWidget;
use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

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

    public static function getNavigationLabel(): string
    {
        $user = auth()->user();

        if ($user instanceof User && $user->isAdmin()) {
            return 'Main Dashboard';
        }

        return 'Dashboard';
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        // Admins get a single custom link in the sidebar start slot.
        if ($user instanceof User && $user->isAdmin()) {
            return false;
        }

        return true;
    }

    public function getTitle(): string
    {
        $user = auth()->user();

        if ($user instanceof User && $user->isAdmin()) {
            return 'Main Admin Dashboard';
        }

        return 'Dashboard';
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        $organizationWide = $this->showsOrganizationWideDashboard();
        $scope = function (string $widget) use ($organizationWide): string|WidgetConfiguration {
            return $organizationWide
                ? $widget::make(['organizationWide' => true])
                : $widget;
        };

        return [
            $scope(DirectorAdminStatsWidget::class),
            FieldTrackStatsWidget::class,
            AdmissionTargetPerformanceWidget::class,
            $scope(DirectorConfirmedAdmissionsByCenterWidget::class),
            $scope(DirectorTodayTeamActivityWidget::class),
        ];
    }

    protected function showsOrganizationWideDashboard(): bool
    {
        return true;
    }
}
