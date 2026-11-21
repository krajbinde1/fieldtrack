<?php

namespace App\Http\Middleware;

use App\Filament\Pages\SelectCenter;
use App\Models\User;
use App\Services\AdminCenterContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminCenterSelected
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdmin() || ! $this->requiresCenter($request)) {
            return $next($request);
        }

        $context = app(AdminCenterContext::class);

        if ($context->hasValidSelection($user)) {
            return $next($request);
        }

        $context->forget();

        return redirect()->guest(SelectCenter::getUrl());
    }

    private function requiresCenter(Request $request): bool
    {
        $panelPath = trim((string) (Filament::getCurrentPanel()?->getPath() ?? 'admin'), '/');
        $path = trim($request->path(), '/');

        if ($path === $panelPath) {
            return true;
        }

        foreach ([
            'users',
            'attendances',
            'employee-routes',
            'admissions',
            'admission-targets',
            'leave-requests',
        ] as $module) {
            $base = $panelPath.'/'.$module;
            if ($path === $base || str_starts_with($path, $base.'/')) {
                return true;
            }
        }

        return false;
    }
}
