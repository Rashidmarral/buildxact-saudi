@extends('layouts.app')

@section('title', __('Two-factor authentication'))

@section('content')
<div class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6">
    @if ($enabled)
        <div class="flex items-center gap-2 mb-1">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                @include('partials.icon', ['name' => 'check-circle', 'class' => 'h-3.5 w-3.5'])
                {{ __('Enabled') }}
            </span>
        </div>
        <h1 class="text-lg font-semibold text-slate-900">{{ __('Two-factor authentication') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('An authenticator app code is required every time you sign in.') }}</p>

        <div class="mt-6 pt-6 border-t border-slate-100">
            <h2 class="font-semibold text-slate-900 mb-1">{{ __('Recovery codes') }}</h2>
            <p class="text-sm text-slate-500 mb-4">{{ __('Lost your device? Use one of your saved recovery codes to sign in, then generate a fresh set.') }}</p>
            <form method="POST" action="{{ route('app.settings.two-factor.recovery-codes') }}" class="max-w-sm space-y-3">
                @csrf
                <label class="block text-sm font-medium text-slate-700">{{ __('Confirm your password to view new recovery codes') }}</label>
                <input type="password" name="current_password" required placeholder="{{ __('Current password') }}" class="w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Generate new recovery codes') }}</button>
            </form>
        </div>

        <div class="mt-6 pt-6 border-t border-slate-100">
            <h2 class="font-semibold text-slate-900 mb-1">{{ __('Turn off two-factor authentication') }}</h2>
            <p class="text-sm text-slate-500 mb-4">{{ __('This makes your account less secure — anyone with your password alone will be able to sign in.') }}</p>
            <form method="POST" action="{{ route('app.settings.two-factor.disable') }}" class="max-w-sm space-y-3" onsubmit="return confirm('{{ __('Turn off two-factor authentication?') }}')">
                @csrf
                <input type="password" name="current_password" required placeholder="{{ __('Current password') }}" class="w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">{{ __('Turn off') }}</button>
            </form>
        </div>
    @else
        <h1 class="text-lg font-semibold text-slate-900">{{ __('Set up two-factor authentication') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Scan this QR code with an authenticator app (Google Authenticator, Microsoft Authenticator, Authy, ...), then enter the 6-digit code it shows to confirm.') }}</p>

        <div class="mt-5 flex flex-col items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 p-6 sm:flex-row sm:items-start">
            <img src="data:image/png;base64,{{ $qrImage }}" alt="{{ __('Two-factor QR code') }}" class="h-44 w-44 shrink-0 rounded-lg border border-slate-200 bg-white p-2">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-500 mb-1">{{ __("Can't scan? Enter this key manually:") }}</p>
                <code class="block break-all rounded-lg bg-white border border-slate-200 px-3 py-2 text-xs font-mono text-slate-700">{{ $secret }}</code>
            </div>
        </div>

        <form method="POST" action="{{ route('app.settings.two-factor.confirm') }}" class="mt-6 max-w-xs space-y-3">
            @csrf
            <label class="block text-sm font-medium text-slate-700">{{ __('6-digit code') }}</label>
            <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus class="w-full rounded-lg border border-slate-200 text-center text-lg tracking-[0.3em] focus:border-brand-500 focus:ring-brand-500">
            <button type="submit" class="w-full rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Confirm & enable') }}</button>
        </form>
    @endif
</div>
@endsection
