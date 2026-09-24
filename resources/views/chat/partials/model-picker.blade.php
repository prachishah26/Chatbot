{{--
    Model picker, grouped by provider. Built on <details> so it opens without
    JavaScript; each option is a form post, and the server only accepts models
    named in configuration.
--}}
<details class="relative" data-menu>
    <summary
        class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg border border-edge bg-panel/60 px-2.5 py-1.5 text-xs font-medium text-muted transition hover:border-accent/40 hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
        <span class="size-1.5 shrink-0 rounded-full bg-accent shadow-none"></span>
        <span class="max-w-[9rem] truncate">{{ $currentChoice->label }}</span>
        <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </summary>

    <div class="absolute right-0 z-40 mt-2 w-64 overflow-hidden rounded-xl border border-edge bg-panel p-1 shadow-xl shadow-black/10">
        @foreach ($modelGroups as $provider => $choices)
            <p @class([
                'flex items-center justify-between px-2.5 pb-1 text-[10px] font-semibold tracking-wider text-faint uppercase',
                'pt-1.5' => $loop->first,
                'mt-1 border-t border-edge/60 pt-2.5' => ! $loop->first,
            ])>
                <span>{{ $choices[0]->providerLabel() }}</span>
                <span class="font-medium normal-case tracking-normal">{{ $choices[0]->isLocal() ? 'Local' : 'Cloud' }}</span>
            </p>

            @foreach ($choices as $option)
                <form method="POST" action="{{ route('chat.model.store') }}">
                    @csrf
                    <input type="hidden" name="model" value="{{ $option->id }}">
                    <button type="submit"
                        @class([
                            'flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm transition',
                            'bg-panel text-ink' => $option->id === $currentChoice->id,
                            'text-muted hover:bg-panel/60 hover:text-ink' => $option->id !== $currentChoice->id,
                        ])>
                        <span class="min-w-0 flex-1 truncate">{{ $option->label }}</span>

                        @if ($option->id === $currentChoice->id)
                            <svg class="size-4 shrink-0 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m20 6-11 11-5-5"/>
                            </svg>
                        @endif
                    </button>
                </form>
            @endforeach
        @endforeach

        <p class="mt-1 border-t border-edge/60 px-2.5 pt-2 pb-1.5 text-[11px] leading-snug text-faint">
            @if ($currentChoice->isLocal())
                Runs locally on this machine with {{ $currentChoice->providerLabel() }}.
            @else
                Sent to {{ $currentChoice->providerLabel() }}'s API. Falls back to the next model if this one runs out of its daily free quota.
            @endif
        </p>
    </div>
</details>
