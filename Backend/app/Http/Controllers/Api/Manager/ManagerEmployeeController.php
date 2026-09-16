<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerEmployeeController extends Controller
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        $employees = $this->access->employeeQuery($request->user())
            ->with(['center:id,name', 'user:id,employee_id,login_id'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('full_name', 'like', $term)
                        ->orWhere('employee_code', 'like', $term)
                        ->orWhere('mobile', 'like', $term);
                });
            })
            ->orderBy('full_name')
            ->limit(200)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'mobile' => $employee->mobile,
                'email' => $employee->email,
                'designation' => $employee->designation,
                'staff_role' => $employee->staffRoleEnum()->value,
                'staff_role_label' => $employee->staffRoleEnum()->label(),
                'center_id' => $employee->center_id,
                'center_name' => $employee->center?->name,
                'login_id' => $employee->user?->login_id,
                'status' => (bool) $employee->status,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }
}
