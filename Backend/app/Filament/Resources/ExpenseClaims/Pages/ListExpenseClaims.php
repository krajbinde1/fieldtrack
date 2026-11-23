<?php

namespace App\Filament\Resources\ExpenseClaims\Pages;

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
                ->badge(fn (): string => (string) $this->inSelectedCenter(
                    ExpenseClaimResource::getEloquentQuery()->where('created_by_user_id', $user->id),
                )->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->inSelectedCenter($query)
                    ->where('created_by_user_id', $user->id));
        }

        $tabs['pending'] = $this->statusTab('pending', 'Pending Approval', false);
        $tabs['approved'] = $this->statusTab('approved', 'Approved', true);
        $tabs['rejected'] = $this->statusTab('rejected', 'Rejected', true);
        $tabs['paid'] = $this->statusTab('paid', 'Paid', true);
        $tabs['history'] = $this->statusTab('history', 'History', true);

        return $tabs;
    }

    private function statusTab(string $key, string $label, bool $limitToSelectedCenter): Tab
    {
        return Tab::make($label)
            ->badge(fn (): string => (string) $this->statusTabQuery($key, $limitToSelectedCenter)->count())
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyStatusTab($query, $key, $limitToSelectedCenter));
    }

    private function statusTabQuery(string $key, bool $limitToSelectedCenter): Builder
    {
        return $this->applyStatusTab(ExpenseClaimResource::getEloquentQuery(), $key, $limitToSelectedCenter);
    }

    private function applyStatusTab(Builder $query, string $key, bool $limitToSelectedCenter): Builder
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereIn(
            'expense_claims.id',
            app(ExpenseClaimService::class)->tabQuery($user, $key)->select('expense_claims.id'),
        );

        return $limitToSelectedCenter ? $this->inSelectedCenter($query) : $query;
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
