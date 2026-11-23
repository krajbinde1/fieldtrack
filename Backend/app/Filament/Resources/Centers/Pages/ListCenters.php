<?php

namespace App\Filament\Resources\Centers\Pages;

use App\Filament\Concerns\HoldsPendingCenterAccounts;
use App\Filament\Resources\Centers\CenterResource;
use App\Filament\Support\CreateCenterManagerAction;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class ListCenters extends ListRecords
{
    use HoldsPendingCenterAccounts;

    protected static string $resource = CenterResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Review and manage centers across your authorized organization scope.';
    }

    protected function makeTable(): Table
    {
        $table = parent::makeTable();

        $user = auth()->user();
        if (! $user instanceof User || ! ($user->isAdmin() || $user->isDirector() || $user->isCenterCoordinator() || $user->isCenterManager())) {
            return $table;
        }

        return $table
            ->recordUrl(null)
            ->recordAction('openDashboard');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => CenterResource::canCreate()),
            CreateCenterManagerAction::make(),
        ];
    }
}
