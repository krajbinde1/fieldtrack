<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseSelfTravelDay extends Model
{
    protected $fillable = [
        'user_id',
        'travel_date',
        'expense_claim_id',
    ];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'expense_claim_id');
    }
}
