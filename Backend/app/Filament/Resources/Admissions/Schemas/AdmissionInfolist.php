<?php

namespace App\Filament\Resources\Admissions\Schemas;

use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionStatus;
use App\Filament\Resources\Admissions\AdmissionReviewActions;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;

class AdmissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->extraAttributes(['class' => 'admission-view-infolist'])
            ->components([
                Grid::make(['default' => 1, 'lg' => 2])
                    ->extraAttributes(['class' => 'admission-view-row'])
                    ->schema([
                        Section::make('Review')
                            ->compact()
                            ->extraAttributes(['class' => 'admission-view-card'])
                            ->schema([
                                Actions::make(
                                    array_map(
                                        static fn (Action $action): Action => $action
                                            ->size(Size::Small)
                                            ->extraAttributes(['class' => 'admission-review-btn']),
                                        AdmissionReviewActions::make('infolist_'),
                                    ),
                                )->extraAttributes(['class' => 'admission-review-actions']),
                            ]),
                        Section::make('Applicant')
                            ->compact()
                            ->extraAttributes(['class' => 'admission-view-card'])
                            ->columns(['default' => 1, 'md' => 2, 'lg' => 3])
                            ->schema([
                                TextEntry::make('full_name')->label('Full Name')->placeholder('-'),
                                TextEntry::make('gender')->placeholder('-'),
                                TextEntry::make('religion')->placeholder('-'),
                                TextEntry::make('caste')->placeholder('-'),
                                TextEntry::make('scheme.name')->label('Scheme / Project')->placeholder('-'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => $state instanceof AdmissionStatus ? $state->label() : (string) $state)
                                    ->color(fn ($state): string => ($state instanceof AdmissionStatus ? $state : AdmissionStatus::tryFrom((string) $state))?->color() ?? 'gray'),
                            ]),
                    ]),
                Grid::make(['default' => 1, 'lg' => 2])
                    ->extraAttributes(['class' => 'admission-view-row'])
                    ->schema([
                        Section::make('Organization')
                            ->compact()
                            ->extraAttributes(['class' => 'admission-view-card'])
                            ->columns(['default' => 1, 'md' => 2, 'lg' => 3])
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
                            ->extraAttributes(['class' => 'admission-view-card'])
                            ->columns(['default' => 1, 'lg' => 2])
                            ->schema([
                                TextEntry::make('state')->placeholder('-'),
                                TextEntry::make('district.name')->label('District')->placeholder('-'),
                                TextEntry::make('taluka.name')->label('Taluka')->placeholder('-'),
                                TextEntry::make('village')->placeholder('-'),
                            ]),
                    ]),
                Section::make('Documents')
                    ->compact()
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'admission-view-card admission-view-documents'])
                    ->schema([
                        ViewEntry::make('documents')
                            ->hiddenLabel()
                            ->view('filament.admissions.documents-table'),
                    ]),
            ]);
    }

    public static function documentTypeLabel(?string $state): string
    {
        if (! filled($state)) {
            return '-';
        }

        return AdmissionDocumentType::tryFrom($state)?->label()
            ?? str($state)->replace('_', ' ')->title()->toString();
    }

    public static function fileTypeLabel(?string $mime, ?string $filename): string
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

    public static function fileSizeLabel(mixed $state): string
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
