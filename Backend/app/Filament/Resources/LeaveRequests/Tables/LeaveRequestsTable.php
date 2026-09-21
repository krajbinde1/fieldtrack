<?php

namespace App\Filament\Resources\LeaveRequests\Tables;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Filament\Support\EmployeeSelect;
use App\Models\LeaveRequest;
use App\Services\OrganizationAccessService;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeaveRequestsTable
{
    public static function configure(Table $table): Table
    {
        $user = auth()->user();
        $access = app(OrganizationAccessService::class);

        return $table
            ->heading('Leave Requests')
            ->defaultSort('from_date', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->formatStateUsing(fn (LeaveRequest $record): string => $record->employee?->displayLabel() ?? '-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('leave_type')
                    ->label('Leave Type')
                    ->formatStateUsing(fn ($state): string => $state instanceof LeaveType ? $state->label() : (string) $state),
                TextColumn::make('from_date')->date('d M Y')->sortable(),
                TextColumn::make('to_date')->date('d M Y')->sortable(),
                TextColumn::make('total_days')->label('Days'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof LeaveStatus ? $state->label() : ucfirst((string) $state))
                    ->color(fn ($state): string => match ($state instanceof LeaveStatus ? $state : LeaveStatus::tryFrom((string) $state)) {
                        LeaveStatus::Approved => 'success',
                        LeaveStatus::Rejected => 'danger',
                        LeaveStatus::Pending => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('scheme.name')->label('Scheme / Project')->toggleable(),
                TextColumn::make('center.name')->label('Center')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('scheme_id')
                    ->label('Scheme / Project')
                    ->relationship(
                        name: 'scheme',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $user ? $access->schemeQuery($user) : $query->whereRaw('1=0'),
                    )
                    ->preload()
                    ->searchable(),
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
                SelectFilter::make('leave_type')->options(LeaveType::options()),
                SelectFilter::make('status')->options(LeaveStatus::options()),
                Filter::make('project_head_only')
                    ->label('Project Manager leaves')
                    ->toggle()
                    ->visible(fn (): bool => auth()->user()?->isAdminOrDirector() === true)
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['isActive'] ?? false)) {
                            return $query;
                        }

                        return app(OrganizationAccessService::class)->constrainToProjectHeadLeaves($query);
                    })
                    ->indicateUsing(fn (array $data): ?string => ($data['isActive'] ?? false) ? 'Project Manager leaves' : null),
                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $inner, $date) => $inner->whereDate('to_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $inner, $date) => $inner->whereDate('from_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
