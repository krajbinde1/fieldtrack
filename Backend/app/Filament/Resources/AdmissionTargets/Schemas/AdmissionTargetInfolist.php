<?php

namespace App\Filament\Resources\AdmissionTargets\Schemas;

use App\Enums\AdmissionTargetType;
use App\Models\AdmissionTarget;
use App\Services\AdmissionTargetService;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdmissionTargetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $targets = app(AdmissionTargetService::class);

        return $schema->components([
            Section::make('Target')->columns(3)->schema([
                TextEntry::make('employee.full_name')
                    ->label('Employee')
                    ->formatStateUsing(fn (AdmissionTarget $record): string => $record->employee?->displayLabel() ?? '-'),
                TextEntry::make('target_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof AdmissionTargetType ? $state->label() : (string) $state),
                TextEntry::make('center.name')->label('Center')->placeholder('-'),
                TextEntry::make('period_start')->label('From')->date('d M Y'),
                TextEntry::make('period_end')->label('To')->date('d M Y'),
                TextEntry::make('scheme.name')->label('Scheme / Project')->placeholder('-'),
                TextEntry::make('target_count')->label('Target'),
                TextEntry::make('achieved')
                    ->label('Achieved')
                    ->state(fn (AdmissionTarget $record): int => $targets->metricsForTarget($record)['achieved']),
                TextEntry::make('remaining')
                    ->label('Remaining')
                    ->state(fn (AdmissionTarget $record): int => $targets->metricsForTarget($record)['remaining']),
                TextEntry::make('percentage')
                    ->label('Achievement %')
                    ->state(fn (AdmissionTarget $record): string => $targets->metricsForTarget($record)['percentage'].'%'),
            ]),
            Section::make('Weekly split')
                ->visible(fn (?AdmissionTarget $record): bool => (bool) $record?->isMonthly())
                ->schema([
                    RepeatableEntry::make('weeks')
                        ->hiddenLabel()
                        ->placeholder('No weekly split.')
                        ->table([
                            TableColumn::make('Week'),
                            TableColumn::make('Target'),
                            TableColumn::make('Achieved'),
                            TableColumn::make('Remaining'),
                            TableColumn::make('Achievement %'),
                        ])
                        ->schema([
                            TextEntry::make('period_start')
                                ->hiddenLabel()
                                ->formatStateUsing(fn ($state, AdmissionTarget $record): string => self::periodLabel($record)),
                            TextEntry::make('target_count')->hiddenLabel(),
                            TextEntry::make('achieved')
                                ->hiddenLabel()
                                ->state(fn (AdmissionTarget $record): int => $targets->metricsForTarget($record)['achieved']),
                            TextEntry::make('remaining')
                                ->hiddenLabel()
                                ->state(fn (AdmissionTarget $record): int => $targets->metricsForTarget($record)['remaining']),
                            TextEntry::make('percentage')
                                ->hiddenLabel()
                                ->state(fn (AdmissionTarget $record): string => $targets->metricsForTarget($record)['percentage'].'%'),
                        ]),
                ]),
        ]);
    }

    private static function periodLabel(AdmissionTarget $record): string
    {
        $start = $record->period_start?->format('d M Y') ?? '-';
        $end = $record->period_end?->format('d M Y') ?? '-';

        return $start.' – '.$end;
    }
}
