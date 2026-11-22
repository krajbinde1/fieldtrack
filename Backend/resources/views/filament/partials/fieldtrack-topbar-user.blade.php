@php
use App\Filament\Pages\AccountSettings;
use App\Filament\Pages\ChangePassword;
use App\Filament\Pages\SelectCenter;
use App\Services\AdminCenterContext;
use Filament\Facades\Filament;

    $user = Filament::auth()->user() ?? auth()->user();
    $name = $user?->name ?? 'User';
    $role = $user && method_exists($user, 'roleEnum') ? ($user->roleEnum()->label() ?? '') : '';
    $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $first = $words[0] ?? 'U';
    $second = $words[1] ?? '';
    $initials = mb_strtoupper(
        mb_substr($first, 0, 1).(filled($second) ? mb_substr($second, 0, 1) : mb_substr($first, 1, 1))
    );
    $profileUrl = Filament::getProfileUrl();
    $accountSettingsUrl = AccountSettings::canAccess() ? AccountSettings::getUrl() : null;
    $changePasswordUrl = ChangePassword::getUrl();
    $logoutUrl = Filament::getLogoutUrl();
    $selectedCenter = $user ? app(AdminCenterContext::class)->centerFor($user) : null;
    $changeCenterUrl = $selectedCenter ? SelectCenter::getUrl() : null;
    $isCoordinator = $user instanceof \App\Models\User && $user->isCenterCoordinator();
    $coordinatorCenterCount = $isCoordinator
        ? count(app(\App\Services\OrganizationAccessService::class)->visibleCenterIds($user) ?? [])
        : 0;
@endphp

@if ($user)
<div class="ft-topbar-user" data-ft-topbar-user>
    @if ($selectedCenter && filled($changeCenterUrl) && ! $isCoordinator)
        <div class="ft-change-center">
            <span class="ft-change-center-name">{{ $selectedCenter->name }}</span>
            <span class="ft-change-center-actions">
                <a class="ft-change-center-action" href="{{ $changeCenterUrl }}">Change Center</a>
                <form class="ft-clear-center" method="POST" action="{{ route('admin.clear-center') }}">
                    @csrf
                    <button type="submit" class="ft-change-center-action">Back to All Centers</button>
                </form>
            </span>
        </div>
    @elseif ($isCoordinator && $selectedCenter)
        <div class="ft-change-center">
            <span class="ft-change-center-name">{{ $selectedCenter->name }}</span>
            <span class="ft-change-center-actions">
                @if ($coordinatorCenterCount > 1)
                    <a class="ft-change-center-action" href="{{ $changeCenterUrl }}">Change Center</a>
                @endif
                <form @class(['ft-clear-center' => $coordinatorCenterCount > 1]) method="POST" action="{{ route('admin.clear-center') }}">
                    @csrf
                    <button type="submit" class="ft-change-center-action">Back to All Centers</button>
                </form>
            </span>
        </div>
    @endif

    <button type="button" class="ft-topbar-bell" aria-label="Notifications" title="No new notifications">
        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M10 2.4a5 5 0 0 0-5 5v1.7c0 .7-.2 1.3-.6 1.9L3.5 12.7A1.2 1.2 0 0 0 4.5 14.6h11a1.2 1.2 0 0 0 1-1.9l-.9-1.7a3.4 3.4 0 0 1-.6-1.9V7.4a5 5 0 0 0-5-5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
            <path d="M8 14.6a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
    </button>

    <details class="ft-topbar-profile">
        <summary class="ft-topbar-profile-trigger" aria-label="Open account menu">
            <span class="ft-topbar-avatar" aria-hidden="true">{{ $initials }}</span>
            <span class="ft-topbar-identity">
                <span class="ft-topbar-name">{{ $name }}</span>
                @if (filled($role))
                    <span class="ft-topbar-role">{{ $role }}</span>
                @endif
            </span>
        </summary>
        <div class="ft-topbar-menu" role="menu">
            @if (filled($profileUrl))
                <a class="ft-topbar-menu-item" role="menuitem" href="{{ $profileUrl }}">Profile</a>
            @endif
            @if (filled($accountSettingsUrl))
                <a class="ft-topbar-menu-item" role="menuitem" href="{{ $accountSettingsUrl }}">Account Settings</a>
            @endif
            <a class="ft-topbar-menu-item" role="menuitem" href="{{ $changePasswordUrl }}">Change Password</a>
            <form method="POST" action="{{ $logoutUrl }}">
                @csrf
                <button type="submit" class="ft-topbar-menu-item ft-topbar-menu-logout" role="menuitem">Logout</button>
            </form>
        </div>
    </details>
</div>

<script>
    (function () {
        if (window.__ftTopbarMenuGuard) {
            return;
        }
        window.__ftTopbarMenuGuard = true;
        document.addEventListener('click', function (event) {
            document.querySelectorAll('.ft-topbar-profile[open]').forEach(function (el) {
                if (!el.contains(event.target)) {
                    el.removeAttribute('open');
                }
            });
        });
    })();
</script>
@endif
