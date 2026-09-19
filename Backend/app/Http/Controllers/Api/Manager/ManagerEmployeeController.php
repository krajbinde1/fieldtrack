<?php

namespace App\Http\Controllers\Api\Manager;

use App\Enums\CenterStaffRole;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Employee;
use App\Models\User;
use App\Services\DirectorWorkforceService;
use App\Services\OrganizationAccessService;
use App\Support\LoginId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ManagerEmployeeController extends Controller
{
    public function __construct(
        private readonly OrganizationAccessService $access,
        private readonly DirectorWorkforceService $workforce,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isAdminOrDirector() || $user->isProjectHead()) {
            $payload = $this->workforce->index($request);

            return response()->json([
                'success' => true,
                'data' => $payload['data'],
                'meta' => $payload['meta'],
            ]);
        }
        $employees = $this->access->employeeQuery($user)
            ->with(['center:id,name', 'user:id,employee_id,login_id'])
            ->when($this->access->requestedCenterId($request), function ($query, int $centerId): void {
                $query->where('center_id', $centerId);
            })
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
            ->map(fn (Employee $employee) => $this->employeePayload($employee))
            ->values();

        $centers = $this->access->centerQuery($user)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Center $center) => [
                'id' => $center->id,
                'name' => $center->name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $employees,
            'meta' => [
                'centers' => $centers,
                'staff_roles' => CenterStaffRole::options(),
                'can_create' => $this->access->canManageEmployees($user),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($this->access->canManageEmployees($actor), 403);

        $centerIds = $this->access->visibleCenterIds($actor) ?? [];
        $data = $request->validate([
            'center_id' => ['nullable', 'integer', Rule::in($centerIds === [] ? [0] : $centerIds)],
            'full_name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'regex:/^[6-9][0-9]{9}$/', Employee::uniqueAmongActive('mobile')],
            'email' => ['nullable', 'email', 'max:255'],
            'staff_role' => ['required', Rule::in(CenterStaffRole::values())],
            'status' => ['sometimes', 'boolean'],
        ]);

        if (count($centerIds) === 1) {
            $data['center_id'] = $centerIds[0];
        }

        $center = Center::query()->find($data['center_id'] ?? null);
        abort_unless(
            $center !== null && $this->access->canManageEmployees($actor, $center),
            403,
            'You can only create users for your own Center.',
        );

        $loginId = trim((string) $data['mobile']);
        LoginId::assertUnique($loginId, attribute: 'mobile');

        $role = CenterStaffRole::tryFromMixed($data['staff_role'] ?? null);
        $password = LoginId::defaultPasswordFromMobile($loginId);

        $employee = DB::transaction(function () use ($actor, $center, $data, $role, $loginId, $password): Employee {
            $employee = Employee::query()->create([
                'center_id' => $center->id,
                'full_name' => $data['full_name'],
                'mobile' => $data['mobile'],
                'email' => $data['email'] ?? null,
                'staff_role' => $role->value,
                'designation' => $role->label(),
                'department' => 'Center',
                'created_by_user_id' => $actor->id,
                'status' => (bool) ($data['status'] ?? true),
            ]);

            User::query()->create([
                'employee_id' => $employee->id,
                'name' => $employee->full_name,
                'email' => $employee->email ?: $employee->mobile.'@fieldtrack.local',
                'login_id' => $loginId,
                'password' => Hash::make($password),
                'role' => UserRole::Employee->value,
                'is_active' => (bool) $employee->status,
                'must_change_password' => true,
            ]);

            return $employee->load(['center:id,name', 'user:id,employee_id,login_id']);
        });

        return response()->json([
            'success' => true,
            'data' => $this->employeePayload($employee),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function employeePayload(Employee $employee): array
    {
        return [
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
        ];
    }
}
