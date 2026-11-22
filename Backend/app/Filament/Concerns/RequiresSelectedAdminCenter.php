<?php

namespace App\Filament\Concerns;

use App\Models\User;
use App\Services\AdminCenterContext;

trait RequiresSelectedAdminCenter
{
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if ($user instanceof User && $user->isCenterCoordinator()) {
            return false;
        }

        if ($user instanceof User && $user->isAdmin() && ! app(AdminCenterContext::class)->hasValidSelection($user)) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }
}
