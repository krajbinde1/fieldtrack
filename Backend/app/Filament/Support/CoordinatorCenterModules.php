<?php

namespace App\Filament\Support;

use Filament\Facades\Filament;
use Illuminate\Http\Request;

final class CoordinatorCenterModules
{
    /**
     * Center modules that require one selected center.
     * The center dashboard itself is included for the selection redirect.
     *
     * @return list<string>
     */
    public static function pathBases(bool $includeCenterDashboard = true): array
    {
        $bases = [
            'attendances',
            'employee-routes',
            'team-activities',
            'admissions',
            'admission-targets',
            'leave-requests',
            'reports',
        ];

        if ($includeCenterDashboard) {
            array_unshift($bases, 'center-dashboard');
        }

        return $bases;
    }

    public static function requestMatches(Request $request, bool $includeCenterDashboard = true): bool
    {
        $panelPath = trim((string) (Filament::getCurrentPanel()?->getPath() ?? 'admin'), '/');
        $path = trim($request->path(), '/');

        foreach (self::pathBases($includeCenterDashboard) as $module) {
            $base = $panelPath.'/'.$module;
            if ($path === $base || str_starts_with($path, $base.'/')) {
                return true;
            }
        }

        return false;
    }
}
