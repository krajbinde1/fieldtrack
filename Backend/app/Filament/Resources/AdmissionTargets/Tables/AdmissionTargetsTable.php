<?php

namespace App\Filament\Resources\AdmissionTargets\Tables;

use App\Enums\AdmissionTargetType;
use App\Filament\Support\EmployeeSelect;
use App\Filament\Support\TodayDateFilter;
use App\Models\AdmissionTarget;
use App\Services\AdmissionTargetService;
use App\Services\OrganizationAccessService;
use App\Support\AdmissionTargetPeriod;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdmissionTargetsTable
{
    public static function configure(Table $table): Table
    {
        $user = auth()->user();
        $access = app(OrganizationAccessService::class);
        $targets = app(AdmissionTargetService::class);

        return $table
            ->heading('Admission Targets')
            ->defaultSort('period_start', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->formatStateUsing(fn (AdmissionTarget $record): string => $record->employee?->displayLabel() ?? '-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('target_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof AdmissionTargetType ? $state->label() : (string) $state),
                TextColumn::make('period_start')
                    ->label('Period')
                    ->formatStateUsing(fn ($state, AdmissionTarget $record): string => ($record->period_start?->format('d M Y') ?? '-').' – '.($record->period_end?->format('d M Y') ?? '-'))
                    ->sortable(),
                TextColumn::make('target_count')->label('Target')->sortable(),
                TextColumn::make('achieved')
                    ->label('Achieved')
                    ->state(fn (AdmissionTarget $record): int => $targets->metricsForTarget($record)['achieved']),
                TextColumn::make('remaining')
                    ->label('Remaining')
                    ->state(fn (AdmissionTarget $record): int => $targets->metricsForTarget($record)['remaining']),
                TextColumn::make('percentage')
                    ->label('Achievement %')
                    ->state(fn (AdmissionTarget $record): string => $targets->metricsForTarget($record)['percentage'].'%'),
                TextColumn::make('center.name')->label('Center')->toggleable(),
                TextColumn::make('scheme.name')->label('Scheme / Project')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('period')
                    ->label('Period')
                    ->schema([
                        Select::make('preset')
                            ->label('Period')
                            ->options([
                                'this_week' => 'This Week',
                                'last_week' => 'Last Week',
                                'this_month' => 'This Month',
                                'last_month' => 'Last Month',
                                'custom' => 'Custom',
                            ])
                            ->placeholder('All periods'),
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $preset = $data['preset'] ?? null;
                        if (! filled($preset)) {
                            return $query;
                        }

                        $from = TodayDateFilter::normalizeDate($data['from'] ?? null);
                        $to = TodayDateFilter::normalizeDate($data['until'] ?? null);

                        if ($preset === 'custom' && (! filled($from) || ! filled($to))) {
                            return $query;
                        }

                        [$start, $end] = AdmissionTargetPeriod::resolve((string) $preset, $from, $to);

                        return $query
                            ->whereDate('period_start', '<=', $end->toDateString())
                            ->whereDate('period_end', '>=', $start->toDateString());
                    }),
                SelectFilter::make('target_type')
                    ->label('Type')
                    ->options(AdmissionTargetType::options()),
                SelectFilter::make('center_id')
                    ->label('Center')
                    ->relationship(
                        name: 'center',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $user ? $access->centerQuery($user) : $query->whereRaw('1=0'),
                    )
                    ->preload()
                    ->searchable(),
                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'full_name')
                    ->tap(fn (SelectFilter $filter) => EmployeeSelect::applyRelationshipFilter($filter))
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()->modal(false),
                EditAction::make(),
            ]);
    }
}
