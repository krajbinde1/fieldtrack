<?php

namespace App\Filament\Resources\Centers\Pages;

use App\Filament\Concerns\HoldsPendingCenterAccounts;
use App\Filament\Resources\Centers\CenterResource;
use App\Services\CenterLoginService;
use Filament\Resources\Pages\CreateRecord;

class CreateCenter extends CreateRecord
{
    use HoldsPendingCenterAccounts;

    protected static string $resource = CenterResource::class;

    public function hasDatabaseTransactions(): bool
    {
        return true;
    }

    public function mount(): void
    {
        abort_unless(CenterResource::canCreate(), 403);

        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['code'], $data['login_id'], $data['login_user_id'], $data['centerManagers'], $data['coordinators']);

        $state = $this->data ?? [];
        unset($state['centerManagers']);

        return app(CenterLoginService::class)->applyManagerSelection($data, $state);
    }
}
