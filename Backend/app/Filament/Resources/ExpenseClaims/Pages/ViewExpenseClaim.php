<?php

namespace App\Filament\Resources\ExpenseClaims\Pages;

use App\Filament\Resources\ExpenseClaims\ExpenseClaimResource;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use App\Services\Expenses\ExpenseClaimService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ViewExpenseClaim extends ViewRecord
{
    protected static string $resource = ExpenseClaimResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $record = $this->getRecord();
        if ($user === null || ! $record instanceof ExpenseClaim) {
            return [];
        }

        $record->loadMissing('items');
        $service = app(ExpenseClaimService::class);
        $actions = [];

        foreach ($record->items as $item) {
            if (! $item instanceof ExpenseItem || ! $item->hasReceipt()) {
                continue;
            }

            $actions[] = Action::make('receipt'.$item->id)
                ->label('Receipt · '.($item->type?->label() ?? 'Expense'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () use ($item) {
                    abort_unless(
                        $item->hasReceipt() && Storage::disk($item->receipt_disk)->exists($item->receipt_path),
                        404,
                        'Receipt file is missing.',
                    );

                    return Storage::disk($item->receipt_disk)->download(
                        $item->receipt_path,
                        $item->receipt_original_name ?: 'receipt',
                    );
                });
        }

        if ($service->canDecide($user, $record)) {
            $actions[] = Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->schema([
                    Textarea::make('remarks')->label('Remarks')->maxLength(1000),
                ])
                ->action(function (array $data) use ($service, $user): void {
                    $this->decide(fn () => $service->approve($user, $this->record, $data['remarks'] ?? null), 'Expense approved');
                });
            $actions[] = Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->schema([
                    Textarea::make('remarks')->label('Remarks')->required()->maxLength(1000),
                ])
                ->action(function (array $data) use ($service, $user): void {
                    $this->decide(fn () => $service->reject($user, $this->record, (string) $data['remarks']), 'Expense rejected');
                });
        }

        if ($user->isAdminOrDirector() && $record->status?->value === 'approved' && $record->payment_status?->value === 'unpaid') {
            $actions[] = Action::make('pay')
                ->label('Mark Paid')
                ->color('info')
                ->schema([
                    TextInput::make('amount')->numeric()->required()->default(fn (): string => (string) $this->record->total_amount),
                    Select::make('payment_mode')->required()->options([
                        'cash' => 'Cash',
                        'upi' => 'UPI',
                        'bank_transfer' => 'Bank transfer',
                        'cheque' => 'Cheque',
                        'other' => 'Other',
                    ]),
                    TextInput::make('reference_number')->maxLength(100),
                    DatePicker::make('paid_on')->required()->default(now()),
                    Textarea::make('remarks')->maxLength(1000),
                ])
                ->action(function (array $data) use ($service, $user): void {
                    $this->decide(fn () => $service->markPaid($user, $this->record, $data), 'Expense marked paid');
                });
        }

        return $actions;
    }

    private function decide(callable $callback, string $success): void
    {
        try {
            $callback();
            Notification::make()->title($success)->success()->send();
            $this->record->refresh()->load(['employee', 'center', 'items', 'approvals', 'payment']);
            $this->refreshFormData(['status', 'payment_status', 'rejection_remarks', 'total_amount']);
        } catch (ValidationException|HttpException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }
}
