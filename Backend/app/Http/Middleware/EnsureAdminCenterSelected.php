<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AdminCenterContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminCenterSelected
{
    /**
     * Admins without a selected center stay on the main dashboard, Centers, and
     * global settings. Center-specific modules redirect to the Centers list.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

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
        $panelPath = trim((string) (Filament::getCurrentPanel()?->getPath() ?? 'admin'), '/');
        $path = trim($request->path(), '/');

        foreach ([
            'users',
            'attendances',
            'employee-routes',
            'admissions',
            'admission-targets',
            'leave-requests',
            'reports',
        ] as $module) {
            $base = $panelPath.'/'.$module;
            if ($path === $base || str_starts_with($path, $base.'/')) {
                return true;
            }
        }

        return false;
    }
}
