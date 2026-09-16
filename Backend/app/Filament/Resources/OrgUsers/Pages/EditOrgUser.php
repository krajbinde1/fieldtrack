<?php

namespace App\Filament\Resources\OrgUsers\Pages;

use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Support\LoginId;
use Filament\Resources\Pages\EditRecord;

class EditOrgUser extends EditRecord
{
    protected static string $resource = OrgUserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['role']);

        $data['login_id'] = LoginId::resolveFromEmail(
            $data['login_id'] ?? null,
            $data['email'] ?? $this->record->email,
        );
        LoginId::assertUnique($data['login_id'], $this->record->id);

        return $data;
    }
}
