<?php

namespace App\Filament\Resources\ExpenseClaims\Pages;

use App\Enums\ExpenseClaimStatus;
use App\Enums\ExpensePaymentStatus;
use App\Filament\Resources\ExpenseClaims\ExpenseClaimResource;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\Expenses\ExpenseClaimService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListExpenseClaims extends ListRecords
{
    protected static string $resource = ExpenseClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add Expense')
                ->visible(fn (): bool => ExpenseClaimResource::canCreate()),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $tabs = [];

        if ($user?->isCenterManager() === true) {
            $tabs['mine'] = Tab::make('My Expenses')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->inSelectedCenter($query)
                    ->where('created_by_user_id', $user->id));
        }

        $tabs['pending'] = Tab::make('Pending Approval')
            ->modifyQueryUsing(function (Builder $query) use ($user): Builder {
                if ($user === null) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->whereIn(
                    'expense_claims.id',
                    app(ExpenseClaimService::class)->queueQuery($user)->select('expense_claims.id'),
                );
            });
        $tabs['approved'] = Tab::make('Approved')
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->inSelectedCenter($query)
                ->where('status', ExpenseClaimStatus::Approved->value)
                ->where('payment_status', ExpensePaymentStatus::Unpaid->value));
        $tabs['rejected'] = Tab::make('Rejected')
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->inSelectedCenter($query)
                ->where('status', ExpenseClaimStatus::Rejected->value));
        $tabs['paid'] = Tab::make('Paid')
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->inSelectedCenter($query)
                ->where('payment_status', ExpensePaymentStatus::Paid->value));
        $tabs['history'] = Tab::make('History')
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->inSelectedCenter($query)
                ->where('status', '!=', ExpenseClaimStatus::Draft->value));

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }

    private function inSelectedCenter(Builder $query): Builder
    {
        $user = auth()->user();
        $centerId = $user instanceof User
            ? app(AdminCenterContext::class)->idFor($user)
            : null;

        if ($centerId === null) {
            return $query;
        }

        return $query->where('expense_claims.center_id', $centerId);
    }
}
