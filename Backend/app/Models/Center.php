<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Center extends Model
{
    protected $fillable = [
        'project_id',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function centerManagers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'center_manager_assignments')
            ->withTimestamps();
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function displayLabel(): string
    {
        return filled($this->code) ? "{$this->code} — {$this->name}" : $this->name;
    }
}
