{{--
    Shell for the account screens: one centred card on the chat canvas, so
    signing in does not feel like leaving the app.
--}}
@extends('layouts.app')

@section('content')
<div class="app-canvas h-full overflow-y-auto">
    <div class="mx-auto flex min-h-full w-full max-w-sm flex-col justify-center px-5 py-10">

        <a href="{{ route('chat.index') }}" class="mx-auto mb-6 flex flex-col items-center gap-2.5 transition hover:opacity-80">
            <span class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br from-accent to-accent-deep text-white shadow-[0_6px_20px_-8px_var(--color-accent)]">
                <x-icon-mark class="size-6" />
            </span>
            <span class="text-sm font-semibold tracking-wide uppercase">{{ config('app.name') }}</span>
        </a>

        <div class="rounded-2xl border border-edge bg-panel px-6 py-7 shadow-sm">
            <h1 class="text-center text-xl font-semibold tracking-tight">@yield('heading')</h1>
            <p class="mt-1.5 mb-6 text-center text-sm text-faint">@yield('subheading')</p>

            @yield('form')
        </div>

        <p class="mt-5 text-center text-sm text-muted">@yield('switch')</p>

        <a href="{{ route('chat.index') }}" class="mt-2 text-center text-xs text-faint underline-offset-2 transition hover:text-muted hover:underline">
            Continue without an account
        </a>
    </div>
</div>
@endsection
