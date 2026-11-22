<?php

namespace App\Filament\Support;

use App\Filament\Pages\Reports;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\AdmissionTargets\AdmissionTargetResource;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\EmployeeRoutes\EmployeeRouteResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

final class CoordinatorCenterModules
{
    /**
     * Center-dashboard modules hidden from the coordinator sidebar.
     * The center dashboard itself is included for the selection redirect.
     *
     * @return list<string>
     */
    public static function pathBases(bool $includeCenterDashboard = true): array
    {
        $bases = [
            'attendances',
            'employee-routes',
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

    /**
     * Existing resource and page URLs. These are the same screens the sidebar used.
     *
     * @return list<array{label: string, url: string}>
     */
    public static function links(): array
    {
        return [
            ['label' => 'Attendances', 'url' => AttendanceResource::getUrl()],
            ['label' => 'Employee Routes', 'url' => EmployeeRouteResource::getUrl()],
            ['label' => 'Admissions', 'url' => AdmissionResource::getUrl()],
            ['label' => 'Admission Targets', 'url' => AdmissionTargetResource::getUrl()],
            ['label' => 'Leave Requests', 'url' => LeaveRequestResource::getUrl()],
            ['label' => 'Reports', 'url' => Reports::getUrl()],
        ];
    }
}
