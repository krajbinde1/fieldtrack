<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ExpenseClaims\ExpenseClaimResource;
use App\Models\AppNotification;
use App\Models\User;
use Filament\Pages\Page;

class NotificationInbox extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'notifications';

    protected static ?string $title = 'Notifications';

    protected string $view = 'filament.pages.notification-inbox';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->isAdmin() || $user->isDirector() || $user->isCenterCoordinator() || $user->isCenterManager());
    }

    /**
     * @return list<AppNotification>
     */
    public function items(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        return AppNotification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->all();
    }

    public function openNotification(int $id): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $notification = AppNotification::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOrFail();
        $notification->markRead();

        $claimId = $notification->data['expense_claim_id'] ?? null;
        if (filled($claimId)) {
            $this->redirect(ExpenseClaimResource::getUrl('view', ['record' => $claimId]));
        }
    }
}
