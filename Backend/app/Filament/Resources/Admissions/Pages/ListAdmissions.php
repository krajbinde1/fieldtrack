<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Filament\Resources\Admissions\AdmissionResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListAdmissions extends ListRecords
{
    protected static string $resource = AdmissionResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'Admissions';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Review and manage field admissions across your authorized centers.';
    }

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
