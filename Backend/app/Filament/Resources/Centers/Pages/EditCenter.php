<?php

namespace App\Filament\Resources\Centers\Pages;

use App\Filament\Resources\Centers\CenterResource;
use Filament\Resources\Pages\EditRecord;

class EditCenter extends EditRecord
{
    protected static string $resource = CenterResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['code'], $data['centerManagers']);

        return $data;
    }
}
