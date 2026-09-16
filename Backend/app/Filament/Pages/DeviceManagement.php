<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\MobileSessionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeviceManagement extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Device Management';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $title = 'Device Management';

    protected static ?string $slug = 'device-management';

    protected string $view = 'filament.pages.device-management';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->with('employee')
                    ->whereIn('role', [
                        UserRole::Director->value,
                        UserRole::ProjectHead->value,
                        UserRole::CenterManager->value,
                        UserRole::Employee->value,
                    ])
            )
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('User')
                    ->description(fn (User $record): string => (string) $record->login_id)
                    ->searchable(['name', 'login_id'])
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Role')
                    ->formatStateUsing(fn (?string $state): string => UserRole::tryFromMixed($state)->label())
                    ->sortable(),
                TextColumn::make('employee.mobile')
                    ->label('Mobile')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('active_mobile_device_id')
                    ->label('Device ID')
                    ->placeholder('Not registered')
                    ->copyable()
                    ->wrap(),
                TextColumn::make('device_status')
                    ->label('Status')
                    ->state(fn (User $record): string => $record->hasRegisteredMobileDevice() ? 'Registered' : 'Not registered')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Registered' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        UserRole::Director->value => UserRole::Director->label(),
                        UserRole::ProjectHead->value => UserRole::ProjectHead->label(),
                        UserRole::CenterManager->value => UserRole::CenterManager->label(),
                        UserRole::Employee->value => UserRole::Employee->label(),
                    ]),
                SelectFilter::make('device_status')
                    ->label('Status')
                    ->options([
                        'registered' => 'Registered',
                        'not_registered' => 'Not registered',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'registered' => $query->whereNotNull('active_mobile_device_id')->where('active_mobile_device_id', '!=', ''),
                            'not_registered' => $query->where(function (Builder $inner): void {
                                $inner->whereNull('active_mobile_device_id')
                                    ->orWhere('active_mobile_device_id', '');
                            }),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                Action::make('resetDevice')
                    ->label('Reset Device')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reset Device')
                    ->modalDescription('This signs the current device out. The next successful mobile login will register the new device automatically.')
                    ->modalSubmitActionLabel('Reset Device')
                    ->visible(fn (User $record): bool => $record->hasRegisteredMobileDevice())
                    ->action(function (User $record): void {
                        app(MobileSessionService::class)->resetDevice($record);

                        Notification::make()
                            ->title('Device reset')
                            ->body('The next mobile login will register a new device.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
