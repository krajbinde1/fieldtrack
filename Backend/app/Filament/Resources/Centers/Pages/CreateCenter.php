<?php

namespace App\Filament\Resources\Centers\Pages;

use App\Filament\Resources\Centers\CenterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCenter extends CreateRecord
{
    protected static string $resource = CenterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['code'], $data['centerManagers']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = auth()->user();
        if ($user?->isProjectHead()) {
            $user->headedCenters()->syncWithoutDetaching([$this->record->id]);
        }
    }
}
