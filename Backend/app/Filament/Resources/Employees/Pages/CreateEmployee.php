<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Enums\CenterStaffRole;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Center;
use App\Services\CenterStaffCredentialService;
use App\Services\OrganizationAccessService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected bool $reusedExisting = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);

        $access = app(OrganizationAccessService::class);
        $centerIds = $access->visibleCenterIds($actor) ?? [];
        if (count($centerIds) === 1) {
            $data['center_id'] = $centerIds[0];
        }

        $center = Center::query()->find($data['center_id'] ?? null);
        abort_unless(
            $center !== null && $access->canManageCenterUsers($actor, $center),
            403,
            'You can only create users for your own Center.',
        );

        $loginId = trim((string) ($data['mobile'] ?? ''));
        if ($loginId === '') {
            throw ValidationException::withMessages([
                'mobile' => 'Mobile Number is required.',
            ]);
        }

        $role = CenterStaffRole::tryFromMixed($data['staff_role'] ?? null);
        $data['staff_role'] = $role->value;
        $data['designation'] = $role->label();
        $data['department'] = $data['department'] ?? 'Center';
        $data['created_by_user_id'] = $actor->id;
        $data['status'] = (bool) ($data['status'] ?? true);

        unset($data['login_password'], $data['login_id'], $data['scheme_name'], $data['project_name'], $data['center_manager_name']);

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $credentials = app(CenterStaffCredentialService::class);
        $employee = $credentials->placeEmployee($data);
        $this->reusedExisting = $credentials->reusedExistingAccount;

        return $employee;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        if ($this->reusedExisting) {
            return 'Existing user assigned to this center.';
        }

        return parent::getCreatedNotificationTitle();
    }
}
