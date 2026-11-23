<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseApproval extends Model
{
    protected $fillable = [
        'expense_claim_id',
        'level',
        'action',
        'user_id',
        'approver_name',
        'role',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'expense_claim_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
