@extends('layouts.app')

@section('title', __('WhatsApp'))

@section('content')
<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">{{ __('WhatsApp') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Send invoices to clients over WhatsApp using your own WhatsApp Business account (Meta Cloud API), in addition to email.') }}</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ __('WhatsApp only allows business-initiated messages (like an invoice notification) to be sent as a pre-approved template. Create and approve a template in your Meta Business account first, then enter its exact name below. The template\'s body must accept 4 parameters in this order: client name, invoice number, total amount, payment link.') }}
        </div>

        <form method="POST" action="{{ route('app.settings.whatsapp.update') }}" class="space-y-4">
            @csrf
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">{{ __('Connection') }}</h3>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_enabled" value="1" {{ $config?->is_enabled ? 'checked' : '' }} class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Enabled') }}
                </label>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Phone number ID') }}</label>
                <input type="text" name="phone_number_id" value="{{ old('phone_number_id', $config->phone_number_id ?? '') }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Access token') }}</label>
                <input type="text" name="access_token" value="{{ old('access_token', $config?->access_token ?? '') }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Template name') }}</label>
                <input type="text" name="template_name" value="{{ old('template_name', $config->template_name ?? 'invoice_notification') }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Template language code') }}</label>
                <input type="text" name="template_language" value="{{ old('template_language', $config->template_language ?? 'en_US') }}" placeholder="en_US" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
            </div>

            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </form>
    </div>

    @if ($config?->is_enabled)
        <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-3">
            <h3 class="font-semibold text-slate-900">{{ __('Send a test message') }}</h3>
            <p class="text-sm text-slate-500">{{ __('Sends a generic 2-parameter test — if your real invoice template expects 4 parameters, this test may fail even though invoices will still send correctly.') }}</p>
            <form method="POST" action="{{ route('app.settings.whatsapp.test') }}" class="flex gap-2 max-w-sm">
                @csrf
                <input type="text" name="phone" required placeholder="{{ __('e.g. 0501234567') }}" class="flex-1 rounded-lg border border-slate-200 text-sm">
                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Send test') }}</button>
            </form>
        </div>
    @endif
</div>
@endsection
