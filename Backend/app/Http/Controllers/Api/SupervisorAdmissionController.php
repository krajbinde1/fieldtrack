<?php

namespace App\Http\Controllers\Api;

use App\Enums\AdmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionDocument;
use App\Services\AdmissionService;
use App\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupervisorAdmissionController extends Controller
{
    public function __construct(
        private readonly AdmissionService $admissions,
        private readonly OrganizationAccessService $access,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->access->admissionQuery($request->user())
            ->with(['scheme', 'center', 'employee', 'district', 'taluka', 'documents']);

        if ($request->filled('status')) {
            $status = AdmissionStatus::tryFrom((string) $request->input('status'));
            if ($status !== null) {
                $query->where('status', $status);
            }
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
        if ($request->filled('district_id')) {
            $query->where('district_id', $request->integer('district_id'));
        }
        if ($request->filled('taluka_id')) {
            $query->where('taluka_id', $request->integer('taluka_id'));
        }
        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(function ($inner) use ($search) {
                $inner->where('full_name', 'like', $search)
                    ->orWhere('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search);
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }
        if ($request->filled('confirmed_from')) {
            $query->whereDate('confirmed_at', '>=', $request->date('confirmed_from'));
        }
        if ($request->filled('confirmed_to')) {
            $query->whereDate('confirmed_at', '<=', $request->date('confirmed_to'));
        }

        $order = (string) $request->input('sort', '');
        $items = ($order === 'confirmed_at'
            ? $query->orderByDesc('confirmed_at')
            : $query->latest('updated_at'))
            ->limit(200)
            ->get()
            ->map(fn (Admission $admission) => $admission->toApiArray($request->user()))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Admissions loaded.',
            'data' => $items,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Admission summary loaded.',
            'data' => $this->access->admissionStatusCounts(
                $request->user(),
                $this->access->requestedCenterId($request),
            ),
        ]);
    }

    public function confirmedByCenter(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdminOrDirector() || $user->isProjectHead(), 403);

        $query = $this->access->admissionQuery($user)
            ->where('status', AdmissionStatus::Confirmed);

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
        if ($request->filled('confirmed_from')) {
            $query->whereDate('confirmed_at', '>=', $request->date('confirmed_from'));
        }
        if ($request->filled('confirmed_to')) {
            $query->whereDate('confirmed_at', '<=', $request->date('confirmed_to'));
        }

        $counts = (clone $query)
            ->toBase()
            ->selectRaw('center_id, COUNT(*) as total_confirmed')
            ->groupBy('center_id')
            ->orderByDesc('total_confirmed')
            ->get();

        $centers = $this->access->centerQuery($user)
            ->with('scheme:id,name')
            ->whereIn('id', $counts->pluck('center_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $rows = $counts->map(function ($row) use ($centers): array {
            $center = $centers->get((int) $row->center_id);

            return [
                'center_id' => (int) $row->center_id,
                'center_name' => $center?->name,
                'scheme_id' => $center?->scheme_id,
                'scheme_name' => $center?->scheme?->name,
                'project_name' => $center?->scheme?->name,
                'total_confirmed' => (int) $row->total_confirmed,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Confirmed admissions by center loaded.',
            'data' => $rows,
            'meta' => [
                'total_confirmed' => $rows->sum('total_confirmed'),
            ],
        ]);
    }

    public function show(Request $request, Admission $admission): JsonResponse
    {
        $this->access->assertCanViewAdmission($request->user(), $admission);

        return response()->json([
            'success' => true,
            'message' => 'Admission loaded.',
            'data' => $admission->fresh([
                'scheme', 'center', 'employee', 'district', 'taluka', 'documents',
            ])->toApiArray($request->user()),
        ]);
    }

    public function confirm(Request $request, Admission $admission): JsonResponse
    {
        $this->access->assertCanReviewAdmission($request->user(), $admission);
        $admission = $this->admissions->confirm($admission, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Admission confirmed.',
            'data' => $admission->toApiArray($request->user()),
        ]);
    }

    public function revert(Request $request, Admission $admission): JsonResponse
    {
        $this->access->assertCanReviewAdmission($request->user(), $admission);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $admission = $this->admissions->revert($admission, $request->user(), $validated['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Admission reverted.',
            'data' => $admission->toApiArray($request->user()),
        ]);
    }

    public function reject(Request $request, Admission $admission): JsonResponse
    {
        $this->access->assertCanReviewAdmission($request->user(), $admission);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $admission = $this->admissions->reject($admission, $request->user(), $validated['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Admission rejected.',
            'data' => $admission->toApiArray($request->user()),
        ]);
    }

    public function downloadDocument(Request $request, Admission $admission, AdmissionDocument $document): StreamedResponse
    {
        $this->access->assertCanViewAdmission($request->user(), $admission);
        abort_unless((int) $document->admission_id === (int) $admission->id, 404);

        return EmployeeAdmissionController::streamDocument($document);
    }
}
