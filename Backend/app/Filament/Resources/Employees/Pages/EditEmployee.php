<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Enums\CenterStaffRole;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Center;
use App\Services\OrganizationAccessService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $employee = $this->record;
        $data['scheme_name'] = $employee->center?->scheme?->name ?: '—';
        $data['center_manager_name'] = $employee->createdByUser?->name
            ?: $employee->center?->centerManagers->pluck('name')->join(', ')
            ?: '—';

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);

        $access = app(OrganizationAccessService::class);
        $centerIds = $access->visibleCenterIds($actor) ?? [];
        if (count($centerIds) === 1) {
            $data['center_id'] = $centerIds[0];
        }

        $center = Center::query()->find($data['center_id'] ?? $this->record->center_id);
        abort_unless(
            $center !== null && $access->canManageEmployees($actor, $center),
            403,
            'You can only manage users for your own Center.',
        );

        $role = CenterStaffRole::tryFromMixed($data['staff_role'] ?? $this->record->staff_role);
        $data['staff_role'] = $role->value;
        $data['designation'] = $role->label();

        unset($data['login_id'], $data['login_password'], $data['scheme_name'], $data['project_name'], $data['center_manager_name']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            EmployeeResource::resetPasswordAction(),
        ];
    }

    protected function afterSave(): void
    {
        $employee = $this->record->fresh(['user']);
        $user = $employee->user;
        if ($user === null) {
            return;
        }

        $payload = [
            'name' => $employee->full_name,
            'is_active' => (bool) $employee->status,
        ];

        if (filled($employee->email)) {
            $payload['email'] = $employee->email;
        }

        try {
            $user->update($payload);
        } catch (QueryException $exception) {
            throw ValidationException::withMessages([
                'email' => 'Email must be unique.',
            ]);
        }
    }
}
