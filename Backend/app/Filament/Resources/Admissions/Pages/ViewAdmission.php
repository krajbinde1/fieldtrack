<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Enums\AdmissionDocumentType;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Models\AdmissionDocument;
use App\Services\AdmissionService;
use App\Services\OrganizationAccessService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ViewAdmission extends ViewRecord
{
    protected static string $resource = AdmissionResource::class;

    protected function getHeaderActions(): array
    {
        $this->record->loadMissing('documents');

        $actions = $this->record->documents
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

        $user = auth()->user();
        $access = app(OrganizationAccessService::class);

        if ($user && $access->canReviewAdmission($user, $this->record)) {
            $actions[] = Action::make('confirm')
                ->label('Confirm')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () use ($user): void {
                    try {
                        app(AdmissionService::class)->confirm($this->record, $user);
                        $this->record->refresh();
                        Notification::make()->title('Admission confirmed')->success()->send();
                        $this->refreshFormData([
                            'status',
                            'review_reason',
                            'reviewed_by_user_id',
                            'reviewed_at',
                            'confirmed_at',
                        ]);
                    } catch (ValidationException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                    }
                });

            $actions[] = Action::make('revert')
                ->label('Revert')
                ->color('warning')
                ->form([
                    Textarea::make('reason')
                        ->label('Revert reason')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(function (array $data) use ($user): void {
                    try {
                        app(AdmissionService::class)->revert($this->record, $user, $data['reason']);
                        $this->record->refresh();
                        Notification::make()->title('Admission reverted')->success()->send();
                        $this->refreshFormData([
                            'status',
                            'review_reason',
                            'reviewed_by_user_id',
                            'reviewed_at',
                            'confirmed_at',
                        ]);
                    } catch (ValidationException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                    }
                });

            $actions[] = Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->form([
                    Textarea::make('reason')
                        ->label('Reject reason')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(function (array $data) use ($user): void {
                    try {
                        app(AdmissionService::class)->reject($this->record, $user, $data['reason']);
                        $this->record->refresh();
                        Notification::make()->title('Admission rejected')->success()->send();
                        $this->refreshFormData([
                            'status',
                            'review_reason',
                            'reviewed_by_user_id',
                            'reviewed_at',
                            'confirmed_at',
                        ]);
                    } catch (ValidationException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                    }
                });
        }

        return $actions;
    }
}
