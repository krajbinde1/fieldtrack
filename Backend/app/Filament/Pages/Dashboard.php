<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdmissionTargetPerformanceWidget;
use App\Filament\Widgets\CenterManagerDashboardWidget;
use App\Filament\Widgets\DirectorAdminStatsWidget;
use App\Filament\Widgets\DirectorConfirmedAdmissionsByCenterWidget;
use App\Filament\Widgets\DirectorTodayTeamActivityWidget;
use App\Filament\Widgets\ExpensePendingWidget;
use App\Filament\Widgets\FieldTrackStatsWidget;
use App\Models\User;
use App\Services\AdminCenterContext;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    protected static ?int $navigationSort = -2;

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

        if ($user instanceof User && app(AdminCenterContext::class)->participatesInCenterSelection($user)) {
            return 'Main Dashboard';
        }

        return 'Dashboard';
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        // Admins get a single custom link in the sidebar start slot.
        if (! $user instanceof User || $user->isAdmin()) {
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

        if ($user instanceof User && $user->isCenterCoordinator()) {
            return 'Coordinator Dashboard';
        }

        if ($user instanceof User && $user->isCenterManager()) {
            return 'Center Manager Dashboard';
        }

        return 'Dashboard';
    }

    public function mount(): void
    {
        $user = auth()->user();

        if ($user instanceof User && app(AdminCenterContext::class)->participatesInCenterSelection($user)) {
            app(AdminCenterContext::class)->forget();
        }
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
        $user = auth()->user();
        $organizationWide = $this->showsOrganizationWideDashboard();
        $scope = function (string $widget) use ($organizationWide): string|WidgetConfiguration {
            return $organizationWide
                ? $widget::make(['organizationWide' => true])
                : $widget;
        };

        if ($user instanceof User && $user->isCenterCoordinator()) {
            return [
                $scope(DirectorAdminStatsWidget::class),
                AdmissionTargetPerformanceWidget::class,
                $scope(DirectorConfirmedAdmissionsByCenterWidget::class),
                $scope(DirectorTodayTeamActivityWidget::class),
                ExpensePendingWidget::class,
            ];
        }

        if ($user instanceof User && $user->isCenterManager()) {
            return [
                CenterManagerDashboardWidget::class,
                ExpensePendingWidget::class,
            ];
        }

        return [
            $scope(DirectorAdminStatsWidget::class),
            FieldTrackStatsWidget::class,
            AdmissionTargetPerformanceWidget::class,
            $scope(DirectorConfirmedAdmissionsByCenterWidget::class),
            $scope(DirectorTodayTeamActivityWidget::class),
            ExpensePendingWidget::class,
        ];
    }

    protected function showsOrganizationWideDashboard(): bool
    {
        return true;
    }
}
