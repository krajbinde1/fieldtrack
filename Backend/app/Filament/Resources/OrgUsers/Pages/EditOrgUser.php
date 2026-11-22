<?php

namespace App\Filament\Resources\OrgUsers\Pages;

use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Models\User;
use App\Services\OrganizationAccessService;
use App\Support\LoginId;
use App\Support\UserIdentity;
use Filament\Resources\Pages\EditRecord;

class EditOrgUser extends EditRecord
{
    protected static string $resource = OrgUserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['is_center_login']);

        $actor = auth()->user();
        $record = $this->getRecord();
        $access = app(OrganizationAccessService::class);

        if (! $actor instanceof User || ! $record instanceof User || ! $access->canChangeOrgUserRole($actor, $record)) {
            unset($data['role']);
        } else {
            $next = (string) ($data['role'] ?? $record->role);
            abort_unless(in_array($next, $access->assignableOrgUserRoles($actor, $record), true), 403);
            $data['role'] = $next;
        }

        $data['login_id'] = LoginId::resolveFromEmail(
            $data['login_id'] ?? null,
            $data['email'] ?? $this->record->email,
        );
        LoginId::assertUnique($data['login_id'], $this->record->id);
        UserIdentity::assertAvailable(
            (string) ($data['name'] ?? $this->record->name),
            (string) $data['login_id'],
            ignoreUserId: (int) $this->record->id,
            ignoreEmployeeId: $this->record->employee_id ? (int) $this->record->employee_id : null,
            attribute: 'login_id',
        );

        return $data;
    }
}
