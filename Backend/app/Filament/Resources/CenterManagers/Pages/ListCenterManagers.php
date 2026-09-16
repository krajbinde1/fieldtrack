<?php

namespace App\Filament\Resources\CenterManagers\Pages;

use App\Filament\Resources\CenterManagers\CenterManagerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCenterManagers extends ListRecords
{
    protected static string $resource = CenterManagerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
