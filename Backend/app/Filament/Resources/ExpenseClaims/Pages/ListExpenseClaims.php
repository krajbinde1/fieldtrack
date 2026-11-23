<?php

namespace App\Filament\Resources\ExpenseClaims\Pages;

use App\Enums\ExpenseClaimStatus;
use App\Filament\Resources\ExpenseClaims\ExpenseClaimResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListExpenseClaims extends ListRecords
{
    protected static string $resource = ExpenseClaimResource::class;

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    ExpenseClaimStatus::PendingCenterManager->value,
                    ExpenseClaimStatus::PendingCenterCoordinator->value,
                    ExpenseClaimStatus::PendingFinal->value,
                    ExpenseClaimStatus::NeedsResolution->value,
                ])),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ExpenseClaimStatus::Approved->value)),
            'rejected' => Tab::make('Rejected')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ExpenseClaimStatus::Rejected->value)),
            'history' => Tab::make('History')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', '!=', ExpenseClaimStatus::Draft->value)),
        ];
    }
}
