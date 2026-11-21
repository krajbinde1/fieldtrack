<?php

namespace App\Models;

use App\Support\CenterCodeGenerator;
use App\Support\CenterLoginIdGenerator;
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
        'login_id',
        'primary_center_manager_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Center $center): void {
            if (blank($center->code)) {
                $center->code = CenterCodeGenerator::generateNext();
            }

            if (blank($center->login_id)) {
                $center->login_id = CenterLoginIdGenerator::unique((string) $center->name);
            }
        });

        static::updating(function (Center $center): void {
            if ($center->isDirty('code') && filled($center->getOriginal('code'))) {
                $center->code = $center->getOriginal('code');
            }

            if ($center->isDirty('login_id') && filled($center->getOriginal('login_id'))) {
                $center->login_id = $center->getOriginal('login_id');
            }
        });
    }

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

    public function loginUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'login_user_id');
    }

    public function primaryCenterManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_center_manager_id');
    }

    public function projectHeads(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_head_center_assignments')
            ->withTimestamps();
    }

    public function coordinators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'center_coordinator_assignments')
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
