<?php

namespace App\Http\Middleware;

use App\Filament\Pages\ChangePassword;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Newly provisioned coordinators and managers must replace the temporary password
     * before they use the rest of the web panel. Existing accounts are unchanged.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->must_change_password !== true || $this->isAllowed($request)) {
            return $next($request);
        }

        return redirect()->to(ChangePassword::getUrl());
    }

    private function isAllowed(Request $request): bool
    {
        if ($request->routeIs('filament.admin.pages.change-password', 'filament.admin.auth.logout')) {
            return true;
        }

        $path = trim($request->path(), '/');

        return $path === 'admin/change-password' || $path === 'admin/logout';
    }
}
