<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\AdmissionTarget;
use App\Services\AdmissionTargetService;
use App\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerAdmissionTargetController extends Controller
{
    public function __construct(
        private readonly AdmissionTargetService $targets,
        private readonly OrganizationAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->targets->listAssigned($request->user(), $request->only([
            'scheme_id',
            'project_id',
            'center_id',
            'employee_id',
            'target_type',
        ]))->map(fn (AdmissionTarget $target) => $target->toApiArray())->values();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isCenterManager() === true, 403);

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'target_type' => ['required', 'string'],
            'target_count' => ['required', 'integer', 'min:0'],
            'period' => ['required', 'date'],
        ]);

        $target = $this->targets->assign($user, $data);

        return response()->json([
            'success' => true,
            'data' => $target->toApiArray(),
        ], 201);
    }

    public function show(Request $request, AdmissionTarget $admissionTarget): JsonResponse
    {
        $this->access->assertCanViewAdmissionTarget(
            $request->user(),
            $admissionTarget->loadMissing('employee'),
        );

        return response()->json([
            'success' => true,
            'data' => $admissionTarget->toApiArray(),
        ]);
    }
}
