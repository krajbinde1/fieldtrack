<?php

namespace App\Filament\Concerns;

use App\Support\LoginId;

trait ResolvesOptionalLoginId
{
    protected function resolveLoginIdForCreate(array $data): array
    {
        $data['login_id'] = LoginId::resolve(
            $data['login_id'] ?? null,
            $this->data['mobile'] ?? null,
        );
        LoginId::assertUnique($data['login_id']);

        return $data;
    }

    protected function resolveLoginIdForSave(array $data): array
    {
        $loginId = trim((string) ($data['login_id'] ?? ''));
        if ($loginId === '') {
            unset($data['login_id']);

            return $data;
        }

        LoginId::assertUnique($loginId, $this->record->id);
        $data['login_id'] = $loginId;

        return $data;
    }
}
