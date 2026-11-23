<div class="ft-note-list">
    @forelse ($this->items() as $notification)
        <button type="button" class="ft-note {{ $notification->read_at ? '' : 'is-unread' }}" wire:click="openNotification({{ $notification->id }})">
            <strong>{{ $notification->title }}</strong>
            <span>{{ $notification->body }}</span>
            <small>{{ $notification->created_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</small>
        </button>
    @empty
        <p class="ft-note-empty">No notifications yet.</p>
    @endforelse
</div>

<style>
    .ft-note-list { display: flex; flex-direction: column; gap: 0.5rem; max-width: 42rem; }
    .ft-note {
        text-align: left;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.6rem;
        background: white;
        padding: 0.7rem 0.8rem;
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }
    .ft-note.is-unread { border-color: rgb(79 70 229); }
    .ft-note span, .ft-note small { color: rgb(71 85 105); white-space: pre-line; }
    .ft-note-empty { color: rgb(107 114 128); }
</style>
