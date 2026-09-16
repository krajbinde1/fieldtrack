<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Enums\AdmissionDocumentType;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Admissions\AdmissionReviewActions;
use App\Models\AdmissionDocument;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewAdmission extends ViewRecord
{
    protected static string $resource = AdmissionResource::class;

    protected function getHeaderActions(): array
    {
        $this->record->loadMissing('documents');

        $downloads = $this->record->documents
            ->map(function (AdmissionDocument $document) {
                $label = $document->typeEnum()?->label() ?? AdmissionDocumentType::tryFrom($document->document_type)?->label() ?? 'Document';

                return Action::make('download_'.$document->id)
                    ->label('Download '.$label)
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () use ($document) {
                        abort_unless(
                            Storage::disk($document->disk ?: 'local')->exists($document->path),
                            404,
                            'Document file is missing.',
                        );

                        return Storage::disk($document->disk ?: 'local')->download(
                            $document->path,
                            $document->original_name,
                        );
                    });
            })
            ->all();

        return [
            ...AdmissionReviewActions::make(),
            ...$downloads,
        ];
    }
}
