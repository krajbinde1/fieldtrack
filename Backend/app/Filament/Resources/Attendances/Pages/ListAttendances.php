<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Attendances\Widgets\MonthlyAttendanceSummary;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Review daily punch-in, punch-out, and attendance status for your authorized employees.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Today's Attendance is the main table (top). Monthly summary sits below.
     */
    protected function getFooterWidgets(): array
    {
        return [
            MonthlyAttendanceSummary::class,
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with(['employee.center.scheme']);
    }
}
