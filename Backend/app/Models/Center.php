<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Center extends Model
{
    protected $fillable = [
        'scheme_id',
        'name',
        'code',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    public function centerManagers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'center_manager_assignments')
            ->withTimestamps();
    }

    public function projectHeads(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_head_center_assignments')
            ->withTimestamps();
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function displayLabel(): string
    {
        return filled($this->code) ? "{$this->code} — {$this->name}" : $this->name;
    }

    public function assignmentLabel(): string
    {
        $schemeName = $this->scheme?->name ?: 'Scheme';

        return $schemeName.' — '.$this->name;
    }
}
