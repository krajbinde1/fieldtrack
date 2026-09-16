<?php

namespace App\Filament\Resources\OrgUsers\Pages;

use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Services\OrganizationAccessService;
use App\Support\LoginId;
use Filament\Resources\Pages\CreateRecord;

class CreateOrgUser extends CreateRecord
{
    protected static string $resource = OrgUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);

        $allowed = app(OrganizationAccessService::class)->creatableOrgUserRoles($actor);
        abort_unless(in_array($data['role'] ?? '', $allowed, true), 403);

        $data['login_id'] = LoginId::resolveFromEmail(
            $data['login_id'] ?? null,
            $data['email'] ?? $this->data['email'] ?? null,
        );
        LoginId::assertUnique($data['login_id']);
        $data['must_change_password'] = $data['must_change_password'] ?? true;

        return $data;
    }
}
