<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Models\Attendance;
use App\Filament\Support\EmployeeSelect;
use App\Filament\Support\TodayDateFilter;
use App\Services\Attendance\AttendanceStatusCalculator;
use App\Support\AttendanceCalendar;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Attendance (IST)')
            ->defaultSort('attendance_date', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->formatStateUsing(fn (Attendance $record): string => $record->employee?->displayLabel() ?? '-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.center.scheme.name')->label('Scheme / Project'),
                TextColumn::make('employee.center.name')->label('Center'),
                TextColumn::make('attendance_date')
                    ->label('Attendance Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('punch_in_time')
                    ->label('Punch In Time')
                    ->formatStateUsing(fn (Attendance $record): string => $record->punchInAt()?->timezone(AttendanceCalendar::TIMEZONE)->format('h:i A') ?? '-')
                    ->placeholder('-'),
                TextColumn::make('punch_out_time')
                    ->label('Punch Out Time')
                    ->formatStateUsing(fn (Attendance $record): string => $record->punchOutAt()?->timezone(AttendanceCalendar::TIMEZONE)->format('h:i A') ?? '-')
                    ->placeholder('-'),
                TextColumn::make('attendance_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Attendance::ATTENDANCE_STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        AttendanceStatusCalculator::STATUS_PRESENT => 'success',
                        AttendanceStatusCalculator::STATUS_ABSENT => 'danger',
                        AttendanceStatusCalculator::STATUS_HALF_DAY => 'warning',
                        AttendanceStatusCalculator::STATUS_PUNCHED_IN => 'info',
                        AttendanceStatusCalculator::STATUS_LEAVE => 'info',
                        default => 'gray',
                    }),
            ])
            ->filters([
                TodayDateFilter::make('attendance_date', 'Date'),
                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'full_name')
                    ->tap(fn (SelectFilter $filter) => EmployeeSelect::applyRelationshipFilter($filter))
                    ->preload(),
                Filter::make('punched_in')
                    ->label('Punched In')
                    ->toggle()
                    ->query(function (Builder $query, array $data): Builder {
                        if (! ($data['isActive'] ?? false)) {
                            return $query;
                        }

                        return $query->whereNotNull('punch_in_time');
                    })
                    ->indicateUsing(fn (array $data): ?string => ($data['isActive'] ?? false) ? 'Punched In' : null),
                SelectFilter::make('attendance_status')
                    ->label('Attendance Status')
                    ->options(Attendance::ATTENDANCE_STATUS_LABELS),
                SelectFilter::make('approval_status')
                    ->label('Approval Status')
                    ->options(Attendance::APPROVAL_STATUS_LABELS),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()->label('View'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
