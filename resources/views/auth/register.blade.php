@extends('layouts.auth')

@section('title', 'Create account')
@section('heading', 'Create your account')
@section('subheading', 'Keep your chat history across browsers and devices.')

@section('form')
<form method="POST" action="{{ route('register.store') }}" class="space-y-4">
    @csrf

    <x-auth-field name="name" label="Name" autocomplete="name" :autofocus="true" />
    <x-auth-field name="email" label="Email" type="email" autocomplete="email" />
    <x-auth-field name="password" label="Password" type="password" autocomplete="new-password" />
    <x-auth-field name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />

    <button type="submit"
        class="w-full rounded-xl bg-gradient-to-br from-accent to-accent-deep px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2">
        Create account
    </button>
</form>
@endsection

@section('switch')
    Already have an account?
    <a href="{{ route('login') }}" class="font-medium text-accent underline-offset-2 hover:underline">Sign in</a>
@endsection
