<?php

namespace App\Filament\Resources\LeaveRequests\Schemas;

use App\Enums\LeaveStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeaveRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Leave')->columns(3)->schema([
                TextEntry::make('employee.full_name')->label('Employee'),
                TextEntry::make('leave_type')
                    ->label('Leave Type')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? (string) $state),
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof LeaveStatus ? $state->label() : ucfirst((string) $state))
                    ->color(fn ($state): string => match ($state instanceof LeaveStatus ? $state : LeaveStatus::tryFrom((string) $state)) {
                        LeaveStatus::Approved => 'success',
                        LeaveStatus::Rejected => 'danger',
                        LeaveStatus::Pending => 'warning',
                        default => 'gray',
                    }),
                TextEntry::make('from_date')->date('d M Y'),
                TextEntry::make('to_date')->date('d M Y'),
                TextEntry::make('total_days')->label('Total Days'),
                TextEntry::make('reason')->columnSpanFull(),
                TextEntry::make('approval_remark')->placeholder('-')->columnSpanFull(),
                TextEntry::make('rejection_remark')->placeholder('-')->columnSpanFull(),
            ]),
            Section::make('Organization')->columns(3)->schema([
                TextEntry::make('scheme.name')->label('Scheme / Project'),
                TextEntry::make('center.name')->label('Center'),
                TextEntry::make('reviewedBy.name')->label('Reviewed By')->placeholder('-'),
                TextEntry::make('reviewed_at')->dateTime('d M Y h:i A')->placeholder('-'),
                TextEntry::make('document_original_name')->label('Document')->placeholder('None'),
            ]),
        ]);
    }
}
