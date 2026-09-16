<?php

namespace App\Filament\Resources\OrgUsers\Pages;

use App\Filament\Concerns\ResolvesOptionalLoginId;
use App\Filament\Resources\OrgUsers\OrgUserResource;
use Filament\Resources\Pages\EditRecord;

class EditOrgUser extends EditRecord
{
    use ResolvesOptionalLoginId;

    protected static string $resource = OrgUserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['role']);

        return $this->resolveLoginIdForSave($data);
    }
}
