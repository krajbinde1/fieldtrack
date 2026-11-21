<?php

namespace App\Filament\Concerns;

trait HoldsPendingCenterAccounts
{
    /**
     * Coordinators typed into the center form. They are inserted only when the center saves.
     *
     * @var array<string, array{name: string, mobile: string, email: string}>
     */
    public array $pendingCoordinators = [];

    /**
     * Center managers typed into the center form. They are inserted only when that assignment saves.
     *
     * @var array<string, array{name: string, mobile: string, email: string}>
     */
    public array $pendingManagers = [];
}
