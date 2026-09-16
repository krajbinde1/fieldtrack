<?php

namespace App\Filament\Resources\Centers\Pages;

use App\Filament\Resources\Centers\CenterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListCenters extends ListRecords
{
    protected static string $resource = CenterResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Review and manage centers across your authorized organization scope.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
