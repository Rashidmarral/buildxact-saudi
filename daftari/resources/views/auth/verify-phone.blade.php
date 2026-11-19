@extends('layouts.auth')

@section('title', __('Verify your phone'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Verify your phone number') }}</h1>
<p class="mt-2 text-sm text-slate-500">{{ __('Enter your phone number and we\'ll text you a 6-digit code.') }}</p>

@if (session('status'))
    <div class="mt-4 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">{{ session('status') }}</div>
@endif

@if (! $smsConfigured)
    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
        {{ __('SMS is not configured for your company yet. Ask an admin to set it up under Settings → SMS, or contact support.') }}
    </div>
@endif

<form method="POST" action="{{ route('phone.send-code') }}" class="mt-6 space-y-4">
    @csrf
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Phone number') }}</label>
        <input type="text" name="phone" required value="{{ old('phone', $phone) }}" placeholder="05XXXXXXXX" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <button type="submit" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Send code') }}</button>
</form>

<form method="POST" action="{{ route('phone.verify-code') }}" class="mt-6 space-y-4 border-t border-slate-100 pt-6">
    @csrf
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Verification code') }}</label>
        <input type="text" name="code" required maxlength="6" inputmode="numeric" placeholder="123456" class="mt-1 w-full rounded-lg border border-slate-200 tracking-widest focus:border-brand-500 focus:ring-brand-500">
    </div>
    <button type="submit" class="w-full rounded-lg border border-slate-200 px-6 py-3 font-semibold text-slate-700 hover:border-slate-300">{{ __('Verify') }}</button>
</form>

<form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
    @csrf
    <button type="submit" class="text-sm font-semibold text-slate-500 hover:underline">{{ __('Log out') }}</button>
</form>
@endsection
