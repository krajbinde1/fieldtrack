<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\LoginId;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccountSettings extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Account Settings';

    protected static ?string $slug = 'account-settings';

    protected string $view = 'filament.pages.account-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdminOrDirector() === true;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'login_id' => Filament::auth()->user()?->login_id,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $userId = Filament::auth()->id();

        return $schema
            ->components([
                Section::make('Account Settings')
                    ->description('Update this account’s Login ID and password. The current password is required to save.')
                    ->schema([
                        TextInput::make('login_id')
                            ->label('Login ID')
                            ->required()
                            ->maxLength(255)
                            ->autocomplete('username')
                            ->rules([
                                'required',
                                'string',
                                'max:255',
                                Rule::unique('users', 'login_id')->ignore($userId),
                            ]),
                        TextInput::make('currentPassword')
                            ->label('Current Password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword(guard: Filament::getAuthGuard())
                            ->autocomplete('current-password')
                            ->dehydrated(false),
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->nullable()
                            ->rule(Password::default())
                            ->same('passwordConfirmation')
                            ->autocomplete('new-password')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('passwordConfirmation')
                            ->label('Confirm Password')
                            ->password()
                            ->revealable()
                            ->requiredWith('password')
                            ->autocomplete('new-password')
                            ->dehydrated(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        abort_unless(static::canAccess(), 403);

        /** @var User $user */
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        $data = $this->form->getState();
        $loginId = trim((string) ($data['login_id'] ?? ''));
        $password = $data['password'] ?? null;
        $loginChanged = $loginId !== (string) $user->login_id;
        $passwordChanged = filled($password);

        if (! $loginChanged && ! $passwordChanged) {
            throw ValidationException::withMessages([
                'data.login_id' => 'Enter a new Login ID or Password.',
            ]);
        }

        try {
            LoginId::assertUnique($loginId, (int) $user->id);
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'data.login_id' => 'This Login ID is already taken.',
            ]);
        }

        $payload = ['login_id' => $loginId];
        if ($passwordChanged) {
            $payload['password'] = $password;
        }

        $user->forceFill($payload)->save();

        Filament::auth()->logout();
        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        return redirect()->to('/admin/login');
    }
}
