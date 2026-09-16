<?php

namespace App\Filament\Resources\ProjectHeads\Pages;

use App\Enums\UserRole;
use App\Filament\Concerns\ResolvesOptionalLoginId;
use App\Filament\Resources\ProjectHeads\ProjectHeadResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProjectHead extends CreateRecord
{
    use ResolvesOptionalLoginId;

    protected static string $resource = ProjectHeadResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->resolveLoginIdForCreate($data);
        $data['role'] = UserRole::ProjectHead->value;
        $data['must_change_password'] = $data['must_change_password'] ?? true;

        return $data;
    }
}
