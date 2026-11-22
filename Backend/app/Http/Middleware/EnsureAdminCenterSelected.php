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
     * Center dashboard and center modules require one selected center.
     * Admin, Director, Center Coordinator, and Center Manager are sent to
     * the centers list until they open a center they can access.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $context = app(AdminCenterContext::class);

        if (! $user instanceof User || ! $context->participatesInCenterSelection($user)) {
            return $next($request);
        }

        if ($this->isCenterModule($request) && ! $context->hasValidSelection($user)) {
            return redirect('/admin/centers');
        }

        return $next($request);
    }

    private function isCenterModule(Request $request): bool
    {
        return CoordinatorCenterModules::requestMatches($request);
    }
}
