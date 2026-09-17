<?php

namespace App\Http\Controllers\Api;

use App\Enums\FieldActivityType;
use App\Http\Controllers\Controller;
use App\Models\FieldActivity;
use App\Services\FieldActivityService;
use App\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorFieldActivityController extends Controller
{
    public function __construct(
        private readonly FieldActivityService $activities,
        private readonly OrganizationAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $this->access->fieldActivityQuery($user)
            ->with($this->activities->relations());
        $this->activities->applyFilters($query, $request, $user);

        $items = $query->latest('activity_at')
            ->limit(200)
            ->get()
            ->map(fn (FieldActivity $activity) => $activity->toApiArray())
            ->values();

        $employees = $this->access->employeeQuery($user)
            ->where('status', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name'])
            ->map(fn ($employee) => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Field activities loaded.',
            'data' => $items,
            'meta' => [
                'employees' => $employees,
                'types' => FieldActivityType::options(),
                'period' => $request->string('period')->toString() ?: 'today',
            ],
        ]);
    }

    public function show(Request $request, FieldActivity $fieldActivity): JsonResponse
    {
        $this->access->assertCanViewFieldActivity($request->user(), $fieldActivity);

        return response()->json([
            'success' => true,
            'message' => 'Field activity loaded.',
            'data' => $fieldActivity->loadMissing($this->activities->relations())->toApiArray(),
        ]);
    }
}
