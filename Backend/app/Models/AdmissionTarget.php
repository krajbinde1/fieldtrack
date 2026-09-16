<?php

namespace App\Models;

use App\Enums\AdmissionTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionTarget extends Model
{
    protected $fillable = [
        'employee_id',
        'center_id',
        'project_id',
        'assigned_by_user_id',
        'parent_id',
        'target_type',
        'period_start',
        'period_end',
        'target_count',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => AdmissionTargetType::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'target_count' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function weeks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isMonthly(): bool
    {
        return $this->target_type === AdmissionTargetType::Monthly;
    }

    public function isWeekly(): bool
    {
        return $this->target_type === AdmissionTargetType::Weekly;
    }

    public function isChildWeek(): bool
    {
        return $this->isWeekly() && $this->parent_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $this->loadMissing(['employee:id,full_name,employee_code', 'center:id,name', 'project:id,name', 'weeks']);

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'center_id' => $this->center_id,
            'project_id' => $this->project_id,
            'parent_id' => $this->parent_id,
            'target_type' => $this->target_type?->value,
            'target_type_label' => $this->target_type?->label(),
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'target_count' => $this->target_count,
            'employee' => $this->employee ? [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'employee_code' => $this->employee->employee_code,
            ] : null,
            'center' => $this->center ? ['id' => $this->center->id, 'name' => $this->center->name] : null,
            'project' => $this->project ? ['id' => $this->project->id, 'name' => $this->project->name] : null,
            'weekly_splits' => $this->weeks->map(fn (self $week) => [
                'id' => $week->id,
                'period_start' => $week->period_start?->toDateString(),
                'period_end' => $week->period_end?->toDateString(),
                'target_count' => $week->target_count,
            ])->values()->all(),
        ];
    }
}
