{{--
    Foot of the thread rail. Signing in is what moves a visitor's history off
    this one browser, so the prompt lives next to the history it protects.
--}}
<div class="border-t border-edge/60 px-3 py-3">
    @auth
        <div class="flex items-center gap-2.5 rounded-xl px-1.5 py-1">
            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-accent to-accent-deep text-[13px] font-semibold text-white uppercase">
                {{ mb_substr(auth()->user()->shortName(), 0, 1) }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-ink">{{ auth()->user()->name }}</p>
                <p class="truncate text-[11px] text-faint">{{ auth()->user()->email }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" aria-label="Sign out" title="Sign out"
                    class="rounded-lg p-1.5 text-muted transition hover:bg-panel hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <x-icon-signout />
                </button>
            </form>
        </div>
    @else
        <a href="{{ route('login') }}"
            class="flex w-full items-center justify-center rounded-xl border border-edge bg-panel px-3 py-2 text-sm font-medium text-ink transition hover:border-accent/40 hover:bg-panel-hi focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
            Sign in
        </a>
        <p class="px-1 pt-2 text-[11px] leading-relaxed text-faint">
            Chats stay in this browser until you
            <a href="{{ route('register') }}" class="text-accent underline-offset-2 hover:underline">create an account</a>.
        </p>
    @endauth

    <p class="mt-3 px-1 text-[11px] text-faint">
        <span class="inline-flex items-center gap-1.5">
            <span class="size-1.5 rounded-full bg-accent"></span>
            Powered by {{ $currentChoice->providerLabel() }}
        </span>
    </p>
</div>
