<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scheme extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function centers(): HasMany
    {
        return $this->hasMany(Center::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function displayLabel(): string
    {
        return filled($this->code) ? "{$this->code} — {$this->name}" : $this->name;
    }
}
