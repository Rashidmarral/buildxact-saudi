@extends('layouts.app')

@section('title', __('Payment gateways'))

@php
    $labels = ['moyasar' => 'Moyasar', 'hyperpay' => 'HyperPay', 'tap' => 'Tap Payments', 'paytabs' => 'PayTabs', 'bank_transfer' => __('Bank transfer (offline)')];
@endphp

@section('content')
<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">{{ __('Payment gateways') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Configure your own merchant credentials to let clients pay your invoices online. Payments go directly to your account with the provider — Daftari never holds your funds.') }}</p>
    </div>

    @if (empty($availableProviders))
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <p class="text-sm text-slate-400">{{ __('No payment gateways have been enabled by your platform administrator yet.') }}</p>
        </div>
    @endif

    @foreach ($availableProviders as $provider)
        @php $gateway = $gateways->get($provider); @endphp
        <form method="POST" action="{{ route('app.settings.payment-gateways.update', $provider) }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
            @csrf
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">{{ $labels[$provider] }}</h3>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_enabled" value="1" {{ $gateway?->is_enabled ? 'checked' : '' }} class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Enabled') }}
                </label>
            </div>

            @if ($provider === 'bank_transfer')
                <p class="text-sm text-slate-500 -mt-2">{{ __('Lets clients pay this invoice by wiring money to your bank account instead of an online gateway. Add or edit your bank accounts under Cash & Banks — the account attached to each invoice (or your default) is what shows in the payment instructions.') }}</p>
            @else
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Mode') }}</label>
                    <select name="mode" class="w-full rounded-lg border border-slate-200 text-sm">
                        <option value="test" {{ ($gateway?->mode ?? 'test') === 'test' ? 'selected' : '' }}>{{ __('Test') }}</option>
                        <option value="live" {{ $gateway?->mode === 'live' ? 'selected' : '' }}>{{ __('Live') }}</option>
                    </select>
                </div>
            @endif

            @if ($provider === 'moyasar')
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Secret key') }}</label>
                    <input type="text" name="secret_key" value="{{ old('secret_key') }}" placeholder="{{ ! empty($gateway?->credentials['secret_key']) ? __('•••••••• (configured — leave blank to keep)') : '' }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono" autocomplete="off">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Webhook shared secret') }}</label>
                    <input type="text" name="webhook_secret" value="{{ old('webhook_secret') }}" placeholder="{{ ! empty($gateway?->credentials['webhook_secret']) ? __('•••••••• (configured — leave blank to keep)') : '' }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono" autocomplete="off">
                </div>
            @elseif ($provider === 'hyperpay')
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Access token') }}</label>
                    <input type="text" name="access_token" value="{{ old('access_token') }}" placeholder="{{ ! empty($gateway?->credentials['access_token']) ? __('•••••••• (configured — leave blank to keep)') : '' }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono" autocomplete="off">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Entity ID') }}</label>
                    <input type="text" name="entity_id" value="{{ old('entity_id', $gateway?->credentials['entity_id'] ?? '') }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
                </div>
            @elseif ($provider === 'tap')
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Secret key') }}</label>
                    <input type="text" name="secret_key" value="{{ old('secret_key') }}" placeholder="{{ ! empty($gateway?->credentials['secret_key']) ? __('•••••••• (configured — leave blank to keep)') : '' }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono" autocomplete="off">
                </div>
            @elseif ($provider === 'paytabs')
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Profile ID') }}</label>
                    <input type="text" name="profile_id" value="{{ old('profile_id', $gateway?->credentials['profile_id'] ?? '') }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Server key') }}</label>
                    <input type="text" name="server_key" value="{{ old('server_key') }}" placeholder="{{ ! empty($gateway?->credentials['server_key']) ? __('•••••••• (configured — leave blank to keep)') : '' }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono" autocomplete="off">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Region') }}</label>
                    <select name="region" class="w-full rounded-lg border border-slate-200 text-sm">
                        <option value="sa" {{ ($gateway?->credentials['region'] ?? 'sa') === 'sa' ? 'selected' : '' }}>{{ __('Saudi Arabia (.sa)') }}</option>
                        <option value="com" {{ ($gateway?->credentials['region'] ?? '') === 'com' ? 'selected' : '' }}>{{ __('Global (.com)') }}</option>
                    </select>
                </div>
            @endif

            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </form>
    @endforeach
</div>
@endsection
