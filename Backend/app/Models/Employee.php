<?php

namespace App\Models;

use App\Enums\CenterStaffRole;
use App\Support\EmployeeCodeGenerator;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Laravel\Sanctum\HasApiTokens;
use Throwable;

class Employee extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    public const DELETE_BLOCKED_MESSAGE = 'Cannot delete this user because related records exist.';

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

    public static function uniqueAmongActive(string $column, ?int $ignoreId = null): Unique
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
        'travel_km_rate',
        'monthly_km_limit',
        'aadhaar_number',
        'pan_number',
        'bank_name',
        'account_number',
        'ifsc_code',
        'reporting_manager_id',
        'created_by_user_id',
        'staff_role',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'salary' => 'decimal:2',
            'daily_allowance' => 'decimal:2',
            'travel_allowance' => 'decimal:2',
            'travel_km_rate' => 'decimal:2',
            'monthly_km_limit' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function staffRoleEnum(): CenterStaffRole
    {
        return CenterStaffRole::tryFromMixed($this->staff_role);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function assignedCenters(): BelongsToMany
    {
        return $this->belongsToMany(Center::class, 'employee_center_assignments')->withTimestamps();
    }

    /**
     * @return list<int>
     */
    public function assignedCenterIds(): array
    {
        $ids = $this->relationLoaded('assignedCenters')
            ? $this->assignedCenters->pluck('id')
            : $this->assignedCenters()->pluck('centers.id');

        return $ids
            ->push($this->center_id)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Center>
     */
    public function assignedCenterRecords(): Collection
    {
        $centers = $this->relationLoaded('assignedCenters')
            ? $this->assignedCenters
            : $this->assignedCenters()->get();

        if ($this->center !== null) {
            $centers = $centers->push($this->center);
        }

        return $centers->unique('id')->sortBy('name')->values();
    }

    /**
     * @return list<string>
     */
    public function assignedCenterNames(): array
    {
        return $this->assignedCenterRecords()
            ->pluck('name')
            ->filter(fn ($name): bool => filled($name))
            ->values()
            ->all();
    }

    public function isAssignedToCenter(int $centerId): bool
    {
        return in_array($centerId, $this->assignedCenterIds(), true);
    }

    public function assignCenter(int $centerId): void
    {
        $this->assignedCenters()->syncWithoutDetaching([$centerId]);

        if ($this->center_id === null) {
            $this->center_id = $centerId;
            $this->save();
        }
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

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function fieldActivities(): HasMany
    {
        return $this->hasMany(FieldActivity::class);
    }

    public function admissionTargets(): HasMany
    {
        return $this->hasMany(AdmissionTarget::class);
    }

    /**
     * True when any other row references this employee or their login.
     * The login row itself is the account being removed, not a dependency.
     * Attendance includes soft-deleted rows so a database cascade cannot remove them.
     */
    public function hasRelatedRecords(): bool
    {
        if ($this->getKey() === null) {
            return true;
        }

        $accountId = $this->user()->value('id');

        return $this->hasBlockingReferences(
            (int) $this->getKey(),
            $accountId !== null ? (int) $accountId : null,
        );
    }

    /**
     * Permanently removes this employee and their login only when nothing references them.
     * Related rows are never deleted. A database error is treated as a blocked delete.
     */
    public function deleteIfUnused(): bool
    {
        if ($this->getKey() === null) {
            return false;
        }

        try {
            return DB::transaction(function (): bool {
                $employee = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

                if (! $employee instanceof self) {
                    return true;
                }

                if ($employee->hasRelatedRecords()) {
                    return false;
                }

                $account = $employee->user()->first();

                if ($employee->forceDelete() !== true) {
                    return false;
                }

                if ($account !== null && $account->delete() !== true) {
                    throw new QueryException(
                        DB::getDefaultConnection(),
                        'delete from users',
                        [],
                        new \PDOException('User account could not be deleted.'),
                    );
                }

                return true;
            });
        } catch (QueryException) {
            return false;
        }
    }

    private function hasBlockingReferences(int $employeeId, ?int $accountId): bool
    {
        try {
            $tables = Schema::getTableListing(schemaQualified: false);
        } catch (Throwable) {
            return true;
        }

        foreach ($tables as $table) {
            try {
                $foreignKeys = Schema::getForeignKeys($table);
            } catch (Throwable) {
                return true;
            }

            foreach ($foreignKeys as $foreignKey) {
                $foreignTable = $foreignKey['foreign_table'] ?? null;
                $columns = $foreignKey['columns'] ?? [];

                if (! is_array($columns)) {
                    return true;
                }

                if ($foreignTable === 'employees' && $this->foreignKeyIsUsed($table, $columns, $employeeId, $employeeId, $accountId, 'employee')) {
                    return true;
                }

                if ($accountId !== null && $foreignTable === 'users' && $this->foreignKeyIsUsed($table, $columns, $accountId, $employeeId, $accountId, 'user')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $columns
     */
    private function foreignKeyIsUsed(string $table, array $columns, int $referencedId, int $employeeId, ?int $accountId, string $target): bool
    {
        foreach ($columns as $column) {
            if (! is_string($column) || $column === '') {
                return true;
            }

            if ($target === 'employee' && $table === 'employees' && $column === 'id') {
                continue;
            }

            if ($target === 'user' && $table === 'users') {
                continue;
            }

            $query = DB::table($table)->where($column, $referencedId);

            if ($table === 'employees') {
                $query->where('id', '!=', $employeeId);
            }

            if ($target === 'employee' && $table === 'users' && $column === 'employee_id' && $accountId !== null) {
                $query->where('id', '!=', $accountId);
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
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

    public function scheme(): ?Scheme
    {
        return $this->center?->scheme;
    }
}
