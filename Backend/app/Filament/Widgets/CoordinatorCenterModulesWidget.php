<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\SelectCenter;
use App\Filament\Support\CoordinatorCenterModules;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\OrganizationAccessService;
use Filament\Widgets\Widget;

class CoordinatorCenterModulesWidget extends Widget
{
    protected string $view = 'filament.widgets.coordinator-center-modules';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->isCenterCoordinator() === true;
    }

    public function centerName(): string
    {
        $user = auth()->user();

        return $user instanceof User
            ? (app(AdminCenterContext::class)->centerFor($user)?->name ?? 'this center')
            : 'this center';
    }

    public function canSwitchCenters(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->isCenterCoordinator()) {
            return false;
        }

        return count(app(OrganizationAccessService::class)->visibleCenterIds($user) ?? []) > 1;
    }

    public function switchUrl(): string
    {
        return SelectCenter::getUrl();
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    public function modules(): array
    {
        return CoordinatorCenterModules::links();
    }
}
