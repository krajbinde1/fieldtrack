<?php

namespace App\Filament\Resources\CenterManagers\Pages;

use App\Enums\UserRole;
use App\Filament\Concerns\ResolvesOptionalLoginId;
use App\Filament\Resources\CenterManagers\CenterManagerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCenterManager extends CreateRecord
{
    use ResolvesOptionalLoginId;

    protected static string $resource = CenterManagerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->resolveLoginIdForCreate($data);
        $data['role'] = UserRole::CenterManager->value;
        $data['must_change_password'] = $data['must_change_password'] ?? true;

        return $data;
    }
}
