<?php

namespace App\Services\Notifications;

use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\ExpenseClaim;
use App\Models\User;
use Illuminate\Support\Collection;

final class ExpensePushNotifier
{
    public function __construct(
        private readonly FcmHttpClient $fcm = new FcmHttpClient,
    ) {}

    /**
     * @param  Collection<int, User>  $users
     */
    public function notifyUsers(Collection $users, ExpenseClaim $claim, string $type, string $title, string $body, string $route): void
    {
        $users->unique('id')->each(function (User $user) use ($claim, $type, $title, $body, $route): void {
            $this->store($user, $claim, $type, $title, $body, $route);
        });
    }

    public function notifyUser(User $user, ExpenseClaim $claim, string $type, string $title, string $body, string $route): void
    {
        $this->store($user, $claim, $type, $title, $body, $route);
    }

    private function store(User $user, ExpenseClaim $claim, string $type, string $title, string $body, string $route): void
    {
        $key = $type.':'.$claim->id.':'.$user->id;
        $data = [
            'type' => $type,
            'expense_claim_id' => (string) $claim->id,
            'claim_no' => $claim->claimNumber(),
            'route' => $route,
        ];

        $notification = AppNotification::query()->firstOrCreate(
            ['dedupe_key' => $key],
            [
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ],
        );

        if (! $notification->wasRecentlyCreated) {
            return;
        }

        $tokens = DeviceToken::query()->where('user_id', $user->id)->pluck('token')->all();
        $this->fcm->sendToTokens($tokens, [
            'title' => $title,
            'body' => $body,
        ], $data);
    }
}
