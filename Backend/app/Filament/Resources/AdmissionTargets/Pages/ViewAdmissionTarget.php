<?php

namespace App\Filament\Resources\AdmissionTargets\Pages;

use App\Filament\Resources\AdmissionTargets\AdmissionTargetResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAdmissionTarget extends ViewRecord
{
    protected static string $resource = AdmissionTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
