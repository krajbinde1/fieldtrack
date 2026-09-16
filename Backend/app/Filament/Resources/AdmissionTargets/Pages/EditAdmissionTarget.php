<?php

namespace App\Filament\Resources\AdmissionTargets\Pages;

use App\Filament\Resources\AdmissionTargets\AdmissionTargetResource;
use App\Models\AdmissionTarget;
use App\Services\AdmissionTargetService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAdmissionTarget extends EditRecord
{
    protected static string $resource = AdmissionTargetResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['period'] = $this->record->period_start?->toDateString();
        $data['target_type'] = $this->record->target_type?->value ?? $data['target_type'] ?? null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);
        abort_unless($record instanceof AdmissionTarget, 404);

        return app(AdmissionTargetService::class)->update(
            $user,
            $record,
            CreateAdmissionTarget::payload(array_merge($data, [
                'employee_id' => $record->employee_id,
            ])),
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
