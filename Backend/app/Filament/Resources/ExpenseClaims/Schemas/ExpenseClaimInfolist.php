<?php

namespace App\Filament\Resources\ExpenseClaims\Schemas;

use App\Enums\ExpenseClaimStatus;
use App\Enums\ExpensePaymentStatus;
use App\Models\ExpenseClaim;
use App\Services\Expenses\ExpenseClaimService;
use Filament\Infolists\Components\TextEntry;
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
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof ExpenseClaimStatus ? $state->label() : (string) $state),
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
                TextEntry::make('approval_summary')
                    ->label('Approval history')
                    ->state(fn (ExpenseClaim $record): array => app(ExpenseClaimService::class)->approvalHistoryLines($record))
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('pending_approval')
                    ->label('Currently pending')
                    ->state(fn (ExpenseClaim $record): ?string => app(ExpenseClaimService::class)->pendingApprovalText($record))
                    ->visible(fn (ExpenseClaim $record): bool => app(ExpenseClaimService::class)->pendingApprovalText($record) !== null)
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
