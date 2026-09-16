<?php

namespace App\Filament\Concerns;

trait RedirectsToPreviousPageAfterSave
{
    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? static::getResource()::getUrl('index');
    }
}
