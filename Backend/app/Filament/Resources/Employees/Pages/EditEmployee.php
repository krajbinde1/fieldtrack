<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function afterSave(): void
    {
        $employee = $this->record;
        $employee->user()?->update([
            'name' => $employee->full_name,
            'is_active' => (bool) $employee->status,
        ]);
    }
}
