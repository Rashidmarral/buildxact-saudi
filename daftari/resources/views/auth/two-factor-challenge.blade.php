@extends('layouts.auth')

@section('title', __('Two-factor verification'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Two-factor verification') }}</h1>
<p class="mt-2 text-sm text-slate-500">{{ __('Enter the 6-digit code from your authenticator app.') }}</p>

<form method="POST" action="{{ route('two-factor.verify') }}" class="mt-6 space-y-5">
    @csrf
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('6-digit code') }}</label>
        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" autofocus class="mt-1 w-full rounded-lg border border-slate-200 text-center text-lg tracking-[0.3em] focus:border-brand-500 focus:ring-brand-500">
    </div>
    <button type="submit" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Verify') }}</button>
</form>

<details class="mt-6">
    <summary class="cursor-pointer text-sm font-semibold text-slate-500 hover:text-brand-700">{{ __("Can't access your authenticator app?") }}</summary>
    <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-4 space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Recovery code') }}</label>
            <input type="text" name="recovery_code" autocomplete="off" class="mt-1 w-full rounded-lg border border-slate-200 font-mono focus:border-brand-500 focus:ring-brand-500">
        </div>
        <button type="submit" class="w-full rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Verify with recovery code') }}</button>
    </form>
</details>

<p class="mt-6 text-center text-sm text-slate-500">
    <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Back to log in') }}</a>
</p>
@endsection
