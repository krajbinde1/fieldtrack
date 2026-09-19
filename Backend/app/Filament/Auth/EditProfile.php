<?php

namespace App\Filament\Auth;

use App\Filament\Pages\AccountSettings;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    protected static ?string $title = 'Profile';

    protected static bool $isDiscovered = false;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        if (! AccountSettings::canAccess()) {
            return [];
        }

        return [
            Action::make('accountSettings')
                ->label('Account Settings')
                ->url(AccountSettings::getUrl()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getLoginIdFormComponent(),
                $this->getRoleFormComponent(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->getUser();
        $data['role_label'] = method_exists($user, 'roleEnum')
            ? $user->roleEnum()->label()
            : (string) ($user->role ?? '');

        return $data;
    }

    protected function getLoginIdFormComponent(): Component
    {
        return TextInput::make('login_id')
            ->label('Login ID')
            ->disabled()
            ->dehydrated(false);
    }

    protected function getRoleFormComponent(): Component
    {
        return TextInput::make('role_label')
            ->label('Role')
            ->disabled()
            ->dehydrated(false);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [
            'name' => $data['name'] ?? $this->getUser()->name,
        ];
    }
}
