<?php

namespace App\Filament\Auth;

use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login_id')
            ->label('Login ID')
            ->required()
            ->maxLength(255)
            ->autocomplete()
            ->autofocus();
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'login_id' => $data['login_id'],
            'password' => $data['password'],
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        if (! $authGuard->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        /** @var User $user */
        $user = $authGuard->user();

        if ($user instanceof FilamentUser && ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            $authGuard->logout();

            throw ValidationException::withMessages([
                'data.login_id' => 'This account cannot access the web application.',
            ]);
        }

        if ($user->is_active !== true) {
            $authGuard->logout();

            throw ValidationException::withMessages([
                'data.login_id' => 'This account is inactive.',
            ]);
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login_id' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
