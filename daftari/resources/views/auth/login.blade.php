@extends('layouts.auth')

@section('title', __('Log in'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Log in to Daftari') }}</h1>

@if (session('status'))
    <div class="mt-4 rounded-lg bg-brand-50 border border-brand-200 text-brand-800 px-4 py-3 text-sm">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
    @csrf
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <div class="flex items-center justify-between">
            <label class="block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
            <a href="{{ route('password.request') }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Forgot password?') }}</a>
        </div>
        <input type="password" name="password" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        {{ __('Remember me') }}
    </label>
    <button type="submit" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Log in') }}</button>
</form>

<p class="mt-6 text-center text-sm text-slate-500">
    {{ __("Don't have an account?") }}
    <a href="{{ route('register') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Start a free trial') }}</a>
</p>

<div class="mt-6 pt-6 border-t border-slate-100 text-xs text-slate-400 space-y-1">
    <p class="font-semibold text-slate-500">{{ __('Demo logins') }}</p>
    <p>{{ __('Platform admin') }}: admin@daftari.local / Admin@12345</p>
    <p>{{ __('Demo company owner') }}: owner@daftari.local / Demo@12345</p>
</div>
@endsection
