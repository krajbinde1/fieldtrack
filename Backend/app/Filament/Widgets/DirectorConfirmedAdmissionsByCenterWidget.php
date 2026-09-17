<?php

namespace App\Filament\Widgets;

use App\Enums\AdmissionStatus;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Support\FilamentFilterUrl;
use App\Models\Center;
use App\Services\OrganizationAccessService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class DirectorConfirmedAdmissionsByCenterWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdminOrDirector() === true;
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $access = app(OrganizationAccessService::class);

        return $table
            ->heading('Center-wise Confirmed Admissions')
            ->query(fn (): Builder => $access->centerQuery($user)
                ->with('scheme:id,name')
                ->withCount([
                    'admissions as confirmed_admissions_count' => fn (Builder $query) => $query
                        ->where('status', AdmissionStatus::Confirmed),
                ])
                ->orderByDesc('confirmed_admissions_count')
                ->orderBy('name'))
            ->columns([
                TextColumn::make('name')->label('Center')->searchable(),
                TextColumn::make('scheme.name')->label('Scheme / Project')->placeholder('-'),
                TextColumn::make('confirmed_admissions_count')
                    ->label('Confirmed')
                    ->numeric()
                    ->sortable(),
            ])
            ->recordUrl(fn (Center $record): string => FilamentFilterUrl::for(AdmissionResource::class, [
                'status' => ['value' => AdmissionStatus::Confirmed->value],
                'center_id' => ['value' => $record->id],
            ]))
            ->emptyStateHeading('No centers')
            ->emptyStateDescription('Confirmed admission totals will appear here by center.');
    }
}
