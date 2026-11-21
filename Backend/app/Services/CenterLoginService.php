<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Center;
use App\Models\User;
use App\Support\CenterLoginIdGenerator;
use App\Support\LoginId;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CenterLoginService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, mixed>  $state
     * @return array<string, mixed>
     */
    public function applyManagerSelection(array $data, array $state): array
    {
        $eligible = $this->eligibleManagerIds($this->selectedIds($state));

        if (count($eligible) <= 1) {
            $data['primary_center_manager_id'] = $eligible[0] ?? null;

            return $data;
        }

        $primary = (int) ($data['primary_center_manager_id'] ?? 0);
        if (! in_array($primary, $eligible, true)) {
            throw ValidationException::withMessages([
                'primary_center_manager_id' => 'Select one active primary Center Manager. A center with multiple Center Managers needs a single active primary before the center login can be created or reset.',
            ]);
        }

        $data['primary_center_manager_id'] = $primary;

        return $data;
    }

    /**
     * @param  array<int, mixed>  $state
     * @return list<int|string>
     */
    public function selectedIds(array $state): array
    {
        $selected = $state['centerManagers'] ?? [];

        return is_array($selected) ? array_values($selected) : [];
    }

    /**
     * @param  list<int|string>  $selected
     * @return list<int>
     */
    public function eligibleManagerIds(array $selected): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $selected))));
        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->where('role', UserRole::CenterManager->value)
            ->where('is_active', true)
            ->where('is_center_login', false)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function syncAccount(Center $center): ?User
    {
        $center->refresh();
        $this->ensureLoginId($center);

        $account = $center->loginUser;
        if ($account !== null) {
            $account->forceFill([
                'name' => $center->name,
                'is_active' => (bool) $center->is_active,
            ])->save();

            return $account;
        }

        try {
            $manager = $this->requirePasswordManager($center);
        } catch (ValidationException) {
            return null;
        }

        return $this->createAccount($center, $manager);
    }

    public function resetPassword(Center $center): void
    {
        $center->refresh();
        $this->ensureLoginId($center);
        $manager = $this->requirePasswordManager($center);
        $account = $center->fresh()?->loginUser;

        if ($account === null) {
            $this->createAccount($center, $manager);

            return;
        }

        $account->forceFill([
            'name' => $center->name,
            'password' => $this->passwordFromManager($manager),
            'is_active' => (bool) $center->is_active,
        ])->save();
    }

    public function backfill(): void
    {
        Center::query()->orderBy('id')->each(function (Center $center): void {
            if (blank($center->login_id)) {
                $center->forceFill([
                    'login_id' => CenterLoginIdGenerator::unique((string) $center->name, (int) $center->id),
                ])->save();
            }

            $this->syncAccount($center);
        });
    }

    public function requirePasswordManager(Center $center): User
    {
        $manager = $this->resolvePrimary($center);
        if ($manager === null) {
            throw ValidationException::withMessages([
                'primary_center_manager_id' => 'Select one active primary Center Manager before creating or resetting the center login. Inactive managers cannot be the primary.',
            ]);
        }

        $this->passwordFromManager($manager);

        return $manager;
    }

    private function ensureLoginId(Center $center): void
    {
        if (filled($center->login_id)) {
            return;
        }

        $center->forceFill([
            'login_id' => CenterLoginIdGenerator::unique((string) $center->name, (int) $center->id),
        ])->save();
    }

    private function resolvePrimary(Center $center): ?User
    {
        $center->loadMissing('primaryCenterManager');
        $primary = $center->primaryCenterManager;

        if ($primary !== null) {
            return $this->isEligiblePrimary($center, $primary) ? $primary : null;
        }

        $active = $this->assignedActiveManagers($center);
        if ($active->count() !== 1) {
            return null;
        }

        /** @var User $manager */
        $manager = $active->first();
        $center->forceFill(['primary_center_manager_id' => $manager->id])->save();

        return $manager;
    }

    private function isEligiblePrimary(Center $center, User $manager): bool
    {
        return $manager->is_active === true
            && $manager->is_center_login !== true
            && $manager->role === UserRole::CenterManager->value
            && $center->centerManagers()->whereKey($manager->id)->exists();
    }

    /**
     * @return Collection<int, User>
     */
    private function assignedActiveManagers(Center $center): Collection
    {
        return $center->centerManagers()
            ->where('users.role', UserRole::CenterManager->value)
            ->where('users.is_active', true)
            ->where('users.is_center_login', false)
            ->orderBy('users.id')
            ->get();
    }

    private function createAccount(Center $center, User $manager): User
    {
        $loginId = (string) $center->login_id;
        LoginId::assertUnique($loginId);

        $user = User::query()->create([
            'name' => $center->name,
            'email' => $this->emailFor($center, $loginId),
            'login_id' => $loginId,
            'password' => $this->passwordFromManager($manager),
            'role' => UserRole::CenterManager->value,
            'is_active' => (bool) $center->is_active,
            'must_change_password' => false,
            'is_center_login' => true,
        ]);

        $center->forceFill(['login_user_id' => $user->id])->save();

        return $user;
    }

    private function passwordFromManager(User $manager): string
    {
        try {
            return LoginId::defaultPasswordFromMobile((string) $manager->login_id);
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'primary_center_manager_id' => 'The primary Center Manager must have a mobile number with at least 4 digits before the center login can be created or reset.',
            ]);
        }
    }

    private function emailFor(Center $center, string $loginId): string
    {
        $local = substr($loginId, 0, 160);
        $email = $local.'@centers.fieldtrack.local';
        if (! User::query()->where('email', $email)->exists()) {
            return $email;
        }

        return $local.'.'.$center->id.'@centers.fieldtrack.local';
    }
}
