<?php

namespace App\Filament\Widgets;

use App\Enums\AdmissionStatus;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Support\FilamentFilterUrl;
use App\Models\Center;
use App\Models\User;
use App\Services\OrganizationAccessService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class DirectorConfirmedAdmissionsByCenterWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    private ?int $cachedMaxConfirmed = null;

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
                    ->html()
                    ->sortable()
                    ->formatStateUsing(function ($state) use ($user, $access): string {
                        $count = (int) $state;
                        $max = $this->maxConfirmedCount($access, $user);
                        $pct = $max > 0 ? min(100, (int) round(($count / $max) * 100)) : 0;

                        return '<div class="ft-dash-confirmed">'.
                            '<span class="ft-dash-confirmed-n">'.e((string) $count).'</span>'.
                            '<span class="ft-dash-confirmed-bar" aria-hidden="true"><span style="width: '.$pct.'%"></span></span>'.
                            '</div>';
                    }),
            ])
            ->recordUrl(fn (Center $record): string => FilamentFilterUrl::for(AdmissionResource::class, [
                'status' => ['value' => AdmissionStatus::Confirmed->value],
                'center_id' => ['value' => $record->id],
            ]))
            ->emptyStateHeading('No centers')
            ->emptyStateDescription('Confirmed admission totals will appear here by center.');
    }

    private function maxConfirmedCount(OrganizationAccessService $access, ?User $user): int
    {
        if ($this->cachedMaxConfirmed !== null) {
            return $this->cachedMaxConfirmed;
        }

        if ($user === null) {
            $this->cachedMaxConfirmed = 0;

            return 0;
        }

        $this->cachedMaxConfirmed = (int) ($access->centerQuery($user)
            ->withCount([
                'admissions as confirmed_admissions_count' => fn (Builder $query) => $query
                    ->where('status', AdmissionStatus::Confirmed),
            ])
            ->get()
            ->max('confirmed_admissions_count') ?? 0);

        return $this->cachedMaxConfirmed;
    }
}
