<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaveType;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeLeaveController extends Controller
{
    public function __construct(
        private readonly LeaveService $leaves,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->ownQuery($request)
            ->latest('from_date')
            ->get()
            ->map(fn (LeaveRequest $leave) => $leave->toApiArray())
            ->values();

        return $this->ok('Leave requests loaded.', $items);
    }

    public function show(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->assertOwn($request, $leave);

        return $this->ok('Leave loaded.', $leave->fresh($this->leaves->relations())->toApiArray());
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validated($request, false);
        $leave = $this->leaves->create($request->user(), $payload, $request->file('document'));

        return $this->ok('Leave applied.', $leave->toApiArray(), 201);
    }

    public function update(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->assertOwn($request, $leave);
        $payload = $this->validated($request, true);
        $leave = $this->leaves->updatePending($leave, $payload, $request->file('document'));

        return $this->ok('Leave updated.', $leave->toApiArray());
    }

    public function destroy(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->assertOwn($request, $leave);
        $this->leaves->cancelPending($leave);

        return $this->ok('Leave cancelled.');
    }

    public function uploadDocument(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->assertOwn($request, $leave);
        $request->validate([
            'document' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:8192'],
        ]);
        $leave = $this->leaves->storeDocument($leave, $request->file('document'));

        return $this->ok('Document uploaded.', $leave->toApiArray());
    }

    public function removeDocument(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->assertOwn($request, $leave);
        $leave = $this->leaves->removeDocument($leave);

        return $this->ok('Document removed.', $leave->toApiArray());
    }

    public function downloadDocument(Request $request, LeaveRequest $leave): StreamedResponse
    {
        $this->assertOwn($request, $leave);

        return self::streamDocument($leave);
    }

    public static function streamDocument(LeaveRequest $leave): StreamedResponse
    {
        abort_unless($leave->hasDocument(), 404, 'No supporting document.');
        $disk = $leave->document_disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($leave->document_path), 404, 'Document file is missing.');

        return Storage::disk($disk)->response(
            $leave->document_path,
            $leave->document_original_name ?: 'leave-document',
            ['Content-Type' => $leave->document_mime_type ?: 'application/octet-stream'],
        );
    }

    private function ownQuery(Request $request)
    {
        $employeeId = $request->user()?->employee_id;
        abort_unless($employeeId, 403, 'Employee profile is not linked to this account.');

        return LeaveRequest::query()
            ->with($this->leaves->relations())
            ->where('employee_id', $employeeId);
    }

    private function assertOwn(Request $request, LeaveRequest $leave): void
    {
        $user = $request->user();
        abort_unless($user && (int) $leave->employee_id === (int) $user->employee_id, 403, 'You can only access your own leave requests.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'leave_type' => [$required, Rule::in(array_column(LeaveType::cases(), 'value'))],
            'from_date' => [$required, 'date'],
            'to_date' => [$required, 'date', 'after_or_equal:from_date'],
            'reason' => [$required, 'string', 'max:1000'],
            'document' => ['sometimes', 'nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:8192'],
        ]);
    }

    private function ok(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => $status < 400,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
