<?php

namespace App\Filament\Auth;

use App\Filament\Pages\SelectCenter;
use App\Models\User;
use App\Services\AdminCenterContext;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = $request->user();

        if ($user instanceof User && $user->isAdmin() && ! app(AdminCenterContext::class)->hasValidSelection($user)) {
            return redirect()->to(SelectCenter::getUrl());
        }

        return redirect()->intended(Filament::getUrl());
    }
}
