<?php

namespace App\Filament\Auth;

use App\Filament\Pages\ChangePassword;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = Filament::auth()->user();
        if ($user instanceof User && $user->must_change_password === true) {
            return redirect()->to(ChangePassword::getUrl());
        }

        return redirect()->intended(Filament::getUrl());
    }
}
