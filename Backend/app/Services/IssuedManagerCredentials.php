<?php

namespace App\Services;

class IssuedManagerCredentials
{
    /**
     * Plain passwords for Center Managers created during the current request.
     * They are shown once and are not stored on the Livewire component.
     *
     * @var list<array{login_id: string, password: string}>
     */
    private array $issued = [];

    public function record(string $loginId, string $password): void
    {
        $this->issued[] = [
            'login_id' => $loginId,
            'password' => $password,
        ];
    }

    public function clear(): void
    {
        $this->issued = [];
    }

    public function notify(): bool
    {
        $issued = $this->issued;
        $this->issued = [];

        if ($issued === []) {
            return false;
        }

        foreach ($issued as $credential) {
            CenterManagerAccountService::credentialNotification(
                $credential['login_id'],
                $credential['password'],
            )->send();
        }

        return true;
    }
}
