<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Models\Attendance;
use App\Services\Attendance\AttendanceStatusCalculator;
use App\Support\AttendanceCalendar;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Daily Attendance')
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->formatStateUsing(fn (Attendance $record): string => $record->employee?->displayLabel() ?? '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('employees.full_name', 'like', '%'.$search.'%');
                    }),
                TextColumn::make('employee.center.scheme.name')
                    ->label('Scheme / Project')
                    ->placeholder('—'),
                TextColumn::make('employee.center.name')
                    ->label('Center')
                    ->placeholder('—'),
                TextColumn::make('punch_in_time')
                    ->label('Punch In')
                    ->state(fn (Attendance $record): string => $record->punchInAt()?->timezone(AttendanceCalendar::TIMEZONE)->format('h:i A') ?? '—'),
                TextColumn::make('punch_out_time')
                    ->label('Punch Out')
                    ->state(fn (Attendance $record): string => $record->punchOutAt()?->timezone(AttendanceCalendar::TIMEZONE)->format('h:i A') ?? '—'),
                TextColumn::make('working_hours')
                    ->label('Working Hours')
                    ->state(fn (Attendance $record): string => app(AttendanceStatusCalculator::class)->formatWorkingHoursLabel(
                        self::hasAttendance($record) ? $record : null,
                    )),
                TextColumn::make('attendance_status')
                    ->label('Attendance Status')
                    ->badge()
                    ->state(fn (Attendance $record): string => self::statusKey($record))
                    ->formatStateUsing(fn (string $state): string => AttendanceStatusCalculator::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        AttendanceStatusCalculator::STATUS_PRESENT => 'success',
                        AttendanceStatusCalculator::STATUS_ABSENT => 'danger',
                        AttendanceStatusCalculator::STATUS_HALF_DAY => 'warning',
                        AttendanceStatusCalculator::STATUS_PUNCHED_IN => 'info',
                        AttendanceStatusCalculator::STATUS_LEAVE => 'info',
                        default => 'gray',
                    }),
            ])
            ->recordUrl(fn (Attendance $record): ?string => self::hasAttendance($record)
                ? AttendanceResource::getUrl('view', ['record' => $record])
                : null)
            ->recordActions([
                ViewAction::make()
                    ->label('View Details')
                    ->visible(fn (Attendance $record): bool => self::hasAttendance($record)),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('No employees for this day')
            ->emptyStateDescription('Try another date or summary card.');
    }

    private static function hasAttendance(Attendance $record): bool
    {
        return (int) $record->getKey() > 0;
    }

    private static function statusKey(Attendance $record): string
    {
        $raw = $record->attendance_date;
        $date = $raw instanceof Carbon
            ? Carbon::parse($raw->toDateString(), AttendanceCalendar::TIMEZONE)->startOfDay()
            : Carbon::parse((string) ($raw ?: AttendanceCalendar::today()->toDateString()), AttendanceCalendar::TIMEZONE)->startOfDay();

        return app(AttendanceStatusCalculator::class)->classifyWorkingDay(
            self::hasAttendance($record) ? $record : null,
            $date,
        );
    }
}
