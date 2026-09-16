<?php

namespace App\Filament\Resources\Directors\Pages;

use App\Enums\UserRole;
use App\Filament\Concerns\ResolvesOptionalLoginId;
use App\Filament\Resources\Directors\DirectorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDirector extends CreateRecord
{
    use ResolvesOptionalLoginId;

    protected static string $resource = DirectorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->resolveLoginIdForCreate($data);
        $data['role'] = UserRole::Director->value;
        $data['must_change_password'] = $data['must_change_password'] ?? true;

        return $data;
    }
}
