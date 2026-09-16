<?php

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Services\LeaveService;
use App\Services\OrganizationAccessService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ViewLeaveRequest extends ViewRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $access = app(OrganizationAccessService::class);
        $actions = [];

        if ($this->record->hasDocument()) {
            $actions[] = Action::make('downloadDocument')
                ->label('Download Document')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    abort_unless($this->record->hasDocument(), 404);
                    abort_unless(
                        Storage::disk($this->record->document_disk ?: 'local')->exists($this->record->document_path),
                        404,
                        'Document file is missing.',
                    );

                    return Storage::disk($this->record->document_disk ?: 'local')->download(
                        $this->record->document_path,
                        $this->record->document_original_name,
                    );
                });
        }

        if ($user && $access->canApproveLeave($user, $this->record)) {
            $actions[] = Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->form([
                    Textarea::make('approval_remark')->label('Approval remark')->maxLength(1000),
                ])
                ->action(function (array $data) use ($user): void {
                    try {
                        app(LeaveService::class)->approve($this->record, $user, $data['approval_remark'] ?? null);
                        Notification::make()->title('Leave approved')->success()->send();
                        $this->refreshFormData(['status', 'approval_remark', 'reviewed_at']);
                    } catch (ValidationException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                    }
                });

            $actions[] = Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->form([
                    Textarea::make('rejection_remark')
                        ->label('Rejection remark')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(function (array $data) use ($user): void {
                    try {
                        app(LeaveService::class)->reject($this->record, $user, $data['rejection_remark']);
                        Notification::make()->title('Leave rejected')->success()->send();
                        $this->refreshFormData(['status', 'rejection_remark', 'reviewed_at']);
                    } catch (ValidationException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                    }
                });
        }

        return $actions;
    }
}
