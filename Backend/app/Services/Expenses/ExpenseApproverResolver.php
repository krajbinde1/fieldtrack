<?php

namespace App\Services\Expenses;

use App\Enums\UserRole;
use App\Models\Center;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves approvers from the center's real assignments.
 * Every assigned center manager or coordinator may act, except the claimant.
 * Only one decision is stored for each level.
 * A missing approver is never filled in automatically.
 */
final class ExpenseApproverResolver
{
    /**
     * @return Collection<int, User>
     */
    public function managers(Center $center, int $claimantUserId): Collection
    {
        return $this->activeAssigned($center->centerManagers(), UserRole::CenterManager)
            ->reject(fn (User $user): bool => (int) $user->id === $claimantUserId)
            ->values();
    }

    public function manager(Center $center, int $claimantUserId): ?User
    {
        $managers = $this->managers($center, $claimantUserId);
        $primaryId = $center->primary_center_manager_id;
        if ($primaryId) {
            $primary = $managers->firstWhere('id', (int) $primaryId);
            if ($primary instanceof User) {
                return $primary;
            }
        }

        return $managers->first();
    }

    /**
     * @return Collection<int, User>
     */
    public function coordinators(Center $center, int $claimantUserId): Collection
    {
        return $this->activeAssigned($center->coordinators(), UserRole::CenterCoordinator)
            ->reject(fn (User $user): bool => (int) $user->id === $claimantUserId)
            ->values();
    }

    public function coordinator(Center $center, int $claimantUserId): ?User
    {
        return $this->coordinators($center, $claimantUserId)->first();
    }

    /**
     * @return Collection<int, User>
     */
    public function finalApprovers(int $claimantUserId): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Director->value])
            ->where('id', '!=', $claimantUserId)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function activeAssigned($relation, UserRole $role): Collection
    {
        return $relation
            ->where('users.is_active', true)
            ->where('users.role', $role->value)
            ->orderBy('users.id')
            ->get();
    }
}
