<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Exceptions\DeviceRegisteredException;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\Auth\MobileSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class EmployeeAuthController extends Controller
{
    public function __construct(
        private readonly MobileSessionService $mobileSessions,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'login_id' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string'],
            'device_id' => ['nullable', 'string', 'max:64'],
        ]);

        $user = User::query()
            ->with(['employee.reportingManager', 'employee.center.project'])
            ->where('login_id', $credentials['login_id'])
            ->whereIn('role', UserRole::mobileValues())
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login ID or password.',
            ], 422);
        }

        if (! $user->canLoginToMobile()) {
            return response()->json([
                'success' => false,
                'message' => 'This account is inactive.',
            ], 403);
        }

        try {
            $session = $this->mobileSessions->startSession(
                $user,
                $credentials['device_id'] ?? $request->header('X-Device-Id'),
            );
        } catch (DeviceRegisteredException) {
            return $this->mobileSessions->deviceRegisteredResponse();
        }

        $user->load(['employee.reportingManager']);

        return response()->json([
            'success' => true,
            'token' => $session['token'],
            'session_id' => $session['session_id'],
            'user' => $this->userData($user),
            'employee' => $user->employee ? $this->employeeData($user->employee) : null,
            'permissions' => $this->mobilePermissions($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['employee.reportingManager', 'employee.center.project']);

        if (! $user->canLoginToMobile()) {
            $request->user()->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'message' => 'This account is inactive.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'user' => $this->userData($user),
            'employee' => $user->employee ? $this->employeeData($user->employee) : null,
            'permissions' => $this->mobilePermissions($user),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The current password is incorrect.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
            'user' => $this->userData($user->fresh()),
        ]);
    }

    public function updateProfilePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'image', 'max:2048', 'mimes:jpeg,jpg,png,webp'],
        ]);

        $user = $request->user()->load('employee');
        $employee = $user->employee;
        abort_if($employee === null, 403, 'Employee profile not found.');

        $oldPath = $employee->profile_photo_path;
        $path = $request->file('photo')->store('employees/profile-photos', 'public');

        $employee->update(['profile_photo_path' => $path]);

        if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        $employee = $employee->fresh(['reportingManager']);

        return response()->json([
            'success' => true,
            'message' => 'Profile photo updated.',
            'employee' => $this->employeeData($employee),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        $this->mobileSessions->endSession(
            $user,
            $token instanceof PersonalAccessToken ? $token : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'employee_id' => $user->employee_id,
            'login_id' => $user->login_id,
            'role' => $user->role,
            'role_label' => $user->roleEnum()->label(),
            'must_change_password' => $user->must_change_password,
        ];
    }

    /**
     * @return list<string>
     */
    private function mobilePermissions(User $user): array
    {
        return array_values($user->roleEnum()->mobilePermissions());
    }

    private function employeeData(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
            'mobile' => $employee->mobile,
            'email' => $employee->email,
            'department' => $employee->department,
            'designation' => $employee->designation,
            'center_id' => $employee->center_id,
            'center_name' => $employee->center?->name,
            'project_id' => $employee->center?->project_id,
            'project_name' => $employee->center?->project?->name,
            'reporting_manager' => $employee->reportingManager?->full_name,
            'base_location' => $employee->base_location,
            'joining_date' => $employee->joining_date?->toDateString(),
            'profile_photo_url' => $this->profilePhotoUrl($employee),
            'active' => (bool) $employee->status,
        ];
    }

    private function profilePhotoUrl(Employee $employee): ?string
    {
        if (! $employee->profile_photo_path) {
            return null;
        }

        $url = Storage::disk('public')->url($employee->profile_photo_path);
        $version = $employee->updated_at?->getTimestamp() ?? time();

        return $url.(str_contains($url, '?') ? '&' : '?').'v='.$version;
    }
}
