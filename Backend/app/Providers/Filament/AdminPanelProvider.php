<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Auth\LoginResponse;
use App\Filament\Auth\LogoutResponse;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\EnsureAdminCenterSelected;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\PreventAdminPageCache;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
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
            ->profile(EditProfile::class, isSimple: false)
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
            ->globalSearch(false)
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
                PreventAdminPageCache::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordChanged::class,
                EnsureAdminCenterSelected::class,
            ]);

        $panel = $this->applyOptionalPanelMethods($panel);

        return $this->registerFieldTrackRenderHooks($panel);
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
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
        $userHook = $this->firstExistingRenderHook(['USER_MENU_BEFORE', 'TOPBAR_END']);
        $sidebarNavHook = $this->firstExistingRenderHook(['SIDEBAR_NAV_START']);

        if ($this->renderHookExists('HEAD_END')) {
            $panel = $panel->renderHook(
                constant(PanelsRenderHook::class.'::HEAD_END'),
                fn () => view('filament.partials.fieldtrack-admin-theme'),
            );
        }

        if ($userHook !== null) {
            $panel = $panel->renderHook(
                constant(PanelsRenderHook::class.'::'.$userHook),
                fn () => view('filament.partials.fieldtrack-topbar-user'),
            );
        }

        if ($sidebarNavHook !== null) {
            $panel = $panel->renderHook(
                constant(PanelsRenderHook::class.'::'.$sidebarNavHook),
                fn () => view('filament.partials.fieldtrack-main-dashboard-nav'),
            );
        }

        if ($this->renderHookExists('HEAD_END')) {
            $panel = $panel->renderHook(
                constant(PanelsRenderHook::class.'::HEAD_END'),
                fn () => view('filament.partials.fieldtrack-login-theme'),
                scopes: Login::class,
            );
        }

        if ($this->renderHookExists('SIMPLE_LAYOUT_START')) {
            $panel = $panel->renderHook(
                constant(PanelsRenderHook::class.'::SIMPLE_LAYOUT_START'),
                fn () => view('filament.auth.login-brand-panel'),
                scopes: Login::class,
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
