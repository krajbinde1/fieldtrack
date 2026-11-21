<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Scheme extends Model
{
    public const DELETE_BLOCKED_MESSAGE = 'This scheme cannot be deleted because it is used by existing records.';

    public const DELETED_MESSAGE = 'Scheme deleted.';

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

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function admissionTargets(): HasMany
    {
        return $this->hasMany(AdmissionTarget::class);
    }

    public function fieldActivities(): HasMany
    {
        return $this->hasMany(FieldActivity::class);
    }

    public function hasRelatedRecords(): bool
    {
        foreach (['centers', 'admissions', 'leaveRequests', 'admissionTargets', 'fieldActivities'] as $relation) {
            if ($this->{$relation}()->exists()) {
                return true;
            }
        }

        return $this->hasOtherSchemeReferences();
    }

    /**
     * Deletes this scheme row only when every related table is empty.
     * Related rows are never removed.
     */
    public function deleteIfUnused(): bool
    {
        if ($this->getKey() === null) {
            return false;
        }

        try {
            return DB::transaction(function (): bool {
                $scheme = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

                if (! $scheme instanceof self) {
                    return true;
                }

                if ($scheme->hasRelatedRecords()) {
                    return false;
                }

                return (bool) $scheme->delete();
            });
        } catch (QueryException) {
            return false;
        }
    }

    /**
     * Foreign keys and scheme_id columns beyond the relationships declared above.
     * A failed lookup blocks deletion so an unknown reference cannot be cascaded away.
     */
    private function hasOtherSchemeReferences(): bool
    {
        $covered = [
            'centers',
            'admissions',
            'leave_requests',
            'admission_targets',
            'field_activities',
            'schemes',
        ];

        try {
            $tables = Schema::getTableListing(schemaQualified: false);
        } catch (Throwable) {
            return true;
        }

        foreach ($tables as $table) {
            if (in_array($table, $covered, true)) {
                continue;
            }

            try {
                if (Schema::hasColumn($table, 'scheme_id') && DB::table($table)->where('scheme_id', $this->getKey())->exists()) {
                    return true;
                }

                foreach (Schema::getForeignKeys($table) as $foreignKey) {
                    if (($foreignKey['foreign_table'] ?? null) !== 'schemes') {
                        continue;
                    }

                    foreach ($foreignKey['columns'] ?? [] as $column) {
                        if ($column === 'scheme_id') {
                            continue;
                        }

                        if (DB::table($table)->where($column, $this->getKey())->exists()) {
                            return true;
                        }
                    }
                }
            } catch (Throwable) {
                return true;
            }
        }

        return false;
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
