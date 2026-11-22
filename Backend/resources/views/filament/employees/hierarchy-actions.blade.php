@if ($person['editUrl'])
    <a href="{{ $person['editUrl'] }}" title="Edit" @if ($menu) aria-label="Edit" @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16.5 3.75l3.75 3.75M4.5 19.5l.7-3.4L16.2 5.1a1.5 1.5 0 012.1 0l.6.6a1.5 1.5 0 010 2.1L7.9 18.8 4.5 19.5z"/></svg>
        @if ($menu) <span>Edit</span> @endif
    </a>
@endif
@if ($person['canReset'])
    <button type="button" title="Reset Password" wire:click="resetHierarchyPassword('{{ $person['kind'] }}', {{ $person['id'] }})" wire:confirm="Reset the password to the last 4 digits of the registered mobile number?">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15.75 5.25a4.5 4.5 0 11-6.36 6.36L4.5 16.5V19.5H7.5l4.89-4.89"/></svg>
        @if ($menu) <span>Reset Password</span> @endif
    </button>
@endif
@if ($person['canDelete'])
    <button type="button" class="is-danger" title="Delete" wire:click="deleteHierarchyUser('{{ $person['kind'] }}', {{ $person['id'] }})" wire:confirm="Permanently delete this user? This cannot be undone. Users with related records cannot be deleted.">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 7.5h12M9.75 7.5V5.25h4.5V7.5M8.25 7.5l.75 12h6l.75-12"/></svg>
        @if ($menu) <span>Delete</span> @endif
    </button>
@endif
@if ($person['canToggle'])
    <button type="button" title="{{ $person['active'] ? 'Deactivate' : 'Activate' }}" wire:click="toggleHierarchyActive('{{ $person['kind'] }}', {{ $person['id'] }})">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3.75v6.5M7.5 6.4a7.5 7.5 0 109 0"/></svg>
        @if ($menu) <span>{{ $person['active'] ? 'Deactivate' : 'Activate' }}</span> @endif
    </button>
@endif
