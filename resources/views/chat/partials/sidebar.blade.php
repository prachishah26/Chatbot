{{--
    Thread rail. Hidden off-canvas on small screens and toggled by the header
    button; a fixed column from `md` upwards.
--}}
<div data-sidebar-backdrop class="fixed inset-0 z-20 hidden bg-black/25 backdrop-blur-[2px] md:hidden"></div>

<aside data-sidebar
    class="fixed inset-y-0 left-0 z-30 flex w-[272px] -translate-x-full flex-col border-r border-edge/60 bg-rail transition-transform duration-200 md:relative md:translate-x-0">

    <div class="flex items-center gap-1 px-3 py-3.5">
        <a href="{{ route('chat.index') }}"
            class="flex flex-1 items-center gap-2.5 rounded-lg px-1.5 py-1 text-sm font-semibold tracking-wide transition hover:opacity-80">
            <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-accent to-accent-deep text-white shadow-sm">
                <x-icon-mark class="size-[18px]" />
            </span>
            <span class="truncate text-[15px] uppercase">{{ config('app.name') }}</span>
        </a>
        <button type="button" data-sidebar-close aria-label="Close sidebar"
            class="rounded-lg p-1.5 text-muted transition hover:bg-panel hover:text-ink md:hidden">
            <x-icon-panel />
        </button>
    </div>

    <div class="px-3">
        <form method="POST" action="{{ route('chat.conversations.store') }}">
            @csrf
            <button type="submit"
                class="flex w-full items-center gap-2.5 rounded-xl border border-edge bg-panel px-3 py-2.5 text-sm font-medium text-ink transition hover:border-accent/40 hover:bg-panel-hi focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                <x-icon-pencil class="size-4 text-accent" />
                New chat
            </button>
        </form>
    </div>

    <nav class="mt-5 min-h-0 flex-1 space-y-4 overflow-y-auto px-3 pb-4" data-conversation-list aria-label="Chat history">
        @foreach ($conversationGroups as $heading => $threads)
            <div data-conversation-group="{{ $heading }}" @class(['hidden' => $threads === []])>
                <p class="px-2 pb-1.5 text-[11px] font-semibold tracking-wider text-faint uppercase">
                    {{ $heading }}
                </p>

                <div data-conversation-group-items class="space-y-0.5">
                    @foreach ($threads as $thread)
                        @include('chat.partials.thread-row', ['thread' => $thread])
                    @endforeach
                </div>
            </div>
        @endforeach

        @php($hasThreads = collect($conversationGroups)->contains(fn (array $threads): bool => $threads !== []))

        <p data-conversation-empty @class(['px-2 pt-2 text-xs text-faint', 'hidden' => $hasThreads])>
            Your chats will appear here.
        </p>
    </nav>

    @include('chat.partials.account')
</aside>
