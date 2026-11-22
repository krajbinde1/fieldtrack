<?php

namespace App\Filament\Concerns;

use App\Models\User;
use App\Services\AdminCenterContext;
use Filament\Navigation\NavigationItem;

trait RequiresSelectedAdminCenter
{
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && app(AdminCenterContext::class)->participatesInCenterSelection($user)
            && parent::shouldRegisterNavigation();
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        return array_map(
            fn (NavigationItem $item): NavigationItem => $item->visible(
                fn (): bool => app(AdminCenterContext::class)->showsCenterModuleNavigation(),
            ),
            parent::getNavigationItems(),
        );
    }
}
