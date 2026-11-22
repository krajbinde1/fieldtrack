<?php

namespace App\Http\Middleware;

use App\Filament\Support\CoordinatorCenterModules;
use App\Models\User;
use App\Services\AdminCenterContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminCenterSelected
{
    /**
     * Admins without a selected center stay on the main dashboard, Centers,
     * Team Management, and global settings. The center dashboard and
     * center-specific modules redirect to the Centers list.
     *
     * Coordinators use the same center modules only after they pick one
     * assigned center. Other roles are unchanged.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isCenterCoordinator()) {
            if ($this->isCenterModule($request) && ! app(AdminCenterContext::class)->hasValidSelection($user)) {
                return redirect('/admin/centers');
            }

            return $next($request);
        }

        if (! $user instanceof User || ! $user->isAdmin()) {
            return $next($request);
        }

        if (app(AdminCenterContext::class)->hasValidSelection($user)) {
            return $next($request);
        }

        if ($this->isCenterModule($request)) {
            return redirect('/admin/centers');
        }

        return $next($request);
    }

    private function isCenterModule(Request $request): bool
    {
        return CoordinatorCenterModules::requestMatches($request);
    }
}
