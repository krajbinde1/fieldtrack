<?php

namespace App\Filament\Resources\ExpenseClaims\Schemas;

use App\Enums\ExpensePaymentStatus;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use App\Services\Expenses\ExpenseApprovalTimeline;
use App\Services\Expenses\ExpenseClaimService;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseClaimInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Claim')->columns(3)->schema([
                TextEntry::make('claim_number')->label('Claim'),
                TextEntry::make('employee.full_name')
                    ->label('Employee')
                    ->placeholder('—')
                    ->state(fn (ExpenseClaim $record): string => $record->employee?->full_name ?: ($record->createdBy?->name ?? '—')),
                TextEntry::make('center.name')->label('Center'),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (ExpenseClaim $record): string => $record->displayStatus()),
                TextEntry::make('pending_with')
                    ->label('Pending With')
                    ->placeholder('—')
                    ->state(fn (ExpenseClaim $record): ?string => app(ExpenseClaimService::class)->pendingWithText($record)),
                TextEntry::make('payment_status')
                    ->label('Payment')
                    ->formatStateUsing(fn ($state): string => $state instanceof ExpensePaymentStatus ? $state->label() : (string) $state),
                TextEntry::make('total_amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state): string => '₹'.number_format((float) $state, 2)),
                TextEntry::make('rejection_remarks')->placeholder('—')->columnSpanFull(),
                TextEntry::make('payment.paid_on')
                    ->label('Paid on')
                    ->date('d M Y')
                    ->visible(fn (ExpenseClaim $record): bool => $record->payment !== null),
                TextEntry::make('payment.payment_mode')
                    ->label('Payment mode')
                    ->visible(fn (ExpenseClaim $record): bool => $record->payment !== null),
                TextEntry::make('payment.reference_number')
                    ->label('Payment reference')
                    ->placeholder('—')
                    ->visible(fn (ExpenseClaim $record): bool => $record->payment !== null),
                TextEntry::make('payment.amount')
                    ->label('Paid amount')
                    ->formatStateUsing(fn ($state): string => '₹'.number_format((float) $state, 2))
                    ->visible(fn (ExpenseClaim $record): bool => $record->payment !== null),
                TextEntry::make('payment.paid_by_name')
                    ->label('Paid by')
                    ->visible(fn (ExpenseClaim $record): bool => $record->payment !== null),
                TextEntry::make('payment.remarks')
                    ->label('Payment remarks')
                    ->placeholder('—')
                    ->columnSpanFull()
                    ->visible(fn (ExpenseClaim $record): bool => $record->payment !== null),
                TextEntry::make('items_summary')
                    ->label('Items')
                    ->state(fn (ExpenseClaim $record): string => $record->items->map(function ($item): string {
                        $type = match ($item->travel_mode) {
                            'self' => 'Self Travelling',
                            'public' => 'Public Transport',
                            default => $item->type?->label() ?? '—',
                        };
                        $distance = $item->travel_mode === 'self' && $item->recorded_km !== null
                            ? ' · '.$item->recorded_km.' recorded km · ₹'.$item->km_rate.'/km'
                            : '';

                        return sprintf(
                            '%s · %s · ₹%s · %s%s%s',
                            $item->expense_date?->format('d M Y'),
                            $type,
                            number_format((float) $item->amount, 2),
                            $item->description,
                            $distance,
                            $item->hasReceipt() ? ' · receipt attached' : '',
                        );
                    })->join("\n"))
                    ->columnSpanFull(),
            ]),
            Section::make('Receipts')
                ->columnSpanFull()
                ->schema([
                    ViewEntry::make('expense_receipts')
                        ->hiddenLabel()
                        ->view('filament.expenses.receipts')
                        ->state(fn (ExpenseClaim $record): array => self::receiptCards($record)),
                ]),
            Section::make('Approval timeline')
                ->columnSpanFull()
                ->visible(fn (ExpenseClaim $record): bool => app(ExpenseApprovalTimeline::class)->forClaim($record)['visible'] === true)
                ->schema([
                    ViewEntry::make('approval_timeline')
                        ->hiddenLabel()
                        ->view('filament.expenses.approval-timeline')
                        ->state(fn (ExpenseClaim $record): array => app(ExpenseApprovalTimeline::class)->forClaim($record)),
                ]),
        ]);
    }

    /**
     * @return array{receipts: list<array{label: string, filename: string, kind: string, preview_url: string, download_url: string}>}
     */
    public static function receiptCards(ExpenseClaim $record): array
    {
        $record->loadMissing('items');
        $receipts = [];

        foreach ($record->items as $item) {
            if (! $item instanceof ExpenseItem || ! $item->hasReceipt()) {
                continue;
            }

            $filename = $item->receipt_original_name ?: 'receipt';
            $mime = strtolower((string) $item->receipt_mime);
            $kind = match (true) {
                str_starts_with($mime, 'image/') => 'image',
                $mime === 'application/pdf' || str_ends_with(strtolower($filename), '.pdf') => 'pdf',
                default => 'file',
            };
            $type = match ($item->travel_mode) {
                'self' => 'Self Travelling',
                'public' => 'Public Transport',
                default => $item->type?->label() ?? 'Expense',
            };
            $params = ['expenseClaim' => $record->getKey(), 'item' => $item->getKey()];

            $receipts[] = [
                'label' => collect([
                    $item->expense_date?->format('d M Y'),
                    $type,
                    $item->description,
                ])->filter(fn (mixed $part): bool => filled($part))->implode(' · '),
                'filename' => $filename,
                'kind' => $kind,
                'preview_url' => route('admin.expense-receipts.preview', $params),
                'download_url' => route('admin.expense-receipts.download', $params),
            ];
        }

        return ['receipts' => $receipts];
    }
}
