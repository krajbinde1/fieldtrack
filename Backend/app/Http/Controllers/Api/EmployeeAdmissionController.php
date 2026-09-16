<?php

namespace App\Http\Controllers\Api;

use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionDocument;
use App\Services\AdmissionService;
use App\Services\OrganizationAccessService;
use App\Support\AdmissionLookups;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeAdmissionController extends Controller
{
    public function __construct(
        private readonly AdmissionService $admissions,
        private readonly OrganizationAccessService $access,
    ) {}

    public function drafts(Request $request): JsonResponse
    {
        $items = $this->ownQuery($request)
            ->where('status', AdmissionStatus::Draft)
            ->latest('updated_at')
            ->get()
            ->map(fn (Admission $admission) => $admission->toApiArray())
            ->values();

        return $this->ok('Draft admissions loaded.', $items);
    }

    public function submitted(Request $request): JsonResponse
    {
        $items = $this->ownQuery($request)
            ->where('status', AdmissionStatus::Submitted)
            ->latest('submitted_at')
            ->get()
            ->map(fn (Admission $admission) => $admission->toApiArray())
            ->values();

        return $this->ok('Submitted admissions loaded.', $items);
    }

    public function show(Request $request, Admission $admission): JsonResponse
    {
        $this->assertOwn($request, $admission);

        return $this->ok('Admission loaded.', $admission->fresh($this->admissions->relations())->toApiArray());
    }

    public function storeDraft(Request $request): JsonResponse
    {
        $payload = $this->validatedDraft($request, false);
        $admission = $this->admissions->createDraft($request->user(), $payload);

        return $this->ok('Draft saved.', $admission->toApiArray(), 201);
    }

    public function updateDraft(Request $request, Admission $admission): JsonResponse
    {
        $this->assertOwn($request, $admission);
        $payload = $this->validatedDraft($request, true);
        $admission = $this->admissions->updateDraft($admission, $payload);

        return $this->ok('Draft updated.', $admission->toApiArray());
    }

    public function destroyDraft(Request $request, Admission $admission): JsonResponse
    {
        $this->assertOwn($request, $admission);
        $this->admissions->deleteDraft($admission);

        return $this->ok('Draft deleted.');
    }

    public function submit(Request $request, Admission $admission): JsonResponse
    {
        $this->assertOwn($request, $admission);
        $admission = $this->admissions->submit($admission);

        return $this->ok('Admission submitted successfully.', $admission->toApiArray());
    }

    public function uploadDocument(Request $request, Admission $admission): JsonResponse
    {
        $this->assertOwn($request, $admission);

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(array_column(AdmissionDocumentType::cases(), 'value'))],
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:8192'],
        ]);

        $document = $this->admissions->storeDocument(
            $admission,
            AdmissionDocumentType::from($validated['document_type']),
            $request->file('file'),
        );

        return $this->ok('Document uploaded.', [
            'document' => $document->toApiArray(),
            'admission' => $admission->fresh($this->admissions->relations())->toApiArray(),
        ]);
    }

    public function removeDocument(Request $request, Admission $admission, AdmissionDocument $document): JsonResponse
    {
        $this->assertOwn($request, $admission);
        $this->admissions->removeDocument($admission, $document);

        return $this->ok('Document removed.', $admission->fresh($this->admissions->relations())->toApiArray());
    }

    public function downloadDocument(Request $request, Admission $admission, AdmissionDocument $document): StreamedResponse
    {
        $this->assertOwn($request, $admission);

        abort_unless((int) $document->admission_id === (int) $admission->id, 404);

        return $this->streamDocument($document);
    }

    public static function streamDocument(AdmissionDocument $document): StreamedResponse
    {
        $disk = $document->disk ?: 'local';
        abort_unless(\Illuminate\Support\Facades\Storage::disk($disk)->exists($document->path), 404, 'Document file is missing.');

        return \Illuminate\Support\Facades\Storage::disk($disk)->response(
            $document->path,
            $document->original_name,
            [
                'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            ],
        );
    }

    private function ownQuery(Request $request)
    {
        $employeeId = $request->user()?->employee_id;
        abort_unless($employeeId, 403, 'Employee profile is not linked to this account.');

        return Admission::query()
            ->with($this->admissions->relations())
            ->where('employee_id', $employeeId);
    }

    private function assertOwn(Request $request, Admission $admission): void
    {
        $user = $request->user();
        abort_unless($user && $this->access->canViewAdmission($user, $admission), 403, 'You are not authorized to view this admission.');
        abort_unless((int) $admission->employee_id === (int) $user->employee_id, 403, 'You can only access your own admissions.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedDraft(Request $request, bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'nullable';

        return $request->validate([
            'scheme_id' => [$required, 'nullable', 'integer', 'exists:schemes,id'],
            'first_name' => [$required, 'nullable', 'string', 'max:100'],
            'middle_name' => [$required, 'nullable', 'string', 'max:100'],
            'last_name' => [$required, 'nullable', 'string', 'max:100'],
            'gender' => [$required, 'nullable', 'string', Rule::in(AdmissionLookups::genders())],
            'religion' => [$required, 'nullable', 'string', Rule::in(AdmissionLookups::religions())],
            'caste' => [$required, 'nullable', 'string', Rule::in(AdmissionLookups::castes())],
            'district_id' => [$required, 'nullable', 'integer', 'exists:maharashtra_districts,id'],
            'taluka_id' => [$required, 'nullable', 'integer', 'exists:maharashtra_talukas,id'],
            'village' => [$required, 'nullable', 'string', 'max:150'],
            'current_step' => ['sometimes', 'integer', 'min:1', 'max:5'],
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
