<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\AdminCenterContext;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

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
        return auth()->user()?->isAdmin() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $user = auth()->user();

        if ($user instanceof User && $user->isAdmin() && ! app(AdminCenterContext::class)->hasValidSelection($user)) {
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

    protected function showsOrganizationWideDashboard(): bool
    {
        return false;
    }
}
