@extends('layouts.app')

@section('title', $conversation->title ?? config('app.name'))

@section('content')
<div class="flex h-full">
    @include('chat.partials.sidebar')

    <div class="app-canvas flex h-full min-w-0 flex-1 flex-col">

        <header class="flex h-14 shrink-0 items-center gap-2 px-3">
            <button type="button" data-sidebar-open aria-label="Open sidebar"
                class="rounded-lg p-1.5 text-muted transition hover:bg-panel hover:text-ink md:hidden">
                <x-icon-panel />
            </button>
            <div class="ml-auto flex items-center gap-2">
                @include('chat.partials.model-picker', [
                    'currentLabel' => collect($modelOptions)->firstWhere('id', $currentModel)?->label ?? $currentModel,
                ])

                <form method="POST" action="{{ route('chat.conversations.store') }}" class="md:hidden">
                    @csrf
                    <button type="submit" aria-label="New chat"
                        class="rounded-lg p-1.5 text-muted transition hover:bg-panel hover:text-ink">
                        <x-icon-pencil />
                    </button>
                </form>
            </div>
        </header>

        {{-- Greeting shown until the thread has its first message. --}}
        <div data-chat-empty @class(['flex min-h-0 flex-1 flex-col items-center justify-center px-4', 'hidden' => $messages !== []])>
            <span class="mb-5 grid size-14 place-items-center rounded-2xl bg-gradient-to-br from-accent to-accent-deep text-white shadow-[0_6px_20px_-8px_var(--color-accent)]">
                <x-icon-mark class="size-7" />
            </span>
            <h1 class="text-center text-[28px] font-semibold tracking-tight sm:text-[32px]">
                What&rsquo;s on your mind today?
            </h1>
            <p class="mt-2 mb-7 text-center text-sm text-faint">
                {{ config('app.name') }} keeps the thread in mind, so you can just keep talking.
            </p>

            <div class="w-full" data-composer-slot="empty">
                @if ($messages === [])
                    @include('chat.partials.composer')
                @endif
            </div>

            {{-- Starter prompts: a fresh random subset of config('chatbot.suggestions') each load. --}}
            <div class="mt-3 flex max-w-2xl flex-wrap justify-center gap-2">
                @foreach ($suggestions as $suggestion)
                    <button type="button" data-chat-suggestion="{{ $suggestion }}"
                        class="rounded-full border border-edge bg-panel/50 px-3.5 py-1.5 text-sm text-muted transition hover:border-accent/40 hover:bg-panel hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                        {{ $suggestion }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Conversation. Scrolls the full column so replies read edge to edge. --}}
        <div data-chat-log @class(['min-h-0 flex-1 overflow-y-auto scroll-smooth', 'hidden' => $messages === []])
            role="log" aria-live="polite" aria-label="Conversation">
            <div data-chat-thread class="mx-auto max-w-3xl space-y-1 px-4 pt-2 pb-6"></div>
        </div>

        <div data-chat-error class="mx-auto hidden w-full max-w-3xl px-4 pb-2" role="alert">
            <p class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700"></p>
        </div>

        <div data-composer-slot="thread" @class(['shrink-0 pb-1', 'hidden' => $messages === []])>
            @if ($messages !== [])
                @include('chat.partials.composer')
            @endif
        </div>
    </div>
</div>

<script type="application/json" data-chat-history>@json($messages)</script>
@endsection
