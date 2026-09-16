<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaharashtraTaluka extends Model
{
    protected $fillable = [
        'district_id',
        'name',
        'code',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(MaharashtraDistrict::class, 'district_id');
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class, 'taluka_id');
    }
}
