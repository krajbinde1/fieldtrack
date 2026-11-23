<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ExpenseClaims\ExpenseClaimResource;
use App\Models\User;
use App\Services\Expenses\ExpenseClaimService;
use Filament\Widgets\Widget;

class ExpensePendingWidget extends Widget
{
    protected string $view = 'filament.widgets.expense-pending-widget';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->isAdmin() || $user->isDirector() || $user->isCenterCoordinator() || $user->isCenterManager());
    }

    /**
     * @return array{count: int, url: string}
     */
    public function pending(): array
    {
        return [
            'count' => app(ExpenseClaimService::class)->pendingCountFor(auth()->user()),
            'url' => ExpenseClaimResource::getUrl().'?tab=pending',
        ];
    }
}
