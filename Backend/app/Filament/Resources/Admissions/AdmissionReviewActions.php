<?php

namespace App\Filament\Resources\Admissions;

use App\Models\Admission;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\OrganizationAccessService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

final class AdmissionReviewActions
{
    /**
     * @return list<Action>
     */
    public static function make(string $namePrefix = ''): array
    {
        return [
            self::confirm($namePrefix),
            self::revert($namePrefix),
            self::reject($namePrefix),
        ];
    }

    public static function canReview(?Admission $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $record instanceof Admission
            && app(OrganizationAccessService::class)->canReviewAdmission($user, $record);
    }

    public static function confirm(string $namePrefix = ''): Action
    {
        return Action::make($namePrefix.'confirm')
            ->label('Confirm')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (?Admission $record): bool => self::canReview($record))
            ->action(function (Admission $record, $livewire): void {
                self::run($record, $livewire, function (Admission $admission, User $user): void {
                    app(AdmissionService::class)->confirm($admission, $user);
                }, 'Admission confirmed');
            });
    }

    public static function revert(string $namePrefix = ''): Action
    {
        return Action::make($namePrefix.'revert')
            ->label('Revert')
            ->color('warning')
            ->visible(fn (?Admission $record): bool => self::canReview($record))
            ->form([
                Textarea::make('reason')
                    ->label('Revert reason')
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(function (Admission $record, array $data, $livewire): void {
                self::run($record, $livewire, function (Admission $admission, User $user) use ($data): void {
                    app(AdmissionService::class)->revert($admission, $user, $data['reason']);
                }, 'Admission reverted');
            });
    }

    public static function reject(string $namePrefix = ''): Action
    {
        return Action::make($namePrefix.'reject')
            ->label('Reject')
            ->color('danger')
            ->visible(fn (?Admission $record): bool => self::canReview($record))
            ->form([
                Textarea::make('reason')
                    ->label('Reject reason')
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(function (Admission $record, array $data, $livewire): void {
                self::run($record, $livewire, function (Admission $admission, User $user) use ($data): void {
                    app(AdmissionService::class)->reject($admission, $user, $data['reason']);
                }, 'Admission rejected');
            });
    }

    /**
     * @param  callable(Admission, User): void  $callback
     */
    private static function run(Admission $record, object $livewire, callable $callback, string $title): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        try {
            $callback($record, $user);
            $record->refresh();
            if (property_exists($livewire, 'record') && $livewire->record instanceof Admission) {
                $livewire->record->refresh();
            }
            if (method_exists($livewire, 'refreshFormData')) {
                $livewire->refreshFormData([
                    'status',
                    'review_reason',
                    'reviewed_by_user_id',
                    'reviewed_at',
                    'confirmed_at',
                ]);
            }
            Notification::make()->title($title)->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }
}
