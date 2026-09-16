<?php

namespace App\Filament\Resources\ProjectHeads\Pages;

use App\Filament\Concerns\ResolvesOptionalLoginId;
use App\Filament\Resources\ProjectHeads\ProjectHeadResource;
use Filament\Resources\Pages\EditRecord;

class EditProjectHead extends EditRecord
{
    use ResolvesOptionalLoginId;

    protected static string $resource = ProjectHeadResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->resolveLoginIdForSave($data);
    }
}
