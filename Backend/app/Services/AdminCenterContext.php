<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\AdmissionTarget;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\Scheme;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Session-backed center chosen by an Admin in the Filament panel.
 * Other roles ignore this context and keep their existing scope.
 */
final class AdminCenterContext
{
    public const SESSION_KEY = 'admin_selected_center_id';

    private const ORGANIZATION_WIDE_DEPTH = 'admin_center_organization_wide_depth';

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function hasValidSelection(?User $user): bool
    {
        return $this->idFor($user) !== null;
    }

    public function beginOrganizationWideScope(): void
    {
        request()->attributes->set(self::ORGANIZATION_WIDE_DEPTH, $this->organizationWideDepth() + 1);
    }

    public function endOrganizationWideScope(): void
    {
        request()->attributes->set(
            self::ORGANIZATION_WIDE_DEPTH,
            max(0, $this->organizationWideDepth() - 1),
        );
    }

    private function organizationWideDepth(): int
    {
        return (int) request()->attributes->get(self::ORGANIZATION_WIDE_DEPTH, 0);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function duringOrganizationWideScope(callable $callback): mixed
    {
        $this->beginOrganizationWideScope();

        try {
            return $callback();
        } finally {
            $this->endOrganizationWideScope();
        }
    }

    public function idFor(?User $user): ?int
    {
        if ($this->organizationWideDepth() > 0) {
            return null;
        }

        if (! $user?->isAdmin()) {
            return null;
        }

        $id = $this->rawId();
        if ($id === null) {
            return null;
        }

        $allowed = app(OrganizationAccessService::class)
            ->centerQuery($user)
            ->whereKey($id)
            ->exists();

        if (! $allowed) {
            $this->forget();

            return null;
        }

        return $id;
    }

    public function centerFor(?User $user): ?Center
    {
        $id = $this->idFor($user);

        return $id === null ? null : Center::query()->find($id);
    }

    /**
     * Center id passed to workforce summaries.
     * Null keeps organization-wide results for the main admin dashboard.
     */
    public function workforceCenterId(?User $user): ?int
    {
        if (! $user?->isAdmin()) {
            return null;
        }

        return $this->idFor($user);
    }

    public function select(User $user, int $centerId): Center
    {
        abort_unless($user->isAdmin(), 403);

        $center = app(OrganizationAccessService::class)
            ->centerQuery($user)
            ->whereKey($centerId)
            ->first();

        if (! $center instanceof Center) {
            throw ValidationException::withMessages([
                'data.center_id' => 'You are not authorized to use this center.',
            ]);
        }

        session()->put(self::SESSION_KEY, (int) $center->id);

        return $center;
    }

    public function scopeEmployees(Builder $query, ?User $user = null): Builder
    {
        return $this->whenAdminCenter($query, $user, function (Builder $query, int $id): Builder {
            $table = $query->getModel()->getTable();

            return $query->where($table.'.center_id', $id);
        });
    }

    public function scopeCenters(Builder $query, ?User $user = null): Builder
    {
        return $this->whenAdminCenter($query, $user, fn (Builder $query, int $id): Builder => $query->whereKey($id));
    }

    public function scopeSchemes(Builder $query, ?User $user = null): Builder
    {
        return $this->whenAdminCenter($query, $user, function (Builder $query, int $id): Builder {
            $schemeId = Center::query()->whereKey($id)->value('scheme_id');

            return $schemeId === null
                ? $query->whereRaw('1 = 0')
                : $query->whereKey($schemeId);
        });
    }

    public function scopeByCenterId(Builder $query, ?User $user = null): Builder
    {
        return $this->whenAdminCenter($query, $user, function (Builder $query, int $id): Builder {
            $table = $query->getModel()->getTable();

            return $query->where($table.'.center_id', $id);
        });
    }

    public function scopeByEmployeeCenter(Builder $query, ?User $user = null): Builder
    {
        return $this->whenAdminCenter($query, $user, fn (Builder $query, int $id): Builder => $query->whereHas(
            'employee',
            fn (Builder $employee) => $employee->where('center_id', $id),
        ));
    }

    /**
     * Records stored against a center, including rows reached through the employee.
     */
    public function scopeOwnedByCenter(Builder $query, ?User $user = null): Builder
    {
        return $this->whenAdminCenter($query, $user, function (Builder $query, int $id): Builder {
            $table = $query->getModel()->getTable();

            return $query->where(function (Builder $inner) use ($id, $table): void {
                $inner->where($table.'.center_id', $id)
                    ->orWhereHas('employee', fn (Builder $employee) => $employee->where('center_id', $id));
            });
        });
    }

    public function applyToResourceQuery(Builder $query, ?User $user, string $model): Builder
    {
        if (! $user?->isAdmin()) {
            return $query;
        }

        // Center management stays organization-wide. Schemes and projects are not center records.
        if (in_array($model, [Center::class, Scheme::class, Project::class], true)) {
            return $query;
        }

        return match ($model) {
            Admission::class, Employee::class => $this->scopeByCenterId($query, $user),
            Attendance::class => $this->scopeByEmployeeCenter($query, $user),
            LeaveRequest::class, AdmissionTarget::class => $this->scopeOwnedByCenter($query, $user),
            default => $query,
        };
    }

    public function abortUnlessEmployeeInAdminCenter(Employee $employee, ?User $user = null): void
    {
        $user ??= auth()->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            return;
        }

        $centerId = $this->idFor($user);

        if ($centerId === null) {
            return;
        }

        abort_unless((int) $employee->center_id === $centerId, 404);
    }

    private function rawId(): ?int
    {
        if (! session()->has(self::SESSION_KEY)) {
            return null;
        }

        $raw = session()->get(self::SESSION_KEY);
        if (! is_numeric($raw) || (int) $raw < 1) {
            $this->forget();

            return null;
        }

        return (int) $raw;
    }

    /**
     * @param  callable(Builder, int): Builder  $apply
     */
    private function whenAdminCenter(Builder $query, ?User $user, callable $apply): Builder
    {
        $user ??= auth()->user();

        if (! $user instanceof User || ! $user->isAdmin()) {
            return $query;
        }

        $id = $this->idFor($user);
        if ($id === null) {
            return $query;
        }

        return $apply($query, $id);
    }
}
