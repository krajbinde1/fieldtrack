<?php

namespace App\Filament\Resources\ExpenseClaims\Pages;

use App\Filament\Resources\ExpenseClaims\ExpenseClaimResource;
use App\Services\Expenses\ExpenseClaimService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateExpenseClaim extends CreateRecord
{
    use SavesExpenseClaimForm;

    protected static string $resource = ExpenseClaimResource::class;

    protected static bool $canCreateAnother = false;

    protected function getFormActions(): array
    {
        return [
            $this->draftAction(),
            $this->submitAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        [$items, $receipts] = $this->claimPayload($data);

        return app(ExpenseClaimService::class)->save(
            auth()->user(),
            $items,
            $receipts,
            $this->submitClaim,
        );
    }

    protected function getRedirectUrl(): string
    {
        return ExpenseClaimResource::getUrl('index');
    }
}
