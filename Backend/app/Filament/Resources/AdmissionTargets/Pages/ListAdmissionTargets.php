<?php

namespace App\Filament\Resources\AdmissionTargets\Pages;

use App\Filament\Resources\AdmissionTargets\AdmissionTargetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListAdmissionTargets extends ListRecords
{
    protected static string $resource = AdmissionTargetResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Set and monitor admission targets for employees across your authorized centers.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with([
            'employee.center.scheme',
            'center',
            'scheme',
            'weeks',
        ]);
    }
}
