<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RequiresSelectedAdminCenter;
use App\Filament\Support\TodayDateFilter;
use App\Models\Attendance;
use App\Services\AdminCenterContext;
use App\Services\OrganizationAccessService;
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
    use RequiresSelectedAdminCenter;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.reports';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->isDirector() || $user?->isCenterCoordinator() || $user?->isCenterManager());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $user = auth()->user();
                $query = app(OrganizationAccessService::class)
                    ->attendanceQuery($user)
                    ->with(['employee.center.scheme']);
                $context = app(AdminCenterContext::class);

                if ($context->coordinatorMustPickCenter($user)) {
                    return $query->whereRaw('1 = 0');
                }

                return $context->scopeByEmployeeCenter($query, $user);
            })
            ->defaultSort('attendance_date', 'desc')
            ->columns([
                TextColumn::make('attendance_date')->date('d M Y')->sortable(),
                TextColumn::make('employee.full_name')->label('Employee')->searchable(),
                TextColumn::make('employee.center.scheme.name')->label('Scheme / Project'),
                TextColumn::make('employee.center.name')->label('Center'),
                TextColumn::make('punch_in_time')->label('Punch In'),
                TextColumn::make('punch_out_time')->label('Punch Out'),
                TextColumn::make('working_hours')->label('Hours'),
                TextColumn::make('attendance_status')->badge(),
                TextColumn::make('total_route_distance_km')->label('Distance (km)'),
            ])
            ->filters([
                TodayDateFilter::make('attendance_date', 'Date'),
                SelectFilter::make('attendance_status')->options(Attendance::ATTENDANCE_STATUS_LABELS),
            ]);
    }
}
