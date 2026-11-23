<?php

namespace App\Filament\Resources\ExpenseClaims\Pages;

use App\Filament\Resources\ExpenseClaims\ExpenseClaimResource;
use App\Models\ExpenseClaim;
use App\Services\Expenses\ExpenseClaimService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditExpenseClaim extends EditRecord
{
    use SavesExpenseClaimForm;

    protected static string $resource = ExpenseClaimResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->draftAction(),
            $this->submitAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if ($record instanceof ExpenseClaim) {
            $data['items'] = $record->items->map(fn ($item): array => [
                'expense_date' => $item->expense_date?->toDateString(),
                'type' => $item->type instanceof \BackedEnum ? $item->type->value : null,
                'amount' => $item->amount,
                'description' => $item->description,
            ])->all();
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        [$items, $receipts] = $this->claimPayload($data);

        return app(ExpenseClaimService::class)->save(
            auth()->user(),
            $items,
            $receipts,
            $this->submitClaim,
            $record instanceof ExpenseClaim ? $record : null,
        );
    }

    protected function getRedirectUrl(): string
    {
        return ExpenseClaimResource::getUrl('index');
    }
}
