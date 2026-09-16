<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
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

    public function directors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'director_project_assignments')
            ->withTimestamps();
    }

    public function projectHeads(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_head_assignments')
            ->withTimestamps();
    }

    public function centers(): HasMany
    {
        return $this->hasMany(Center::class);
    }

    public function displayLabel(): string
    {
        return filled($this->code) ? "{$this->code} — {$this->name}" : $this->name;
    }
}
