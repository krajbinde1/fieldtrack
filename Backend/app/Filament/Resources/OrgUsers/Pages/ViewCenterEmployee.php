<?php

namespace App\Filament\Resources\OrgUsers\Pages;

use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Models\Employee;
use App\Models\User;
use Filament\Resources\Pages\Page;

class ViewCenterEmployee extends Page
{
    protected static string $resource = OrgUserResource::class;

    protected static ?string $title = 'Employee';

    protected string $view = 'filament.resources.org-users.view-center-employee';

    public Employee $employee;

    public function mount(Employee $employee): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $centerId = (int) request()->query('center', (int) $employee->center_id);
        $visible = OrgUserResource::findAuthorizedCenterEmployee($user, $centerId, $employee->id);
        abort_unless($visible instanceof Employee, 404);

        $this->employee = $visible;
    }

    public function getHeading(): string
    {
        return $this->employee->full_name;
    }
}
