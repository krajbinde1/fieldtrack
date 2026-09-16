<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Manage field users assigned to your center.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
