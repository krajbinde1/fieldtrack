<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Models\Attendance;
use App\Filament\Support\EmployeeSelect;
use App\Filament\Support\TodayDateFilter;
use App\Services\Attendance\AttendanceStatusCalculator;
use App\Support\AttendanceCalendar;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class AttendancesTable
{
    public static function configure(Table $table): Table
    {
        $calculator = app(AttendanceStatusCalculator::class);

        return $table
            ->heading('Attendance (IST)')
            ->defaultSort('attendance_date', 'desc')
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->formatStateUsing(fn (Attendance $record): string => $record->employee?->displayLabel() ?? '-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.center.scheme.name')->label('Scheme / Project')->toggleable(),
                TextColumn::make('employee.center.name')->label('Center')->toggleable(),
                TextColumn::make('attendance_date')
                    ->label('Attendance Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('punch_in_time')
                    ->label('Punch In Time (IST)')
                    ->formatStateUsing(fn (Attendance $record): string => $record->punchInAt()?->timezone(AttendanceCalendar::TIMEZONE)->format('h:i A') ?? '-')
                    ->placeholder('-'),
                TextColumn::make('punch_out_time')
                    ->label('Punch Out Time (IST)')
                    ->formatStateUsing(fn (Attendance $record): string => $record->punchOutAt()?->timezone(AttendanceCalendar::TIMEZONE)->format('h:i A') ?? '-')
                    ->placeholder('-'),
                ImageColumn::make('punch_in_photo')
                    ->label('Punch In Photo')
                    ->disk('public')
                    ->visibility('public')
                    ->square()
                    ->imageHeight(48)
                    ->url(fn (?string $state): ?string => filled($state) ? Storage::disk('public')->url($state) : null)
                    ->openUrlInNewTab()
                    ->toggleable(),
                ImageColumn::make('punch_out_photo')
                    ->label('Punch Out Photo')
                    ->disk('public')
                    ->visibility('public')
                    ->square()
                    ->imageHeight(48)
                    ->url(fn (?string $state): ?string => filled($state) ? Storage::disk('public')->url($state) : null)
                    ->openUrlInNewTab()
                    ->toggleable(),
                TextColumn::make('punch_in_location')
                    ->label('Punch in location')
                    ->placeholder('-')
                    ->url(fn (Attendance $record): ?string => $record->punchInMapsUrl())
                    ->openUrlInNewTab()
                    ->toggleable(),
                TextColumn::make('punch_out_location')
                    ->label('Punch out location')
                    ->placeholder('-')
                    ->url(fn (Attendance $record): ?string => $record->punchOutMapsUrl())
                    ->openUrlInNewTab()
                    ->toggleable(),
                TextColumn::make('working_hours')
                    ->label('Working Hours')
                    ->state(fn (Attendance $record): string => $calculator->formatWorkingHoursLabel($record)),
                TextColumn::make('attendance_status')
                    ->label('Attendance Status')
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
                TextColumn::make('approval_status')
                    ->label('Approval Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Approved' => 'success',
                        'Rejected' => 'danger',
                        default => 'warning',
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
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
