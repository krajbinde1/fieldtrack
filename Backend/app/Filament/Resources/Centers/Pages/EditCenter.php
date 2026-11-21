<?php

namespace App\Filament\Resources\Centers\Pages;

use App\Filament\Resources\Centers\CenterResource;
use App\Models\Center;
use App\Services\CenterLoginService;
use Filament\Resources\Pages\EditRecord;

class EditCenter extends EditRecord
{
    protected static string $resource = CenterResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['code'], $data['login_id'], $data['login_user_id'], $data['centerManagers']);

        return app(CenterLoginService::class)->applyManagerSelection($data, $this->data ?? []);
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if ($record instanceof Center) {
            app(CenterLoginService::class)->syncAccount($record);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CenterResource::resetPasswordAction(),
        ];
    }
}
