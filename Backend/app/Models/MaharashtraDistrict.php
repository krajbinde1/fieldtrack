<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaharashtraDistrict extends Model
{
    protected $fillable = [
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

    public function talukas(): HasMany
    {
        return $this->hasMany(MaharashtraTaluka::class, 'district_id');
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class, 'district_id');
    }
}
