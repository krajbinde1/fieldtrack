<?php

namespace App\Models;

use App\Casts\ExpenseItemTypeCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ExpenseItem extends Model
{
    protected $fillable = [
        'expense_claim_id',
        'expense_date',
        'type',
        'travel_mode',
        'amount',
        'description',
        'receipt_disk',
        'receipt_path',
        'receipt_original_name',
        'receipt_mime',
        'travel_from',
        'travel_to',
        'travel_km',
        'recorded_km',
        'eligible_km',
        'km_rate',
        'monthly_km_used',
        'monthly_km_limit',
        'travel_from_latitude',
        'travel_from_longitude',
        'travel_to_latitude',
        'travel_to_longitude',
        'travel_from_source',
        'travel_to_source',
        'gps_from_address',
        'gps_to_address',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'type' => ExpenseItemTypeCast::class,
            'amount' => 'decimal:2',
            'travel_km' => 'decimal:2',
            'recorded_km' => 'decimal:2',
            'eligible_km' => 'decimal:2',
            'km_rate' => 'decimal:2',
            'monthly_km_used' => 'decimal:2',
            'monthly_km_limit' => 'decimal:2',
            'travel_from_latitude' => 'decimal:7',
            'travel_from_longitude' => 'decimal:7',
            'travel_to_latitude' => 'decimal:7',
            'travel_to_longitude' => 'decimal:7',
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
