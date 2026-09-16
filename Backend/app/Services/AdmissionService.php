<?php

namespace App\Services;

use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionStatus;
use App\Models\Admission;
use App\Models\AdmissionDocument;
use App\Models\Employee;
use App\Models\MaharashtraTaluka;
use App\Models\Scheme;
use App\Models\User;
use App\Support\AdmissionLookups;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AdmissionService
{
    public function createDraft(User $user, array $payload = []): Admission
    {
        $employee = $this->requireEmployee($user);
        $center = $employee->center;

        if ($center === null || $center->scheme_id === null) {
            throw ValidationException::withMessages([
                'center' => 'Employee is not assigned to a center.',
            ]);
        }

        $admission = Admission::query()->create(array_merge(
            $this->sanitizeDraftPayload($payload),
            [
                'employee_id' => $employee->id,
                'center_id' => $center->id,
                'created_by_user_id' => $user->id,
                'status' => AdmissionStatus::Draft,
                'state' => AdmissionLookups::STATE_MAHARASHTRA,
                'current_step' => (int) ($payload['current_step'] ?? 1),
            ],
        ));

        return $admission->fresh($this->relations());
    }

    public function updateDraft(Admission $admission, array $payload): Admission
    {
        $this->assertEditable($admission);

        $data = $this->sanitizeDraftPayload($payload);
        if (isset($payload['current_step'])) {
            $data['current_step'] = max(1, min(5, (int) $payload['current_step']));
        }

        $admission->fill($data);
        $admission->save();

        return $admission->fresh($this->relations());
    }

    public function submit(Admission $admission): Admission
    {
        return DB::transaction(function () use ($admission): Admission {
            /** @var Admission $locked */
            $locked = Admission::query()
                ->whereKey($admission->id)
                ->lockForUpdate()
                ->with(['documents', 'scheme', 'taluka'])
                ->firstOrFail();

            if ($locked->isSubmitted()) {
                throw ValidationException::withMessages([
                    'status' => 'Admission already submitted.',
                ]);
            }
            if ($locked->isConfirmed()) {
                throw ValidationException::withMessages([
                    'status' => 'Confirmed admissions cannot be submitted again.',
                ]);
            }
            if ($locked->isRejected()) {
                throw ValidationException::withMessages([
                    'status' => 'Rejected admissions cannot be submitted again.',
                ]);
            }
            if (! $locked->isDraft() && ! $locked->isReverted()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft or reverted admissions can be submitted.',
                ]);
            }

            $this->assertReadyToSubmit($locked);

            $locked->status = AdmissionStatus::Submitted;
            $locked->current_step = 5;
            $locked->submitted_at = now();
            $locked->review_reason = null;
            $locked->reviewed_by_user_id = null;
            $locked->reviewed_at = null;
            $locked->confirmed_at = null;
            $locked->save();

            return $locked->fresh($this->relations());
        });
    }

    public function deleteDraft(Admission $admission): void
    {
        $this->assertDraft($admission);

        foreach ($admission->documents as $document) {
            $document->deleteStoredFile();
        }

        $admission->delete();
    }

    public function storeDocument(Admission $admission, AdmissionDocumentType $type, UploadedFile $file): AdmissionDocument
    {
        $this->assertEditable($admission);

        return DB::transaction(function () use ($admission, $type, $file): AdmissionDocument {
            $existing = AdmissionDocument::query()
                ->where('admission_id', $admission->id)
                ->where('document_type', $type->value)
                ->first();

            $storageKey = (string) Str::uuid();
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
            $path = sprintf('admissions/%d/%s.%s', $admission->id, $storageKey, $extension);

            Storage::disk('local')->put($path, $file->getContent());

            if ($existing) {
                $existing->deleteStoredFile();
                $existing->fill([
                    'storage_key' => $storageKey,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName() ?: $type->value.'.'.$extension,
                    'mime_type' => $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                ]);
                $existing->save();

                return $existing->fresh();
            }

            return AdmissionDocument::query()->create([
                'admission_id' => $admission->id,
                'document_type' => $type->value,
                'storage_key' => $storageKey,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName() ?: $type->value.'.'.$extension,
                'mime_type' => $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ]);
        });
    }

    public function removeDocument(Admission $admission, AdmissionDocument $document): void
    {
        $this->assertEditable($admission);

        if ((int) $document->admission_id !== (int) $admission->id) {
            abort(404);
        }

        $document->deleteStoredFile();
        $document->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizeDraftPayload(array $payload): array
    {
        $data = [];

        foreach ([
            'scheme_id',
            'first_name',
            'middle_name',
            'last_name',
            'gender',
            'religion',
            'caste',
            'district_id',
            'taluka_id',
            'village',
        ] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field] === '' ? null : $payload[$field];
            }
        }

        if (array_key_exists('scheme_id', $data) && $data['scheme_id'] !== null) {
            $scheme = Scheme::query()->find($data['scheme_id']);
            if ($scheme === null) {
                throw ValidationException::withMessages(['scheme_id' => 'Selected scheme is invalid.']);
            }
        }

        if (array_key_exists('taluka_id', $data) || array_key_exists('district_id', $data)) {
            $districtId = $data['district_id'] ?? null;
            $talukaId = $data['taluka_id'] ?? null;

            if ($talukaId) {
                $taluka = MaharashtraTaluka::query()->find($talukaId);
                if ($taluka === null) {
                    throw ValidationException::withMessages(['taluka_id' => 'Selected taluka is invalid.']);
                }

                $expectedDistrict = $districtId ?? $taluka->district_id;
                if ((int) $taluka->district_id !== (int) $expectedDistrict) {
                    throw ValidationException::withMessages([
                        'taluka_id' => 'Taluka does not belong to the selected district.',
                    ]);
                }

                $data['district_id'] = $taluka->district_id;
            }
        }

        $data['state'] = AdmissionLookups::STATE_MAHARASHTRA;

        return $data;
    }

    public function assertDraft(Admission $admission): void
    {
        if (! $admission->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Submitted admissions cannot be edited.',
            ]);
        }
    }

    public function assertEditable(Admission $admission): void
    {
        if (! $admission->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'Submitted admissions cannot be edited.',
            ]);
        }
    }

    public function confirm(Admission $admission, User $reviewer): Admission
    {
        return $this->review($admission, $reviewer, AdmissionStatus::Confirmed, null);
    }

    public function revert(Admission $admission, User $reviewer, string $reason): Admission
    {
        if (! filled($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Revert reason is required.',
            ]);
        }

        return $this->review($admission, $reviewer, AdmissionStatus::Reverted, $reason);
    }

    public function reject(Admission $admission, User $reviewer, string $reason): Admission
    {
        if (! filled($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'Reject reason is required.',
            ]);
        }

        return $this->review($admission, $reviewer, AdmissionStatus::Rejected, $reason);
    }

    private function review(
        Admission $admission,
        User $reviewer,
        AdmissionStatus $status,
        ?string $reason,
    ): Admission {
        return DB::transaction(function () use ($admission, $reviewer, $status, $reason): Admission {
            /** @var Admission $locked */
            $locked = Admission::query()->whereKey($admission->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isSubmitted()) {
                throw ValidationException::withMessages([
                    'status' => 'Only submitted admissions can be reviewed.',
                ]);
            }

            $locked->status = $status;
            $locked->review_reason = $reason;
            $locked->reviewed_by_user_id = $reviewer->id;
            $locked->reviewed_at = now();
            $locked->confirmed_at = $status === AdmissionStatus::Confirmed ? now() : null;
            $locked->save();

            return $locked->fresh($this->relations());
        });
    }

    public function assertReadyToSubmit(Admission $admission): void
    {
        if ($admission->scheme_id) {
            $scheme = $admission->scheme ?? Scheme::query()->find($admission->scheme_id);
            if ($scheme === null || ! $scheme->is_active) {
                throw ValidationException::withMessages([
                    'scheme_id' => 'Selected scheme is not active.',
                ]);
            }
        }

        if ($admission->taluka_id && $admission->district_id) {
            $taluka = $admission->taluka ?? MaharashtraTaluka::query()->find($admission->taluka_id);
            if ($taluka === null || (int) $taluka->district_id !== (int) $admission->district_id) {
                throw ValidationException::withMessages([
                    'taluka_id' => 'Taluka does not belong to the selected district.',
                ]);
            }
        }

        $missing = $admission->missingSubmitFields();
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'admission' => 'Please complete required fields before submitting: '.implode(', ', $missing).'.',
            ]);
        }
    }

    private function requireEmployee(User $user): Employee
    {
        $employee = $user->employee;

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee' => 'Employee profile is not linked to this account.',
            ]);
        }

        $employee->loadMissing('center');

        return $employee;
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return [
            'scheme',
            'center',
            'employee',
            'district',
            'taluka',
            'documents',
        ];
    }
}
