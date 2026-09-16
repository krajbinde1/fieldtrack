<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class LeaveRequest extends Model
{
    protected $fillable = [
        'employee_id',
        'scheme_id',
        'center_id',
        'created_by_user_id',
        'leave_type',
        'from_date',
        'to_date',
        'total_days',
        'reason',
        'status',
        'approval_remark',
        'rejection_remark',
        'reviewed_by_user_id',
        'reviewed_at',
        'document_storage_key',
        'document_disk',
        'document_path',
        'document_original_name',
        'document_mime_type',
        'document_size',
    ];

    protected function casts(): array
    {
        return [
            'leave_type' => LeaveType::class,
            'status' => LeaveStatus::class,
            'from_date' => 'date',
            'to_date' => 'date',
            'total_days' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === LeaveStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === LeaveStatus::Approved;
    }

    public function hasDocument(): bool
    {
        return filled($this->document_path) && filled($this->document_storage_key);
    }

    public static function inclusiveDays(Carbon|string $from, Carbon|string $to): int
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        return (int) $start->diffInDays($end) + 1;
    }

    public function deleteStoredDocument(): void
    {
        if (! $this->hasDocument()) {
            return;
        }

        $disk = $this->document_disk ?: 'local';
        if (Storage::disk($disk)->exists($this->document_path)) {
            Storage::disk($disk)->delete($this->document_path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $this->loadMissing([
            'employee:id,full_name,employee_code,center_id',
            'scheme:id,name,code',
            'center:id,name,code,scheme_id',
            'reviewedBy:id,name',
        ]);

        return [
            'id' => $this->id,
            'leave_type' => $this->leave_type?->value,
            'leave_type_label' => $this->leave_type?->label(),
            'from_date' => $this->from_date?->toDateString(),
            'to_date' => $this->to_date?->toDateString(),
            'total_days' => $this->total_days,
            'reason' => $this->reason,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'approval_remark' => $this->approval_remark,
            'rejection_remark' => $this->rejection_remark,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'reviewed_by' => $this->reviewedBy?->name,
            'editable' => $this->isPending(),
            'cancellable' => $this->isPending(),
            'project' => $this->scheme ? [
                'id' => $this->scheme->id,
                'name' => $this->scheme->name,
            ] : null,
            'center' => $this->center ? [
                'id' => $this->center->id,
                'name' => $this->center->name,
            ] : null,
            'employee' => $this->employee ? [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_code' => $this->employee->employee_code,
            ] : null,
            'document' => $this->hasDocument() ? [
                'original_name' => $this->document_original_name,
                'mime_type' => $this->document_mime_type,
                'size' => $this->document_size,
                'uploaded' => true,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
