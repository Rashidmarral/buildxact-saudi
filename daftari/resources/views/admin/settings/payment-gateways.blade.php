@extends('layouts.admin')

@section('title', __('Payment gateways'))

@php
    $labels = ['moyasar' => 'Moyasar', 'hyperpay' => 'HyperPay', 'tap' => 'Tap Payments', 'paytabs' => 'PayTabs', 'bank_transfer' => __('Bank transfer (offline)')];
@endphp

@section('content')
<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">{{ __('Payment gateways') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Daftari\'s own credentials for collecting subscription payments from companies. Enable at least one provider so companies can pay online — each company still configures its own separate gateway for collecting payments on its own invoices.') }}</p>
    </div>

    @foreach ([...\App\Models\PaymentGateway::PROVIDERS, \App\Models\PaymentGateway::BANK_TRANSFER] as $provider)
        @php $gateway = $gateways->get($provider); @endphp
        <form method="POST" action="{{ route('admin.settings.payment-gateways.update', $provider) }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
            @csrf
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">{{ $labels[$provider] }}</h3>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_enabled" value="1" {{ $gateway?->is_enabled ? 'checked' : '' }} class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Enabled') }}
                </label>
            </div>

            @if ($provider === 'bank_transfer')
                <p class="text-sm text-slate-500 -mt-2">{{ __('Lets companies pay their subscription by wiring money to this account instead of an online gateway. Payments still need to be confirmed manually from Admin > Payments once the transfer arrives. Also turns bank-transfer instructions on or off for every company\'s invoices platform-wide.') }}</p>
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
            @elseif ($provider === 'bank_transfer')
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Bank name') }}</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $gateway?->credentials['bank_name'] ?? '') }}" class="w-full rounded-lg border border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Account holder name') }}</label>
                    <input type="text" name="account_holder_name" value="{{ old('account_holder_name', $gateway?->credentials['account_holder_name'] ?? '') }}" class="w-full rounded-lg border border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('IBAN') }}</label>
                    <input type="text" name="iban" value="{{ old('iban', $gateway?->credentials['iban'] ?? '') }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Account number (optional)') }}</label>
                    <input type="text" name="account_number" value="{{ old('account_number', $gateway?->credentials['account_number'] ?? '') }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('SWIFT/BIC code (optional)') }}</label>
                    <input type="text" name="swift_code" value="{{ old('swift_code', $gateway?->credentials['swift_code'] ?? '') }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
                </div>
            @endif

            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </form>
    @endforeach
</div>
@endsection
