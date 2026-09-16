<?php

namespace App\Filament\Resources\AdmissionTargets\Pages;

use App\Filament\Resources\AdmissionTargets\AdmissionTargetResource;
use App\Filament\Support\TodayDateFilter;
use App\Services\AdmissionTargetService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAdmissionTarget extends CreateRecord
{
    protected static string $resource = AdmissionTargetResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        return app(AdmissionTargetService::class)->assign($user, self::payload($data));
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{employee_id: int, target_type: string, target_count: int, period: string}
     */
    public static function payload(array $data): array
    {
        $type = $data['target_type'] ?? null;

        return [
            'employee_id' => (int) $data['employee_id'],
            'target_type' => $type instanceof \BackedEnum ? $type->value : (string) $type,
            'target_count' => (int) $data['target_count'],
            'period' => (string) (TodayDateFilter::normalizeDate($data['period'] ?? null) ?? $data['period']),
        ];
    }
}
