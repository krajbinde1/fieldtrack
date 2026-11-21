<?php

namespace App\Filament\Widgets\Concerns;

use App\Services\AdminCenterContext;
use Livewire\Attributes\Locked;

/**
 * Main Dashboard sets organizationWide so widget queries ignore the selected
 * center. Other pages leave it false and keep the session center.
 */
trait UsesOrganizationWideDashboardScope
{
    #[Locked]
    public bool $organizationWide = false;

    public function boot(): void
    {
        if ($this->organizationWide) {
            app(AdminCenterContext::class)->beginOrganizationWideScope();
        }
    }

    public function dehydrate(): void
    {
        if ($this->organizationWide) {
            app(AdminCenterContext::class)->endOrganizationWideScope();
        }
    }
}
