<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Support\LoginId;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['login_id'] = $this->record->user?->login_id;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['login_id']);

        return $data;
    }

    protected function afterSave(): void
    {
        $employee = $this->record;
        $user = $employee->user;
        if ($user === null) {
            return;
        }

        $payload = [
            'name' => $employee->full_name,
            'is_active' => (bool) $employee->status,
        ];

        $loginId = trim((string) ($this->data['login_id'] ?? ''));
        if ($loginId !== '') {
            LoginId::assertUnique($loginId, $user->id);
            $payload['login_id'] = $loginId;
        }

        $user->update($payload);
    }
}
