<?php

namespace App\Filament\Resources\EmployeeRoutes\Pages;

use App\Filament\Resources\EmployeeRoutes\EmployeeRouteResource;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class LiveEmployeeTracking extends Page
{
    protected static string $resource = EmployeeRouteResource::class;

    protected static ?string $title = 'Live Tracking';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.resources.employee-routes.live-employee-tracking';

    public static function canAccess(array $parameters = []): bool
    {
        return EmployeeRouteResource::canViewAny();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Employees who are punched in right now. Markers move only when a new GPS fix is stored.';
    }

    public function overviewUrl(): string
    {
        return route('employee-routes.tracking.overview');
    }
}
