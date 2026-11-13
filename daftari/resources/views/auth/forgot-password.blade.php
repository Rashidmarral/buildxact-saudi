@extends('layouts.auth')

@section('title', __('Forgot password'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Reset your password') }}</h1>
<p class="mt-2 text-sm text-slate-500">{{ __("Enter your account email and we'll send you a link to reset your password.") }}</p>

@if (session('status'))
    <div class="mt-4 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">{{ session('status') }}</div>
@endif

@if (session('dev_reset_url'))
    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
        <p class="font-semibold">{{ __('Development mode: no email transport is configured, so here is the reset link directly.') }}</p>
        <a href="{{ session('dev_reset_url') }}" class="mt-1 block break-all font-mono text-amber-900 underline">{{ session('dev_reset_url') }}</a>
    </div>
@endif

<form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
    @csrf
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <button type="submit" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Send reset link') }}</button>
</form>

<p class="mt-6 text-center text-sm text-slate-500">
    <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Back to log in') }}</a>
</p>
@endsection
