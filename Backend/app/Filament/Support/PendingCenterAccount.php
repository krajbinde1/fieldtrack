<?php

namespace App\Filament\Support;

use App\Enums\UserRole;
use App\Filament\Concerns\HoldsPendingCenterAccounts;
use App\Models\User;
use App\Support\LoginId;
use App\Support\UserIdentity;
use Filament\Forms\Components\Select;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class PendingCenterAccount
{
    public const COORDINATORS = 'pendingCoordinators';

    public const MANAGERS = 'pendingManagers';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function remember(object $livewire, string $bucket, array $data, UserRole $role, bool $reuseManager): int|string
    {
        self::assertHolder($livewire);
        $payload = self::payload($data, 'mobile');
        self::assertAvailable($livewire, $payload, $role, $reuseManager);

        $existing = self::reusableAccount($payload, $role, $reuseManager);
        if ($existing instanceof User) {
            return (int) $existing->id;
        }

        $key = ($role === UserRole::CenterCoordinator ? 'pending-coordinator-' : 'pending-manager-').$payload['mobile'];
        $stored = $livewire->{$bucket};
        $stored[$key] = $payload;
        $livewire->{$bucket} = $stored;

        return $key;
    }

    public static function materialize(object $livewire, string $bucket, string $key, UserRole $role, bool $reuseManager, string $errorKey): int
    {
        self::assertHolder($livewire);
        $stored = $livewire->{$bucket}[$key] ?? null;
        if (! is_array($stored)) {
            throw ValidationException::withMessages([
                $errorKey => 'Enter the new account again. It was not saved.',
            ]);
        }

        $payload = self::payload($stored, $errorKey);
        self::assertAvailable($livewire, $payload, $role, $reuseManager, $errorKey);

        $existing = User::query()->whereRaw('lower(login_id) = ?', [mb_strtolower($payload['mobile'])])->lockForUpdate()->first();
        if ($existing instanceof User) {
            $reusable = self::reusableAccount($payload, $role, $reuseManager);
            if ($reusable instanceof User && $reusable->is($existing)) {
                return (int) $existing->id;
            }

            throw ValidationException::withMessages([
                $errorKey => UserIdentity::LOGIN_TAKEN,
            ]);
        }

        if (User::query()->where('email', $payload['email'])->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                $errorKey => 'This email is already taken.',
            ]);
        }

        try {
            $user = User::query()->create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'login_id' => $payload['mobile'],
                'password' => Hash::make(LoginId::defaultPasswordFromMobile($payload['mobile'])),
                'role' => $role->value,
                'is_active' => true,
                'is_center_login' => false,
                'must_change_password' => true,
            ]);
        } catch (QueryException) {
            throw ValidationException::withMessages([
                $errorKey => 'This Login ID is already taken.',
            ]);
        }

        $token = $key;
        DB::afterCommit(function () use ($livewire, $bucket, $token): void {
            if (! isset($livewire->{$bucket}[$token])) {
                return;
            }

            $pending = $livewire->{$bucket};
            unset($pending[$token]);
            $livewire->{$bucket} = $pending;
        });

        return (int) $user->id;
    }

    public static function isPending(mixed $value): bool
    {
        return is_string($value)
            && (str_starts_with($value, 'pending-coordinator-') || str_starts_with($value, 'pending-manager-'));
    }

    /**
     * @return array<int|string, string>
     */
    public static function labelsFor(Select $component, string $bucket): array
    {
        $livewire = $component->getLivewire();
        $state = data_get($livewire, $component->getStatePath());
        if (! is_array($state)) {
            $state = [];
        }

        $pending = (isset($livewire->{$bucket}) && is_array($livewire->{$bucket}))
            ? $livewire->{$bucket}
            : [];
        $labels = [];
        $ids = [];

        foreach ($state as $value) {
            $token = (string) $value;
            if (isset($pending[$token]) && is_array($pending[$token])) {
                $labels[$token] = trim($pending[$token]['name'].' ('.$pending[$token]['mobile'].')');

                continue;
            }

            if (ctype_digit($token)) {
                $ids[] = (int) $token;
            }
        }

        if ($ids === []) {
            return $labels;
        }

        User::query()->whereIn('id', array_values(array_unique($ids)))->get()->each(function (User $user) use (&$labels): void {
            $labels[(string) $user->id] = trim($user->name.' ('.$user->login_id.')');
        });

        return $labels;
    }

    private static function assertHolder(object $livewire): void
    {
        if (! in_array(HoldsPendingCenterAccounts::class, class_uses_recursive($livewire), true)) {
            throw ValidationException::withMessages([
                'mobile' => 'This screen cannot create that account.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, mobile: string, email: string}
     */
    private static function payload(array $data, string $mobileKey): array
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
                $mobileKey => 'Enter a valid 10-digit mobile number.',
            ]);
        }

        if ($email === '') {
            $email = $mobile.'@fieldtrack.local';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'Enter a valid email address.',
            ]);
        }

        return [
            'name' => $name,
            'mobile' => $mobile,
            'email' => $email,
        ];
    }

    /**
     * @param  array{name: string, mobile: string, email: string}  $payload
     */
    private static function reusableAccount(array $payload, UserRole $role, bool $reuseManager): ?User
    {
        $existing = UserIdentity::userByLogin($payload['mobile']);
        if (! $existing instanceof User || UserIdentity::nameKey($existing->name) !== UserIdentity::nameKey($payload['name'])) {
            return null;
        }

        if ($reuseManager && $role === UserRole::CenterManager && self::canReuseManager($existing)) {
            return $existing;
        }

        if ($role === UserRole::CenterCoordinator && $existing->isCenterCoordinator() && $existing->is_center_login !== true) {
            return $existing;
        }

        return null;
    }

    /**
     * @param  array{name: string, mobile: string, email: string}  $payload
     */
    private static function assertAvailable(object $livewire, array $payload, UserRole $role, bool $reuseManager, string $attribute = 'mobile'): void
    {
        $mobile = $payload['mobile'];
        $email = $payload['email'];
        foreach ([self::COORDINATORS, self::MANAGERS] as $bucket) {
            foreach ($livewire->{$bucket} ?? [] as $pending) {
                if (! is_array($pending)) {
                    continue;
                }

                if (($pending['mobile'] ?? null) === $mobile) {
                    throw ValidationException::withMessages([
                        'mobile' => 'This Login ID is already taken.',
                    ]);
                }

                if (($pending['email'] ?? null) === $email) {
                    throw ValidationException::withMessages([
                        'email' => 'This email is already taken.',
                    ]);
                }
            }
        }

        UserIdentity::assertAvailable(
            $payload['name'],
            $mobile,
            allowManagerReuse: $reuseManager && $role === UserRole::CenterManager,
            allowCoordinatorReuse: $role === UserRole::CenterCoordinator,
            attribute: $attribute,
        );

        $emailOwner = User::query()->where('email', $email)->first();
        $reusable = self::reusableAccount($payload, $role, $reuseManager);
        if ($emailOwner instanceof User && ! ($reusable instanceof User && $emailOwner->is($reusable))) {
            throw ValidationException::withMessages([
                'email' => 'This email is already taken.',
            ]);
        }
    }

    private static function canReuseManager(User $user): bool
    {
        return $user->role === UserRole::CenterManager->value
            && $user->is_center_login !== true;
    }
}
