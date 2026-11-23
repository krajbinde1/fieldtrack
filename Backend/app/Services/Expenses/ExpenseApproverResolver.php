<?php

namespace App\Services\Expenses;

use App\Enums\UserRole;
use App\Models\Center;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves one approver per level from the center's real assignments.
 * Several coordinators do not each have to approve: the active coordinator
 * with the lowest user id is the single designated approver.
 * A missing approver is never filled in automatically.
 */
final class ExpenseApproverResolver
{
    public function manager(Center $center, int $claimantUserId): ?User
    {
        $managers = $this->activeAssigned($center->centerManagers(), UserRole::CenterManager);

        $primaryId = $center->primary_center_manager_id;
        if ($primaryId) {
            $primary = $managers->firstWhere('id', (int) $primaryId);
            if ($primary instanceof User && (int) $primary->id !== $claimantUserId) {
                return $primary;
            }
        }

        return $managers->first(fn (User $user): bool => (int) $user->id !== $claimantUserId);
    }

    public function coordinator(Center $center, int $claimantUserId): ?User
    {
        return $this->activeAssigned($center->coordinators(), UserRole::CenterCoordinator)
            ->first(fn (User $user): bool => (int) $user->id !== $claimantUserId);
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
