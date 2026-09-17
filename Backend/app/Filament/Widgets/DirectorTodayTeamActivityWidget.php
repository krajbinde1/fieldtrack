<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Models\Attendance;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class DirectorTodayTeamActivityWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdminOrDirector() === true;
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $access = app(OrganizationAccessService::class);
        $today = AttendanceCalendar::today()->toDateString();
        $employeeIds = $access->employeeQuery($user)->where('status', true)->pluck('id');

        return $table
            ->heading('Today Team Activity')
            ->query(fn (): Builder => Attendance::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('attendance_date', $today)
                ->with(['employee.center.scheme'])
                ->orderByRaw('punch_in_time is null')
                ->orderBy('punch_in_time'))
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->formatStateUsing(fn (Attendance $record): string => $record->employee?->displayLabel() ?? '-'),
                TextColumn::make('employee.center.name')->label('Center')->placeholder('-'),
                TextColumn::make('punch_in_time')
                    ->label('Punch In')
                    ->formatStateUsing(fn (Attendance $record): string => $record->punchInAt()?->timezone(AttendanceCalendar::TIMEZONE)->format('h:i A') ?? '-')
                    ->placeholder('-'),
                TextColumn::make('punch_out_time')
                    ->label('Punch Out')
                    ->formatStateUsing(fn (Attendance $record): string => $record->punchOutAt()?->timezone(AttendanceCalendar::TIMEZONE)->format('h:i A') ?? '-')
                    ->placeholder('-'),
                TextColumn::make('attendance_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Attendance::ATTENDANCE_STATUS_LABELS[$state] ?? $state),
            ])
            ->recordUrl(fn (Attendance $record): string => AttendanceResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No team activity today')
            ->emptyStateDescription('Punch-in and punch-out activity will appear here as the team reports attendance.');
    }
}
