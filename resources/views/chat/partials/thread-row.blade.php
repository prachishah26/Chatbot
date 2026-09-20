{{-- One sidebar entry. Shared by the initial render and the live JS insert. --}}
<div class="group relative" data-conversation="{{ $thread->public_id }}">
    <a href="{{ route('chat.show', $thread) }}"
        @class([
            'block truncate rounded-lg py-2 pr-9 pl-2.5 text-sm transition',
            'bg-panel font-medium text-ink shadow-[inset_2px_0_0_0_var(--color-accent)]' => $thread->id === $conversation->id,
            'text-muted hover:bg-panel/60 hover:text-ink' => $thread->id !== $conversation->id,
        ])
        data-conversation-title>{{ $thread->displayTitle() }}</a>

    <form method="POST" action="{{ route('chat.conversations.destroy', $thread) }}"
        class="absolute top-1.5 right-1 hidden group-hover:block focus-within:block"
        onsubmit="return confirm('Delete this chat? This cannot be undone.')">
        @csrf
        @method('DELETE')
        <button type="submit" aria-label="Delete chat"
            class="rounded-md p-1.5 text-faint transition hover:bg-panel-hi hover:text-red-400">
            <x-icon-trash />
        </button>
    </form>
</div>
