<?php

namespace App\Filament\Resources\TeamActivities\Pages;

use App\Filament\Resources\TeamActivities\TeamActivityResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListTeamActivities extends ListRecords
{
    protected static string $resource = TeamActivityResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Select an employee to view their activity records.';
    }
}
