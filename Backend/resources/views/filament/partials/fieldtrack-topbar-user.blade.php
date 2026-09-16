@php
    $user = auth()->user();
    $name = $user?->name ?? 'User';
    $role = $user && method_exists($user, 'roleEnum') ? ($user->roleEnum()->label() ?? '') : '';
    $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $first = $words[0] ?? 'U';
    $second = $words[1] ?? '';
    $initials = mb_strtoupper(
        mb_substr($first, 0, 1).(filled($second) ? mb_substr($second, 0, 1) : mb_substr($first, 1, 1))
    );
@endphp

<div class="ft-topbar-user">
    <button type="button" class="ft-topbar-bell" aria-label="Notifications" title="No new notifications">
        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M10 2.4a5 5 0 0 0-5 5v1.7c0 .7-.2 1.3-.6 1.9L3.5 12.7A1.2 1.2 0 0 0 4.5 14.6h11a1.2 1.2 0 0 0 1-1.9l-.9-1.7a3.4 3.4 0 0 1-.6-1.9V7.4a5 5 0 0 0-5-5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
            <path d="M8 14.6a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
    </button>
    <div class="ft-topbar-avatar" aria-hidden="true">{{ $initials }}</div>
    <div class="ft-topbar-identity">
        <span class="ft-topbar-name">{{ $name }}</span>
        @if (filled($role))
            <span class="ft-topbar-role">{{ $role }}</span>
        @endif
    </div>
</div>
