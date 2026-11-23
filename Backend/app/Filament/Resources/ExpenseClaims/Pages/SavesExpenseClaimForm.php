<?php

namespace App\Filament\Resources\ExpenseClaims\Pages;

use Filament\Actions\Action;
use Illuminate\Http\UploadedFile;

trait SavesExpenseClaimForm
{
    public bool $submitClaim = false;

    protected function draftAction(): Action
    {
        return Action::make('saveDraft')
            ->label('Save Draft')
            ->action(function (): void {
                $this->submitClaim = false;
                $this->saveClaim();
            });
    }

    protected function submitAction(): Action
    {
        return Action::make('submitClaim')
            ->label('Submit')
            ->color('primary')
            ->action(function (): void {
                $this->submitClaim = true;
                $this->saveClaim();
            });
    }

    protected function saveClaim(): void
    {
        if (method_exists($this, 'create') && $this instanceof CreateExpenseClaim) {
            $this->create();

            return;
        }

        $this->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<array<string, mixed>>, 1: array<int, UploadedFile|null>}
     */
    protected function claimPayload(array $data): array
    {
        $items = [];
        $receipts = [];

        foreach (array_values($data['items'] ?? []) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $date = $item['expense_date'] ?? null;
            $items[] = [
                'expense_date' => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date,
                'type' => $item['type'] ?? null,
                'travel_mode' => $item['travel_mode'] ?? null,
                'amount' => $item['amount'] ?? null,
                'description' => $item['description'] ?? null,
                'travel_from' => $item['travel_from'] ?? null,
                'travel_to' => $item['travel_to'] ?? null,
                'travel_from_source' => $item['travel_from_source'] ?? null,
                'travel_to_source' => $item['travel_to_source'] ?? null,
                'travel_km' => $item['travel_km'] ?? null,
            ];

            $file = $item['receipt'] ?? null;
            if (is_array($file)) {
                $file = reset($file) ?: null;
            }
            $receipts[$index] = $file instanceof UploadedFile ? $file : null;
        }

        return [$items, $receipts];
    }
}
