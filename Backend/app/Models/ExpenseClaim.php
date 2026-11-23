<?php

namespace App\Models;

use App\Enums\ExpenseClaimStatus;
use App\Enums\ExpensePaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExpenseClaim extends Model
{
    protected $fillable = [
        'employee_id',
        'center_id',
        'scheme_id',
        'created_by_user_id',
        'claim_number',
        'status',
        'payment_status',
        'current_level',
        'blocked_level',
        'designated_approver_id',
        'total_amount',
        'submitted_at',
        'final_approved_at',
        'rejected_at',
        'rejection_remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExpenseClaimStatus::class,
            'payment_status' => ExpensePaymentStatus::class,
            'current_level' => 'integer',
            'blocked_level' => 'integer',
            'total_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'final_approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function designatedApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designated_approver_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ExpenseApproval::class)->orderBy('level');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(ExpensePayment::class);
    }

    public function claimNumber(): string
    {
        return $this->claim_number;
    }

    public function displayStatus(): string
    {
        if ($this->payment_status === ExpensePaymentStatus::Paid) {
            return 'Paid';
        }

        if ($this->status === ExpenseClaimStatus::Approved && $this->payment_status === ExpensePaymentStatus::Unpaid) {
            return 'Approved / Pending Payment';
        }

        $level = $this->pendingApprovalLevel();
        if ($level !== null && $this->status !== ExpenseClaimStatus::NeedsResolution) {
            return match ($level) {
                1 => 'Pending Center Manager',
                2 => 'Pending Center Coordinator',
                3 => 'Pending Director',
                4 => 'Pending Admin',
                default => $this->status->label(),
            };
        }

        return $this->status->label();
    }

    /**
     * The stage that can still be acted on. A stored approval closes that stage
     * even when the status column has not caught up.
     */
    public function pendingApprovalLevel(): ?int
    {
        if ($this->status === ExpenseClaimStatus::NeedsResolution) {
            return $this->blocked_level !== null
                ? (int) $this->blocked_level
                : ((int) $this->current_level > 0 ? (int) $this->current_level : null);
        }

        if (! $this->status instanceof ExpenseClaimStatus || ! $this->status->isPendingApproval()) {
            return null;
        }

        $this->loadMissing(['approvals', 'createdBy']);
        $start = $this->createdBy?->isCenterManager() === true ? 2 : 1;
        $approved = [];
        foreach ($this->approvals as $approval) {
            if ($approval->action === 'approved') {
                $approved[(int) $approval->level] = true;
            }
        }

        for ($level = $start; $level <= 4; $level++) {
            if (! isset($approved[$level])) {
                return $level;
            }
        }

        return null;
    }
}
