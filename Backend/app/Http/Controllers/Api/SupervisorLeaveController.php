<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use App\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupervisorLeaveController extends Controller
{
    public function __construct(
        private readonly LeaveService $leaves,
        private readonly OrganizationAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->access->leaveQuery($request->user())
            ->with($this->leaves->relations());

        if ($request->user()->isAdminOrDirector()) {
            $this->access->constrainToProjectHeadLeaves($query);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('leave_type')) {
            $query->where('leave_type', $request->string('leave_type'));
        }
        if ($request->filled('scheme_id')) {
            $query->where('scheme_id', $request->integer('scheme_id'));
        }
        if ($request->filled('project_id')) {
            $query->where('scheme_id', $request->integer('project_id'));
        }
        if ($request->filled('center_id')) {
            $query->where('center_id', $request->integer('center_id'));
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('to_date', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('from_date', '<=', $request->date('date_to'));
        }
        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->whereHas('employee', fn ($inner) => $inner->where('full_name', 'like', $search));
        }

        $items = $query->latest('from_date')
            ->limit(200)
            ->get()
            ->map(fn (LeaveRequest $leave) => $leave->toApiArray())
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Leave requests loaded.',
            'data' => $items,
        ]);
    }

    public function show(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->access->assertCanViewLeave($request->user(), $leave);

        return response()->json([
            'success' => true,
            'message' => 'Leave loaded.',
            'data' => $leave->fresh($this->leaves->relations())->toApiArray(),
        ]);
    }

    public function approve(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->access->assertCanApproveLeave($request->user(), $leave);
        $validated = $request->validate([
            'approval_remark' => ['nullable', 'string', 'max:1000'],
        ]);
        $leave = $this->leaves->approve($leave, $request->user(), $validated['approval_remark'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Leave approved.',
            'data' => $leave->toApiArray(),
        ]);
    }

    public function reject(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->access->assertCanApproveLeave($request->user(), $leave);
        $validated = $request->validate([
            'rejection_remark' => ['required', 'string', 'max:1000'],
        ]);
        $leave = $this->leaves->reject($leave, $request->user(), $validated['rejection_remark']);

        return response()->json([
            'success' => true,
            'message' => 'Leave rejected.',
            'data' => $leave->toApiArray(),
        ]);
    }

    public function downloadDocument(Request $request, LeaveRequest $leave): StreamedResponse
    {
        $this->access->assertCanViewLeave($request->user(), $leave);

        return EmployeeLeaveController::streamDocument($leave);
    }
}
