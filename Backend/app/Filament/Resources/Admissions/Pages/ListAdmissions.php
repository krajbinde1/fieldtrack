<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Filament\Resources\Admissions\AdmissionResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAdmissions extends ListRecords
{
    protected static string $resource = AdmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with([
            'scheme',
            'employee.center.scheme',
            'center',
            'district',
            'taluka',
        ]);
    }
}
