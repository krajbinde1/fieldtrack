<?php

namespace App\Filament\Resources\OrgUsers\Pages;

use App\Filament\Resources\OrgUsers\OrgUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrgUsers extends ListRecords
{
    protected static string $resource = OrgUserResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
