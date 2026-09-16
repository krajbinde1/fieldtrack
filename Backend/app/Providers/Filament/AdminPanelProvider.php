<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Auth\LoginResponse;
use App\Filament\Pages\Dashboard;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName('Param FieldTrack')
            ->maxContentWidth(Width::Full)
            ->colors([
                'primary' => Color::Violet,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Blue,
                'orange' => Color::Orange,
            ])
            ->navigationGroups([
                'Organization',
                'People',
                'Field Operations',
                'Admissions',
                'Leave',
                'Reports',
                'System',
            ])
            ->navigation(fn (): bool => ! request()->routeIs('filament.admin.resources.employee-routes.view'))
            ->topbar(fn (): bool => ! request()->routeIs('filament.admin.resources.employee-routes.view'))
            ->breadcrumbs(fn (): bool => ! request()->routeIs('filament.admin.resources.employee-routes.view'))
            ->homeUrl(fn (): string => Dashboard::getUrl())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        $panel = $this->applyOptionalPanelMethods($panel);

        return $this->registerFieldTrackRenderHooks($panel);
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
    }

    private function applyOptionalPanelMethods(Panel $panel): Panel
    {
        if (method_exists($panel, 'darkMode')) {
            $panel = $panel->darkMode(false);
        }

        if (method_exists($panel, 'sidebarCollapsibleOnDesktop')) {
            $panel = $panel->sidebarCollapsibleOnDesktop();
        }

        return $panel;
    }

    private function registerFieldTrackRenderHooks(Panel $panel): Panel
    {
        $searchHook = $this->firstExistingRenderHook(['TOPBAR_LOGO_AFTER', 'TOPBAR_START', 'TOPBAR_END']);
        $userHook = $this->firstExistingRenderHook(['USER_MENU_BEFORE', 'TOPBAR_END']);

        if ($this->renderHookExists('HEAD_END')) {
            $panel = $panel->renderHook(
                constant(PanelsRenderHook::class.'::HEAD_END'),
                fn () => view('filament.partials.fieldtrack-admin-theme'),
            );
        }

        if ($searchHook !== null) {
            $panel = $panel->renderHook(
                constant(PanelsRenderHook::class.'::'.$searchHook),
                fn () => view('filament.partials.fieldtrack-topbar-search'),
            );
        }

        if ($userHook !== null) {
            $panel = $panel->renderHook(
                constant(PanelsRenderHook::class.'::'.$userHook),
                fn () => view('filament.partials.fieldtrack-topbar-user'),
            );
        }

        return $panel;
    }

    /**
     * @param  list<string>  $names
     */
    private function firstExistingRenderHook(array $names): ?string
    {
        foreach ($names as $name) {
            if ($this->renderHookExists($name)) {
                return $name;
            }
        }

        return null;
    }

    private function renderHookExists(string $name): bool
    {
        return defined(PanelsRenderHook::class.'::'.$name);
    }
}
