<?php

namespace App\Filament\Resources\Centers\Pages;

use App\Filament\Resources\Centers\CenterResource;
use App\Models\Center;
use App\Services\CenterLoginService;
use Filament\Resources\Pages\CreateRecord;

class CreateCenter extends CreateRecord
{
    protected static string $resource = CenterResource::class;

    public function mount(): void
    {
        abort_unless(CenterResource::canCreate(), 403);

        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['code'], $data['login_id'], $data['login_user_id'], $data['centerManagers']);

        return app(CenterLoginService::class)->applyManagerSelection($data, $this->data ?? []);
    }

    protected function afterCreate(): void
    {
        $user = auth()->user();
        if ($user?->isProjectHead()) {
            $user->headedCenters()->syncWithoutDetaching([$this->record->id]);
        }

        $record = $this->getRecord();
        if ($record instanceof Center) {
            app(CenterLoginService::class)->syncAccount($record);
        }
    }
}
