<?php

namespace App\Models;

use App\Enums\ExpenseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ExpenseItem extends Model
{
    protected $fillable = [
        'expense_claim_id',
        'expense_date',
        'type',
        'amount',
        'description',
        'receipt_disk',
        'receipt_path',
        'receipt_original_name',
        'receipt_mime',
        'travel_from',
        'travel_to',
        'travel_km',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'type' => ExpenseType::class,
            'amount' => 'decimal:2',
            'travel_km' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(ExpenseClaim::class, 'expense_claim_id');
    }

    public function hasReceipt(): bool
    {
        return filled($this->receipt_path) && filled($this->receipt_disk);
    }

    public function deleteStoredReceipt(): void
    {
        if (! $this->hasReceipt()) {
            return;
        }

        Storage::disk($this->receipt_disk)->delete($this->receipt_path);
    }
}
