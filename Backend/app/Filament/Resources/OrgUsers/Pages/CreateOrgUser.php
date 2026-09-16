<?php

namespace App\Filament\Resources\OrgUsers\Pages;

use App\Filament\Concerns\ResolvesOptionalLoginId;
use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Services\OrganizationAccessService;
use Filament\Resources\Pages\CreateRecord;

class CreateOrgUser extends CreateRecord
{
    use ResolvesOptionalLoginId;

    protected static string $resource = OrgUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);

        $allowed = app(OrganizationAccessService::class)->creatableOrgUserRoles($actor);
        abort_unless(in_array($data['role'] ?? '', $allowed, true), 403);

        $data = $this->resolveLoginIdForCreate($data);
        $data['must_change_password'] = $data['must_change_password'] ?? true;

        return $data;
    }
}
