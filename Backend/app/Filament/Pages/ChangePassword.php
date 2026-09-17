<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class ChangePassword extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Change Password';

    protected static ?string $slug = 'change-password';

    protected string $view = 'filament.pages.change-password';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        abort_unless(Filament::auth()->check(), 403);
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Change password')
                    ->schema([
                        TextInput::make('currentPassword')
                            ->label('Current password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword(guard: Filament::getAuthGuard())
                            ->autocomplete('current-password')
                            ->dehydrated(false),
                        TextInput::make('password')
                            ->label('New password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::default())
                            ->autocomplete('new-password')
                            ->same('passwordConfirmation'),
                        TextInput::make('passwordConfirmation')
                            ->label('Confirm new password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->autocomplete('new-password')
                            ->dehydrated(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(Filament::auth()->check(), 403);

        $data = $this->form->getState();
        $user = Filament::auth()->user();
        $user->forceFill([
            'password' => $data['password'],
        ])->save();

        if (request()->hasSession()) {
            request()->session()->put([
                'password_hash_'.Filament::getAuthGuard() => $user->getAuthPassword(),
            ]);
        }

        $this->form->fill();

        Notification::make()
            ->title('Password updated')
            ->success()
            ->send();
    }
}
