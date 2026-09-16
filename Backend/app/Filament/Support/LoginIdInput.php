<?php

namespace App\Filament\Support;

use App\Models\Employee;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                return self::uniqueRules($record, 32);
            });
    }

    public static function fromEmail(): TextInput
    {
        return TextInput::make('login_id')
            ->label('Login ID')
            ->maxLength(255)
            ->helperText('Defaults to Email. You can enter a custom Login ID. Must be unique.')
            ->rules(function ($record): array {
                return self::uniqueRules($record, 255);
            });
    }

    public static function emailWithLoginIdSync(): TextInput
    {
        return TextInput::make('email')
            ->label('Email')
            ->email()
            ->required()
            ->unique(ignoreRecord: true)
            ->live(onBlur: true)
            ->afterStateUpdated(function (?string $state, Set $set, Get $get, mixed $old): void {
                $loginId = trim((string) $get('login_id'));
                $previousEmail = trim((string) $old);
                if ($loginId === '' || strcasecmp($loginId, $previousEmail) === 0) {
                    $set('login_id', trim((string) $state));
                }
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

    /**
     * @return list<mixed>
     */
    private static function uniqueRules(mixed $record, int $max): array
    {
        $ignoreId = null;
        if ($record instanceof User) {
            $ignoreId = $record->id;
        } elseif ($record instanceof Employee) {
            $ignoreId = $record->user?->id;
        }

        return [
            'nullable',
            'string',
            'max:'.$max,
            Rule::unique('users', 'login_id')->ignore($ignoreId),
        ];
    }
}
