<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Center;
use App\Models\User;
use App\Support\LoginId;
use App\Support\UserIdentity;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CenterManagerAccountService
{
    public static function credentialMessage(string $loginId, string $password): string
    {
        return 'Center Manager created. Login ID: '.$loginId.'. Temporary password: '.$password.'.';
    }

    public static function credentialNotification(string $loginId, string $password): Notification
    {
        return Notification::make()
            ->title(self::credentialMessage($loginId, $password))
            ->success()
            ->persistent();
    }

    /**
     * Create or reuse a Center Manager and assign them to the coordinator's centers.
     * Each selected center keeps a single manager. An existing manager on that center
     * is replaced for that center only.
     *
     * @param  array<string, mixed>  $data
     * @return array{user: User, created: bool, login_id: string, password: ?string}
     */
    public function provisionForCoordinator(User $actor, array $data): array
    {
        abort_unless($actor->isCenterCoordinator(), 403);

        $centerIds = $this->normalizeIds($data['center_ids'] ?? []);
        $this->assertCentersInScope($actor, $centerIds, 'center_ids');

        $existingId = (int) ($data['existing_manager_id'] ?? 0);
        if ($existingId > 0) {
            $manager = User::query()->find($existingId);
            if (! $manager instanceof User || ! $this->isAssignableManager($manager)) {
                throw ValidationException::withMessages([
                    'existing_manager_id' => 'Select an active Center Manager.',
                ]);
            }

            $this->installOnCenters($actor, $manager, $centerIds);

            return [
                'user' => $manager->fresh(),
                'created' => false,
                'login_id' => (string) $manager->login_id,
                'password' => null,
            ];
        }

        return $this->createAndInstall($actor, $data, $centerIds);
    }

    /**
     * Add this manager to the given centers without removing their other centers.
     *
     * @param  list<int>  $centerIds
     */
    public function installOnCenters(User $actor, User $manager, array $centerIds): void
    {
        abort_unless($actor->isCenterCoordinator(), 403);
        abort_unless($this->isAssignableManager($manager), 403);

        $centerIds = $this->normalizeIds($centerIds);
        $this->assertCentersInScope($actor, $centerIds, 'center_ids');
        $this->applyCenterManagers($manager, $centerIds, []);
    }

    /**
     * Replace the manager's assignments inside the coordinator's centers.
     * Centers outside that scope are left unchanged.
     *
     * @param  list<int|string>  $selectedCenterIds
     */
    public function syncVisibleAssignments(User $actor, User $manager, array $selectedCenterIds): void
    {
        abort_unless($actor->isCenterCoordinator(), 403);
        abort_unless($manager->isCenterManager() && ! $manager->isCenterLogin(), 403);

        $visible = $this->visibleCenterIds($actor);
        $selected = $this->normalizeIds($selectedCenterIds);

        if (array_diff($selected, $visible) !== []) {
            throw ValidationException::withMessages([
                'managedCenters' => 'You can only assign a Center Manager to your centers.',
            ]);
        }

        $managed = $manager->managedCenters()
            ->pluck('centers.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (array_diff($managed, $visible) !== []) {
            abort(403);
        }

        if ($selected === []) {
            throw ValidationException::withMessages([
                'managedCenters' => 'Select at least one center.',
            ]);
        }

        $remove = array_values(array_diff(
            array_values(array_intersect($managed, $visible)),
            $selected,
        ));

        $this->applyCenterManagers($manager, $selected, $remove);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $centerIds
     * @return array{user: User, created: bool, login_id: string, password: ?string}
     */
    private function createAndInstall(User $actor, array $data, array $centerIds): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $mobile = trim((string) ($data['mobile'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Name is required.',
            ]);
        }

        if (! preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
            throw ValidationException::withMessages([
                'mobile' => 'Enter a valid 10-digit mobile number.',
            ]);
        }

        if ($email === '') {
            $email = $mobile.'@fieldtrack.local';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'Enter a valid email address.',
            ]);
        }

        UserIdentity::assertAvailable($name, $mobile, allowManagerReuse: true, attribute: 'mobile');

        $reusable = UserIdentity::userByLogin($mobile);
        if ($reusable instanceof User
            && UserIdentity::nameKey($reusable->name) === UserIdentity::nameKey($name)
            && $this->isAssignableManager($reusable)
        ) {
            $this->installOnCenters($actor, $reusable, $centerIds);

            return [
                'user' => $reusable->fresh(),
                'created' => false,
                'login_id' => (string) $reusable->login_id,
                'password' => null,
            ];
        }

        $emailOwner = User::query()->where('email', $email)->first();
        if ($emailOwner instanceof User) {
            throw ValidationException::withMessages([
                'email' => 'This email is already taken.',
            ]);
        }

        $password = LoginId::defaultPasswordFromMobile($mobile);

        $manager = DB::transaction(function () use ($actor, $name, $email, $mobile, $password, $centerIds): User {
            try {
                $created = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'login_id' => $mobile,
                    'password' => Hash::make($password),
                    'role' => UserRole::CenterManager->value,
                    'is_active' => true,
                    'is_center_login' => false,
                    'must_change_password' => true,
                ]);
            } catch (QueryException) {
                throw ValidationException::withMessages([
                    'mobile' => 'This Login ID is already taken.',
                ]);
            }

            $this->installOnCenters($actor, $created, $centerIds);

            return $created;
        });

        return [
            'user' => $manager->fresh(),
            'created' => true,
            'login_id' => $mobile,
            'password' => $password,
        ];
    }

    /**
     * @param  list<int>  $installIds
     * @param  list<int>  $removeIds
     */
    private function applyCenterManagers(User $manager, array $installIds, array $removeIds): void
    {
        $installIds = array_values(array_unique(array_map(fn ($id): int => (int) $id, $installIds)));
        $removeIds = array_values(array_unique(array_map(fn ($id): int => (int) $id, $removeIds)));
        $all = array_values(array_unique(array_merge($installIds, $removeIds)));
        sort($all);

        DB::transaction(function () use ($manager, $installIds, $removeIds, $all): void {
            $centers = [];
            foreach ($all as $centerId) {
                $center = Center::query()->whereKey($centerId)->lockForUpdate()->first();
                if ($center instanceof Center) {
                    $centers[$centerId] = $center;
                }
            }

            foreach ($removeIds as $centerId) {
                $center = $centers[$centerId] ?? null;
                if (! $center instanceof Center) {
                    continue;
                }

                $center->centerManagers()->detach($manager->id);
                if ((int) $center->primary_center_manager_id === (int) $manager->id) {
                    $center->forceFill(['primary_center_manager_id' => null])->save();
                }
            }

            foreach ($installIds as $centerId) {
                $center = $centers[$centerId] ?? null;
                if (! $center instanceof Center) {
                    throw ValidationException::withMessages([
                        'center_ids' => 'You can only assign a Center Manager to your centers.',
                    ]);
                }

                $center->centerManagers()->sync([(int) $manager->id]);
                $center->forceFill(['primary_center_manager_id' => $manager->id])->save();
            }
        });
    }

    /**
     * @param  list<int>  $centerIds
     */
    private function assertCentersInScope(User $actor, array $centerIds, string $errorKey): void
    {
        $visible = $this->visibleCenterIds($actor);
        $found = $centerIds === []
            ? []
            : Center::query()->whereIn('id', $centerIds)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($centerIds === [] || array_diff($centerIds, $visible) !== [] || array_diff($centerIds, $found) !== []) {
            throw ValidationException::withMessages([
                $errorKey => 'You can only assign a Center Manager to your centers.',
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function visibleCenterIds(User $actor): array
    {
        return app(OrganizationAccessService::class)->visibleCenterIds($actor) ?? [];
    }

    /**
     * @return list<int>
     */
    private function normalizeIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $normalized = [];
        foreach ($ids as $id) {
            if (is_string($id) && ! ctype_digit($id)) {
                continue;
            }

            $id = (int) $id;
            if ($id > 0) {
                $normalized[] = $id;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function isAssignableManager(User $user): bool
    {
        return $user->isCenterManager()
            && ! $user->isCenterLogin()
            && $user->is_active === true;
    }
}
