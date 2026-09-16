<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class Reports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.reports';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isDirector() || $user?->isProjectHead() || $user?->isCenterManager());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(OrganizationAccessService::class)
                ->attendanceQuery(auth()->user())
                ->with(['employee.center.project']))
            ->defaultSort('attendance_date', 'desc')
            ->columns([
                TextColumn::make('attendance_date')->date('d M Y')->sortable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                TextColumn::make('employee.center.project.name')->label('Project'),
                TextColumn::make('employee.center.name')->label('Center'),
                TextColumn::make('punch_in_time')->label('Punch In'),
                TextColumn::make('punch_out_time')->label('Punch Out'),
                TextColumn::make('working_hours')->label('Hours'),
                TextColumn::make('attendance_status')->badge(),
                TextColumn::make('total_route_distance_km')->label('Distance (km)'),
            ])
            ->filters([
                \App\Filament\Support\TodayDateFilter::make('attendance_date', 'Date'),
                SelectFilter::make('attendance_status')->options(Attendance::ATTENDANCE_STATUS_LABELS),
            ]);
    }
}
