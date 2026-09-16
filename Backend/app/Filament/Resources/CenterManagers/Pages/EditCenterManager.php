<?php

namespace App\Filament\Resources\CenterManagers\Pages;

use App\Filament\Concerns\ResolvesOptionalLoginId;
use App\Filament\Resources\CenterManagers\CenterManagerResource;
use Filament\Resources\Pages\EditRecord;

class EditCenterManager extends EditRecord
{
    use ResolvesOptionalLoginId;

    protected static string $resource = CenterManagerResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->resolveLoginIdForSave($data);
    }
}
