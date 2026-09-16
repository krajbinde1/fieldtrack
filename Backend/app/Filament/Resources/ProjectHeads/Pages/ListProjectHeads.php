<?php

namespace App\Filament\Resources\ProjectHeads\Pages;

use App\Filament\Resources\ProjectHeads\ProjectHeadResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjectHeads extends ListRecords
{
    protected static string $resource = ProjectHeadResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
