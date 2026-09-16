<?php

namespace App\Filament\Resources\Directors\Pages;

use App\Filament\Resources\Directors\DirectorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDirectors extends ListRecords
{
    protected static string $resource = DirectorResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
