<?php

namespace App\Filament\Resources\Directors\Pages;

use App\Filament\Concerns\ResolvesOptionalLoginId;
use App\Filament\Resources\Directors\DirectorResource;
use Filament\Resources\Pages\EditRecord;

class EditDirector extends EditRecord
{
    use ResolvesOptionalLoginId;

    protected static string $resource = DirectorResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->resolveLoginIdForSave($data);
    }
}
