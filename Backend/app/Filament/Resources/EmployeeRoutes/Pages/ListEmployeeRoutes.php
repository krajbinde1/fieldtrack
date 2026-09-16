<?php

namespace App\Filament\Resources\EmployeeRoutes\Pages;

use App\Filament\Resources\EmployeeRoutes\EmployeeRouteResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListEmployeeRoutes extends ListRecords
{
    protected static string $resource = EmployeeRouteResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Track live and historical field routes for employees who have punched in.';
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->whereNotNull('punch_in_time')
            ->with(['employee'])
            ->withCount('routePoints');
    }
}
