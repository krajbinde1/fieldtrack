<?php

namespace App\Filament\Resources\Admissions\Schemas;

use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionStatus;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Applicant')->columns(3)->schema([
                TextEntry::make('full_name')->label('Full Name'),
                TextEntry::make('gender'),
                TextEntry::make('religion'),
                TextEntry::make('caste'),
                TextEntry::make('scheme.name')->label('Scheme / Project'),
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof AdmissionStatus ? $state->label() : (string) $state)
                    ->color(fn ($state): string => ($state instanceof AdmissionStatus ? $state : AdmissionStatus::tryFrom((string) $state))?->color() ?? 'gray'),
            ]),
            Section::make('Organization')->columns(3)->schema([
                TextEntry::make('center.name')->label('Center'),
                TextEntry::make('employee.full_name')->label('Employee'),
                TextEntry::make('submitted_at')->dateTime('d M Y h:i A')->placeholder('-'),
                TextEntry::make('confirmed_at')->dateTime('d M Y h:i A')->placeholder('-'),
                TextEntry::make('review_reason')->placeholder('-')->columnSpanFull(),
                TextEntry::make('created_at')->dateTime('d M Y h:i A'),
            ]),
            Section::make('Address')->columns(2)->schema([
                TextEntry::make('state'),
                TextEntry::make('district.name')->label('District'),
                TextEntry::make('taluka.name')->label('Taluka'),
                TextEntry::make('village'),
            ]),
            Section::make('Documents')->schema([
                RepeatableEntry::make('documents')->schema([
                    TextEntry::make('document_type')
                        ->label('Type')
                        ->formatStateUsing(fn (?string $state): string => AdmissionDocumentType::tryFrom((string) $state)?->label() ?? (string) $state),
                    TextEntry::make('original_name')->label('File'),
                    TextEntry::make('mime_type')->label('Type'),
                    TextEntry::make('size')
                        ->formatStateUsing(fn ($state): string => number_format(((int) $state) / 1024, 1).' KB'),
                ])->columns(4),
            ]),
        ]);
    }
}
