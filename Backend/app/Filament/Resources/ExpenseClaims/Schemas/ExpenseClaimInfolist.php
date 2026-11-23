<?php

namespace App\Filament\Resources\ExpenseClaims\Schemas;

use App\Enums\ExpenseClaimStatus;
use App\Enums\ExpensePaymentStatus;
use App\Models\ExpenseClaim;
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
                TextEntry::make('employee.full_name')->label('Employee'),
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
                    ->state(fn (ExpenseClaim $record): string => $record->items->map(fn ($item): string => sprintf(
                        '%s · %s · ₹%s · %s%s',
                        $item->expense_date?->format('d M Y'),
                        $item->type->label(),
                        number_format((float) $item->amount, 2),
                        $item->description,
                        $item->hasReceipt() ? ' · receipt attached' : '',
                    ))->join("\n"))
                    ->columnSpanFull(),
                TextEntry::make('approval_summary')
                    ->label('Approval history')
                    ->state(fn (ExpenseClaim $record): string => $record->approvals->map(fn ($approval): string => sprintf(
                        'Level %d %s by %s (%s)%s',
                        $approval->level,
                        $approval->action,
                        $approval->approver_name,
                        $approval->role,
                        filled($approval->remarks) ? ' — '.$approval->remarks : '',
                    ))->join("\n") ?: '—')
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
