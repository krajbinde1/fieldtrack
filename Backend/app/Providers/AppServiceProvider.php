<?php

namespace App\Providers;

use App\Services\OrganizationAccessService;
use Filament\Support\Enums\Width;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OrganizationAccessService::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            $root = rtrim((string) config('app.url'), '/');
            if ($root !== '') {
                URL::forceRootUrl($root);
            }
        }

        config([
            'livewire.temporary_file_upload.rules' => ['file', 'max:122880'],
        ]);

        Table::configureUsing(function (Table $table): void {
            $table
                ->persistFiltersInSession()
                ->persistSearchInSession()
                ->persistColumnSearchesInSession()
                ->persistSortInSession()
                ->filtersFormWidth(Width::Small)
                ->filtersFormMaxHeight('70vh');
        });
    }
}
