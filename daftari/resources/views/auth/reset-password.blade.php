@extends('layouts.auth')

@section('title', __('Reset password'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Choose a new password') }}</h1>

<form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
        <input type="email" name="email" value="{{ old('email', $email) }}" required autofocus class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('New password') }}</label>
        <input type="password" name="password" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        <p class="mt-1 text-xs text-slate-400">{{ __('At least 8 characters.') }}</p>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Confirm new password') }}</label>
        <input type="password" name="password_confirmation" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <button type="submit" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Reset password') }}</button>
</form>

<p class="mt-6 text-center text-sm text-slate-500">
    <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Back to log in') }}</a>
</p>
@endsection
