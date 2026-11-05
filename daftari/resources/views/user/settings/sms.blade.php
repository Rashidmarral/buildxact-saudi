@extends('layouts.app')

@section('title', __('SMS'))

@section('content')
<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">{{ __('SMS') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Send invoices and reminders to clients over SMS using your own Unifonic account, in addition to email and WhatsApp.') }}</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        <form method="POST" action="{{ route('app.settings.sms.update') }}" class="space-y-4">
            @csrf
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">{{ __('Connection') }}</h3>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_enabled" value="1" {{ $config?->is_enabled ? 'checked' : '' }} class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Enabled') }}
                </label>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('App Sid') }}</label>
                <input type="text" name="app_sid" value="{{ old('app_sid', $config?->app_sid ?? '') }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Sender ID') }}</label>
                <input type="text" name="sender_id" value="{{ old('sender_id', $config->sender_id ?? 'Daftari') }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
                <p class="text-xs text-slate-400 mt-1">{{ __('Must be a Sender ID already approved on your Unifonic account.') }}</p>
            </div>

            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </form>
    </div>

    @if ($config?->is_enabled)
        <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-3">
            <h3 class="font-semibold text-slate-900">{{ __('Send a test message') }}</h3>
            <form method="POST" action="{{ route('app.settings.sms.test') }}" class="flex gap-2 max-w-sm">
                @csrf
                <input type="text" name="phone" required placeholder="{{ __('e.g. 0501234567') }}" class="flex-1 rounded-lg border border-slate-200 text-sm">
                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Send test') }}</button>
            </form>
        </div>
    @endif
</div>
@endsection
