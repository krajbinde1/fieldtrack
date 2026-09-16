<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Models\Employee;
use App\Support\AttendanceAdminMonthlyReport;
use App\Support\AttendanceCalendar;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class EmployeeMonthlyDetails extends Page
{
    protected static string $resource = AttendanceResource::class;

    protected static ?string $title = 'Monthly Attendance';

    protected static ?string $navigationLabel = 'Monthly Attendance';

    protected string $view = 'filament.resources.attendances.employee-monthly-details';

    public Employee $employee;

    public int $month;

    public int $year;

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
        $this->month = (int) request()->query('month', AttendanceCalendar::now()->month);
        $this->year = (int) request()->query('year', AttendanceCalendar::now()->year);
    }

    public function getHeading(): string|Htmlable
    {
        return 'Employee Monthly Attendance';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $monthLabel = AttendanceCalendar::now()->month($this->month)->format('F');

        return new HtmlString(
            e($this->employee->full_name).'<br>'.e($monthLabel.' '.$this->year),
        );
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            AttendanceResource::getUrl('index') => 'Attendances',
            url()->current() => 'Monthly Attendance',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        return AttendanceAdminMonthlyReport::monthlyDetailRows(
            $this->employee->id,
            $this->month,
            $this->year,
        );
    }
}
