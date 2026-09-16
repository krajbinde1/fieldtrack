<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Filament\Resources\Admissions\AdmissionResource;
use App\Models\AdmissionDocument;
use App\Services\OrganizationAccessService;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewAdmission extends ViewRecord
{
    protected static string $resource = AdmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function previewAdmissionDocument(int $document): StreamedResponse
    {
        return $this->streamAdmissionDocument($document, asDownload: false);
    }

    public function downloadAdmissionDocument(int $document): StreamedResponse
    {
        return $this->streamAdmissionDocument($document, asDownload: true);
    }

    private function streamAdmissionDocument(int $documentId, bool $asDownload): StreamedResponse
    {
        $this->record->loadMissing('documents');
        $document = $this->record->documents->firstWhere('id', $documentId);
        abort_unless($document instanceof AdmissionDocument, 404);

        app(OrganizationAccessService::class)
            ->assertCanViewAdmission(auth()->user(), $this->record);

        $disk = $document->disk ?: 'local';
        abort_unless(
            Storage::disk($disk)->exists($document->path),
            404,
            'Document file is missing.',
        );

        $filename = $document->original_name ?: 'document';
        $headers = [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
        ];

        if ($asDownload) {
            return Storage::disk($disk)->download($document->path, $filename, $headers);
        }

        return Storage::disk($disk)->response($document->path, $filename, $headers);
    }
}
