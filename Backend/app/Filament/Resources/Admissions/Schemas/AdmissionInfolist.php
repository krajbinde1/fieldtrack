<?php

namespace App\Filament\Resources\Admissions\Schemas;

use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionStatus;
use App\Filament\Resources\Admissions\AdmissionReviewActions;
use App\Models\Admission;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Size;

class AdmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->extraAttributes(['class' => 'admission-view-infolist'])
            ->components([
                Section::make('Review')
                    ->compact()
                    ->visible(fn (?Admission $record): bool => AdmissionReviewActions::canReview($record))
                    ->schema([
                        Actions::make(
                            array_map(
                                static fn (Action $action): Action => $action->size(Size::Small),
                                AdmissionReviewActions::make('infolist_'),
                            ),
                        ),
                    ]),
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make('Applicant')
                            ->compact()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('full_name')->label('Full Name'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => $state instanceof AdmissionStatus ? $state->label() : (string) $state)
                                    ->color(fn ($state): string => ($state instanceof AdmissionStatus ? $state : AdmissionStatus::tryFrom((string) $state))?->color() ?? 'gray'),
                                TextEntry::make('gender')->placeholder('-'),
                                TextEntry::make('religion')->placeholder('-'),
                                TextEntry::make('caste')->placeholder('-'),
                                TextEntry::make('scheme.name')->label('Scheme / Project')->placeholder('-'),
                            ]),
                        Section::make('Organization')
                            ->compact()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('center.name')->label('Center')->placeholder('-'),
                                TextEntry::make('employee.full_name')->label('Employee')->placeholder('-'),
                                TextEntry::make('submitted_at')->dateTime('d M Y h:i A')->placeholder('-'),
                                TextEntry::make('confirmed_at')->dateTime('d M Y h:i A')->placeholder('-'),
                                TextEntry::make('reviewedBy.name')->label('Reviewed / confirmed by')->placeholder('-'),
                                TextEntry::make('created_at')->dateTime('d M Y h:i A')->placeholder('-'),
                                TextEntry::make('review_reason')->placeholder('-')->columnSpanFull(),
                            ]),
                        Section::make('Address')
                            ->compact()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('state')->placeholder('-'),
                                TextEntry::make('district.name')->label('District')->placeholder('-'),
                                TextEntry::make('taluka.name')->label('Taluka')->placeholder('-'),
                                TextEntry::make('village')->placeholder('-'),
                            ]),
                        Section::make('Documents')
                            ->compact()
                            ->schema([
                                RepeatableEntry::make('documents')
                                    ->hiddenLabel()
                                    ->placeholder('No documents uploaded.')
                                    ->table([
                                        TableColumn::make('Document Type'),
                                        TableColumn::make('File Name'),
                                        TableColumn::make('File Type'),
                                        TableColumn::make('Size'),
                                        TableColumn::make('View')
                                            ->alignment(Alignment::Center)
                                            ->width('1%'),
                                        TableColumn::make('Download')
                                            ->alignment(Alignment::Center)
                                            ->width('1%'),
                                    ])
                                    ->schema([
                                        TextEntry::make('document_type')
                                            ->hiddenLabel()
                                            ->formatStateUsing(fn (?string $state): string => self::documentTypeLabel($state)),
                                        TextEntry::make('original_name')
                                            ->hiddenLabel()
                                            ->placeholder('-'),
                                        TextEntry::make('mime_type')
                                            ->hiddenLabel()
                                            ->formatStateUsing(
                                                fn (?string $state, $record): string => self::fileTypeLabel(
                                                    $state,
                                                    is_object($record) ? ($record->original_name ?? null) : null,
                                                ),
                                            ),
                                        TextEntry::make('size')
                                            ->hiddenLabel()
                                            ->formatStateUsing(fn ($state): string => self::fileSizeLabel($state)),
                                        Actions::make([
                                            Action::make('viewDocument')
                                                ->label('View')
                                                ->link()
                                                ->size(Size::Small)
                                                ->action(function (Get $get, $livewire) {
                                                    return $livewire->previewAdmissionDocument((int) $get('id'));
                                                }),
                                        ]),
                                        Actions::make([
                                            Action::make('downloadDocument')
                                                ->label('Download')
                                                ->link()
                                                ->size(Size::Small)
                                                ->action(function (Get $get, $livewire) {
                                                    return $livewire->downloadAdmissionDocument((int) $get('id'));
                                                }),
                                        ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    private static function documentTypeLabel(?string $state): string
    {
        if (! filled($state)) {
            return '-';
        }

        return AdmissionDocumentType::tryFrom($state)?->label()
            ?? str($state)->replace('_', ' ')->title()->toString();
    }

    private static function fileTypeLabel(?string $mime, ?string $filename): string
    {
        $extension = strtoupper((string) pathinfo((string) $filename, PATHINFO_EXTENSION));
        if ($extension !== '') {
            return $extension;
        }

        if (filled($mime) && $mime !== 'application/octet-stream') {
            return $mime;
        }

        return '-';
    }

    private static function fileSizeLabel(mixed $state): string
    {
        $bytes = (int) $state;
        if ($bytes <= 0) {
            return '-';
        }
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / 1048576, 1).' MB';
    }
}
