<?php

namespace App\Filament\Widgets;

use App\Enums\AdmissionStatus;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Support\FilamentFilterUrl;
use App\Filament\Widgets\Concerns\UsesOrganizationWideDashboardScope;
use App\Models\Admission;
use App\Models\Center;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\OrganizationAccessService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class DirectorConfirmedAdmissionsByCenterWidget extends TableWidget
{
    use UsesOrganizationWideDashboardScope;

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
        $centerId = $user instanceof User && $user->isAdmin()
            ? app(AdminCenterContext::class)->idFor($user)
            : null;

        if ($centerId !== null) {
            return $this->admissionDetailsTable($table, $user, $centerId);
        }

        return $this->centerSummaryTable($table, $user);
    }

    private function admissionDetailsTable(Table $table, ?User $user, int $centerId): Table
    {
        $access = app(OrganizationAccessService::class);

        return $table
            ->heading('Admission Details')
            ->query(function () use ($access, $user, $centerId): Builder {
                if (! $user instanceof User) {
                    return Admission::query()->whereRaw('1 = 0');
                }

                return $access->admissionQuery($user)
                    ->where('center_id', $centerId)
                    ->with(['employee:id,full_name,employee_code'])
                    ->latest('updated_at');
            })
            ->columns([
                TextColumn::make('full_name')->label('Applicant')->searchable()->placeholder('-'),
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->formatStateUsing(fn (Admission $record): string => $record->employee?->displayLabel() ?? '-'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof AdmissionStatus ? $state->label() : ucfirst((string) $state))
                    ->color(fn ($state): string => ($state instanceof AdmissionStatus ? $state : AdmissionStatus::tryFrom((string) $state))?->color() ?? 'gray'),
                TextColumn::make('submitted_at')->label('Submitted')->dateTime('d M Y')->placeholder('-'),
            ])
            ->recordUrl(fn (Admission $record): string => AdmissionResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No admissions')
            ->emptyStateDescription('Admissions for this center will appear here.');
    }

    private function centerSummaryTable(Table $table, ?User $user): Table
    {
        $access = app(OrganizationAccessService::class);
        $context = app(AdminCenterContext::class);

        return $table
            ->heading('Center-wise Confirmed Admissions')
            ->query(fn (): Builder => $context->scopeCenters($access->centerQuery($user), $user)
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

        $this->cachedMaxConfirmed = (int) (app(AdminCenterContext::class)->scopeCenters($access->centerQuery($user), $user)
            ->withCount([
                'admissions as confirmed_admissions_count' => fn (Builder $query) => $query
                    ->where('status', AdmissionStatus::Confirmed),
            ])
            ->get()
            ->max('confirmed_admissions_count') ?? 0);

        return $this->cachedMaxConfirmed;
    }
}
