<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CoordinatorCenterModulesWidget;
use App\Models\User;
use App\Services\AdminCenterContext;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class CenterDashboard extends Dashboard
{
    protected static string $routePath = '/center-dashboard';

    protected static ?int $navigationSort = -1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function getNavigationLabel(): string
    {
        return 'Center Dashboard';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->isAdmin() || $user->isCenterCoordinator() || $user->isCenterManager());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $user = auth()->user();

        if ($user instanceof User
            && ($user->isAdmin() || $user->isCenterCoordinator() || $user->isCenterManager())
            && ! app(AdminCenterContext::class)->hasValidSelection($user)) {
            $this->redirect('/admin/centers');
        }
    }

    public function getTitle(): string
    {
        $user = auth()->user();
        $center = $user instanceof User
            ? app(AdminCenterContext::class)->centerFor($user)
            : null;

        return $center ? $center->name.' Dashboard' : 'Center Dashboard';
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        $widgets = parent::getWidgets();
        $user = auth()->user();

        if ($user instanceof User && $user->isCenterCoordinator()) {
            array_unshift($widgets, CoordinatorCenterModulesWidget::class);
        }

        return $widgets;
    }

    protected function showsOrganizationWideDashboard(): bool
    {
        return false;
    }
}
