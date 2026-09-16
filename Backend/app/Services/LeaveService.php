<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Attendance\AttendanceStatusCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LeaveService
{
    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return ['employee', 'scheme', 'center', 'reviewedBy'];
    }

    public function create(User $user, array $payload, ?UploadedFile $file = null): LeaveRequest
    {
        $employee = $this->requireEmployee($user);
        $center = $employee->center;
        if ($center === null || $center->scheme_id === null) {
            throw ValidationException::withMessages([
                'center' => 'Employee is not assigned to a center.',
            ]);
        }

        $from = Carbon::parse($payload['from_date'])->startOfDay();
        $to = Carbon::parse($payload['to_date'])->startOfDay();
        $this->assertDateRange($from, $to);
        $this->assertNoOverlap($employee->id, $from, $to);

        $leave = LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'center_id' => $center->id,
            'scheme_id' => $center->scheme_id,
            'created_by_user_id' => $user->id,
            'leave_type' => LeaveType::from($payload['leave_type']),
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'total_days' => LeaveRequest::inclusiveDays($from, $to),
            'reason' => $payload['reason'],
            'status' => LeaveStatus::Pending,
        ]);

        if ($file) {
            $this->storeDocument($leave, $file);
        }

        return $leave->fresh($this->relations());
    }

    public function updatePending(LeaveRequest $leave, array $payload, ?UploadedFile $file = null): LeaveRequest
    {
        $this->assertPending($leave);

        $from = Carbon::parse($payload['from_date'] ?? $leave->from_date)->startOfDay();
        $to = Carbon::parse($payload['to_date'] ?? $leave->to_date)->startOfDay();
        $this->assertDateRange($from, $to);
        $this->assertNoOverlap($leave->employee_id, $from, $to, $leave->id);

        $leave->fill([
            'leave_type' => isset($payload['leave_type'])
                ? LeaveType::from($payload['leave_type'])
                : $leave->leave_type,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'total_days' => LeaveRequest::inclusiveDays($from, $to),
            'reason' => $payload['reason'] ?? $leave->reason,
        ]);
        $leave->save();

        if ($file) {
            $this->storeDocument($leave, $file);
        }

        return $leave->fresh($this->relations());
    }

    public function cancelPending(LeaveRequest $leave): void
    {
        $this->assertPending($leave);
        $leave->status = LeaveStatus::Cancelled;
        $leave->save();
    }

    public function approve(LeaveRequest $leave, User $reviewer, ?string $remark = null): LeaveRequest
    {
        return DB::transaction(function () use ($leave, $reviewer, $remark): LeaveRequest {
            /** @var LeaveRequest $locked */
            $locked = LeaveRequest::query()->whereKey($leave->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);

            $locked->status = LeaveStatus::Approved;
            $locked->approval_remark = filled($remark) ? $remark : null;
            $locked->rejection_remark = null;
            $locked->reviewed_by_user_id = $reviewer->id;
            $locked->reviewed_at = now();
            $locked->save();

            $this->markAttendanceOnLeave($locked);

            return $locked->fresh($this->relations());
        });
    }

    public function reject(LeaveRequest $leave, User $reviewer, string $remark): LeaveRequest
    {
        if (! filled($remark)) {
            throw ValidationException::withMessages([
                'rejection_remark' => 'Rejection remark is required.',
            ]);
        }

        return DB::transaction(function () use ($leave, $reviewer, $remark): LeaveRequest {
            /** @var LeaveRequest $locked */
            $locked = LeaveRequest::query()->whereKey($leave->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($locked);

            $locked->status = LeaveStatus::Rejected;
            $locked->rejection_remark = $remark;
            $locked->approval_remark = null;
            $locked->reviewed_by_user_id = $reviewer->id;
            $locked->reviewed_at = now();
            $locked->save();

            return $locked->fresh($this->relations());
        });
    }

    public function storeDocument(LeaveRequest $leave, UploadedFile $file): LeaveRequest
    {
        $this->assertPending($leave);
        $leave->deleteStoredDocument();

        $storageKey = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = sprintf('leaves/%d/%s.%s', $leave->id, $storageKey, $extension);
        Storage::disk('local')->put($path, $file->getContent());

        $leave->fill([
            'document_storage_key' => $storageKey,
            'document_disk' => 'local',
            'document_path' => $path,
            'document_original_name' => $file->getClientOriginalName() ?: 'document.'.$extension,
            'document_mime_type' => $file->getMimeType(),
            'document_size' => (int) $file->getSize(),
        ]);
        $leave->save();

        return $leave->fresh($this->relations());
    }

    public function removeDocument(LeaveRequest $leave): LeaveRequest
    {
        $this->assertPending($leave);
        $leave->deleteStoredDocument();
        $leave->fill([
            'document_storage_key' => null,
            'document_disk' => null,
            'document_path' => null,
            'document_original_name' => null,
            'document_mime_type' => null,
            'document_size' => null,
        ]);
        $leave->save();

        return $leave->fresh($this->relations());
    }

    private function markAttendanceOnLeave(LeaveRequest $leave): void
    {
        $from = Carbon::parse($leave->from_date)->startOfDay();
        $to = Carbon::parse($leave->to_date)->startOfDay();

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $attendance = Attendance::query()->firstOrNew([
                'employee_id' => $leave->employee_id,
                'attendance_date' => $date->toDateString(),
            ]);

            $attendance->attendance_status = AttendanceStatusCalculator::STATUS_LEAVE;
            if (blank($attendance->approval_status)) {
                $attendance->approval_status = 'Approved';
            }
            if (blank($attendance->remarks)) {
                $attendance->remarks = 'On Leave';
            }
            $attendance->save();
        }
    }

    private function assertPending(LeaveRequest $leave): void
    {
        if (! $leave->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending leave can be changed.',
            ]);
        }
    }

    private function assertDateRange(Carbon $from, Carbon $to): void
    {
        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'to_date' => 'To date cannot be before from date.',
            ]);
        }
    }

    private function assertNoOverlap(int $employeeId, Carbon $from, Carbon $to, ?int $ignoreId = null): void
    {
        $query = LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])
            ->whereDate('from_date', '<=', $to->toDateString())
            ->whereDate('to_date', '>=', $from->toDateString());

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'from_date' => 'This leave overlaps an existing pending or approved request.',
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
}
