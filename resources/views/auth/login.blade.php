@extends('layouts.auth')

@section('title', 'Sign in')
@section('heading', 'Welcome back')
@section('subheading', 'Your chats are waiting, on any device.')

@section('form')
<form method="POST" action="{{ route('login.store') }}" class="space-y-4">
    @csrf

    <x-auth-field name="email" label="Email" type="email" autocomplete="email" :autofocus="true" />
    <x-auth-field name="password" label="Password" type="password" autocomplete="current-password" />

    <label class="flex items-center gap-2 text-sm text-muted">
        <input type="checkbox" name="remember" value="1"
            class="size-4 rounded border-edge text-accent focus:ring-accent">
        Keep me signed in
    </label>

    <button type="submit"
        class="w-full rounded-xl bg-gradient-to-br from-accent to-accent-deep px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2">
        Sign in
    </button>
</form>
@endsection

@section('switch')
    New here?
    <a href="{{ route('register') }}" class="font-medium text-accent underline-offset-2 hover:underline">Create an account</a>
@endsection
