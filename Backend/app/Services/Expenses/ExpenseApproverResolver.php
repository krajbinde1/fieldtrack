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
        $managers = $this->activeAssigned($center->centerManagers(), UserRole::CenterManager);
        $center->loadMissing('primaryCenterManager');
        $primary = $center->primaryCenterManager;
        if (
            $primary instanceof User
            && $primary->is_active === true
            && $primary->isCenterManager()
            && ! $primary->isCenterLogin()
            && ! $managers->contains(fn (User $user): bool => (int) $user->id === (int) $primary->id)
        ) {
            $managers->push($primary);
        }

        return $managers
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
     * Directors assigned to this center. Directors of other centers are not approvers.
     *
     * @return Collection<int, User>
     */
    public function directors(Center $center, int $claimantUserId): Collection
    {
        return $this->activeAssigned($center->directors(), UserRole::Director)
            ->reject(fn (User $user): bool => (int) $user->id === $claimantUserId)
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function admins(int $claimantUserId): Collection
    {
        return $this->activeRole(UserRole::Admin, $claimantUserId);
    }

    /**
     * @return Collection<int, User>
     */
    private function activeRole(UserRole $role, int $claimantUserId): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where('role', $role->value)
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
