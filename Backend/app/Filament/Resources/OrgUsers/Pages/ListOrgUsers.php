<?php

namespace App\Filament\Resources\OrgUsers\Pages;

use App\Filament\Resources\OrgUsers\OrgUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListOrgUsers extends ListRecords
{
    protected static string $resource = OrgUserResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Manage organization user accounts and roles for your authorized scope.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
