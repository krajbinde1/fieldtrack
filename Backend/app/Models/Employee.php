<?php

namespace App\Models;

use App\Support\EmployeeCodeGenerator;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Employee $employee): void {
            if (filled($employee->employee_code)) {
                return;
            }

            $employee->employee_code = EmployeeCodeGenerator::generateNext();
        });

        static::updating(function (Employee $employee): void {
            if (
                $employee->isDirty('employee_code')
                && filled($employee->getOriginal('employee_code'))
            ) {
                $employee->employee_code = $employee->getOriginal('employee_code');
            }
        });
    }

    public static function uniqueAmongActive(string $column, ?int $ignoreId = null): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique('employees', $column)->whereNull('deleted_at');

        if ($ignoreId !== null) {
            $rule->ignore($ignoreId);
        }

        return $rule;
    }

    protected $fillable = [
        'center_id',
        'full_name',
        'mobile',
        'email',
        'department',
        'designation',
        'joining_date',
        'base_location',
        'profile_photo_path',
        'status',
        'salary',
        'daily_allowance',
        'travel_allowance',
        'aadhaar_number',
        'pan_number',
        'bank_name',
        'account_number',
        'ifsc_code',
        'reporting_manager_id',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'salary' => 'decimal:2',
            'daily_allowance' => 'decimal:2',
            'travel_allowance' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reporting_manager_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function routePoints(): HasMany
    {
        return $this->hasMany(EmployeeRoutePoint::class);
    }

    public function displayLabel(): string
    {
        if (filled($this->employee_code)) {
            return "{$this->employee_code} — {$this->full_name}";
        }

        return $this->full_name;
    }

    public function assignmentLabel(): string
    {
        return $this->displayLabel();
    }

    public function project(): ?Project
    {
        return $this->center?->project;
    }
}
