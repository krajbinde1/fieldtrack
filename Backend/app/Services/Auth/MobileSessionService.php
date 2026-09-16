<?php

namespace App\Services\Auth;

use App\Models\DeviceToken;
use App\Models\RevokedMobileToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Enforces a single active mobile Sanctum session per user.
 * Admin Filament / web sessions are untouched.
 */
final class MobileSessionService
{
    public const TOKEN_NAME = 'employee-mobile';

    public const CODE_SESSION_REPLACED = 'SESSION_REPLACED';

    public const MESSAGE_SESSION_REPLACED = 'Your account was signed in on another device.';

    /**
     * Start a new mobile session. Latest login wins; previous mobile tokens are revoked.
     *
     * @return array{token: string, session_id: string, device_id: string|null}
     */
    public function startSession(User $user, ?string $deviceId = null): array
    {
        $deviceId = filled($deviceId) ? trim((string) $deviceId) : null;
        if ($deviceId !== null && strlen($deviceId) > 64) {
            $deviceId = substr($deviceId, 0, 64);
        }

        return DB::transaction(function () use ($user, $deviceId): array {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $this->revokeExistingMobileTokens($locked);

            $sessionId = (string) Str::uuid();
            $newToken = $locked->createToken(self::TOKEN_NAME, [self::TOKEN_NAME, 'session:'.$sessionId]);
            $plainText = $newToken->plainTextToken;
            $tokenId = (int) $newToken->accessToken->id;

            $locked->forceFill([
                'active_mobile_session_id' => $sessionId,
                'active_mobile_device_id' => $deviceId,
                'active_mobile_token_id' => $tokenId,
                'active_mobile_login_at' => now(),
            ])->save();

            DeviceToken::query()
                ->where('user_id', $locked->id)
                ->when(
                    $deviceId !== null,
                    fn ($q) => $q->where(function ($inner) use ($deviceId): void {
                        $inner->whereNull('installation_id')
                            ->orWhere('installation_id', '!=', $deviceId);
                    }),
                )
                ->delete();

            return [
                'token' => $plainText,
                'session_id' => $sessionId,
                'device_id' => $deviceId,
            ];
        });
    }

    public function endSession(User $user, ?PersonalAccessToken $currentToken = null): void
    {
        DB::transaction(function () use ($user, $currentToken): void {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($currentToken !== null) {
                $this->rememberRevokedHash($locked->id, $currentToken->token, $locked->active_mobile_device_id);
                $currentToken->delete();
            }

            $locked->tokens()->where('name', self::TOKEN_NAME)->delete();

            DeviceToken::query()->where('user_id', $locked->id)->delete();

            $locked->forceFill([
                'active_mobile_session_id' => null,
                'active_mobile_device_id' => null,
                'active_mobile_token_id' => null,
                'active_mobile_login_at' => null,
            ])->save();
        });
    }

    public function isActiveMobileToken(User $user, PersonalAccessToken $token): bool
    {
        if ($token->name !== self::TOKEN_NAME) {
            return true;
        }

        if ($user->active_mobile_token_id === null) {
            return true;
        }

        return (int) $user->active_mobile_token_id === (int) $token->id;
    }

    public function wasRevokedMobileToken(?string $plainTextToken): bool
    {
        if ($plainTextToken === null || $plainTextToken === '') {
            return false;
        }

        $hashable = $plainTextToken;
        if (str_contains($plainTextToken, '|')) {
            [, $hashable] = explode('|', $plainTextToken, 2);
        }

        return RevokedMobileToken::query()
            ->where('token_hash', hash('sha256', $hashable))
            ->exists();
    }

    public function sessionReplacedResponse()
    {
        return response()->json([
            'success' => false,
            'code' => self::CODE_SESSION_REPLACED,
            'message' => self::MESSAGE_SESSION_REPLACED,
        ], 401);
    }

    private function revokeExistingMobileTokens(User $user): void
    {
        $tokens = $user->tokens()->where('name', self::TOKEN_NAME)->get();

        foreach ($tokens as $token) {
            $this->rememberRevokedHash(
                $user->id,
                $token->token,
                $user->active_mobile_device_id,
            );
        }

        if ($tokens->isNotEmpty()) {
            $user->tokens()->where('name', self::TOKEN_NAME)->delete();
        }
    }

    private function rememberRevokedHash(int $userId, string $storedTokenHash, ?string $deviceId): void
    {
        RevokedMobileToken::query()->updateOrCreate(
            ['token_hash' => $storedTokenHash],
            [
                'user_id' => $userId,
                'device_id' => $deviceId,
                'revoked_at' => now(),
            ],
        );
    }
}
