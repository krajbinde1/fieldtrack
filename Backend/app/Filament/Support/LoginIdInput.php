<?php

namespace App\Filament\Support;

use App\Models\Employee;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Illuminate\Validation\Rule;

final class LoginIdInput
{
    public static function make(): TextInput
    {
        return TextInput::make('login_id')
            ->label('Login ID')
            ->maxLength(32)
            ->helperText('Optional. Leave blank to use the mobile number. Must be unique.')
            ->rules(function ($record): array {
                $ignoreId = null;
                if ($record instanceof User) {
                    $ignoreId = $record->id;
                } elseif ($record instanceof Employee) {
                    $ignoreId = $record->user?->id;
                }

                return [
                    'nullable',
                    'string',
                    'max:32',
                    Rule::unique('users', 'login_id')->ignore($ignoreId),
                ];
            });
    }

    public static function mobileFallback(): TextInput
    {
        return TextInput::make('mobile')
            ->label('Mobile Number')
            ->tel()
            ->maxLength(15)
            ->requiredWithout('login_id')
            ->dehydrated(false)
            ->visibleOn('create')
            ->helperText('Required if Login ID is blank. Used as the Login ID when Login ID is empty.');
    }
}
