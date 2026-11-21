<?php

namespace App\Filament\Resources\Centers\Pages;

use App\Enums\UserRole;
use App\Filament\Concerns\HoldsPendingCenterAccounts;
use App\Filament\Resources\Centers\CenterResource;
use App\Models\Center;
use App\Services\CenterLoginService;
use Filament\Resources\Pages\EditRecord;

class EditCenter extends EditRecord
{
    use HoldsPendingCenterAccounts;

    protected static string $resource = CenterResource::class;

    public function hasDatabaseTransactions(): bool
    {
        return true;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['code'], $data['login_id'], $data['login_user_id'], $data['centerManagers'], $data['coordinators']);

        $record = $this->getRecord();
        $ids = [];
        if ($record instanceof Center) {
            $record->unsetRelation('centerManagers');
            $ids = $record->centerManagers()
                ->where('users.role', UserRole::CenterManager->value)
                ->where('users.is_center_login', false)
                ->pluck('users.id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        return app(CenterLoginService::class)->applyManagerSelection($data, [
            'centerManagers' => $ids,
        ]);
    }
}
