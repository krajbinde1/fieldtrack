<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AdminCenterContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminCenterSelected
{
    /**
     * Clears a stale or unauthorized admin center. A missing selection stays on the
     * aggregated main dashboard instead of forcing a center choice.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isAdmin()) {
            app(AdminCenterContext::class)->idFor($user);
        }

        return $next($request);
    }
}
