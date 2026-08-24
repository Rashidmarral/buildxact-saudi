@extends('layouts.admin')

@section('title', __('Platform settings'))

@section('content')
<div class="max-w-2xl space-y-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6 flex items-center justify-between">
        <div>
            <h3 class="font-semibold text-slate-900">{{ __('Payment gateways') }}</h3>
            <p class="text-sm text-slate-500 mt-1">{{ __('Daftari\'s own credentials for collecting subscription payments.') }}</p>
        </div>
        <a href="{{ route('admin.settings.payment-gateways') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Manage') }}</a>
    </div>


    <form method="POST" action="{{ route('admin.settings.branding') }}" enctype="multipart/form-data" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        @csrf
        <div>
            <h3 class="font-semibold text-slate-900 mb-1">{{ __('Platform branding') }}</h3>
            <p class="text-sm text-slate-500">{{ __('Shown publicly on the marketing site footer and on SaaS subscription receipts. Leave VAT/CR/address blank until you have your real registered numbers.') }}</p>
        </div>

        <div class="flex items-center gap-4">
            @if ($branding['logo_path'])
                <img src="{{ Storage::url($branding['logo_path']) }}" alt="" class="h-14 w-14 rounded-lg object-cover border border-slate-200">
            @else
                <div class="h-14 w-14 rounded-lg border border-dashed border-slate-200 flex items-center justify-center text-slate-300 text-xs text-center">{{ __('Logo') }}</div>
            @endif
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Logo') }}</label>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-600 hover:file:bg-slate-200">
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Company name') }}</label>
                <input type="text" name="platform_name" required value="{{ old('platform_name', $branding['name']) }}" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Company name (Arabic)') }}</label>
                <input type="text" name="platform_name_ar" value="{{ old('platform_name_ar', $branding['name_ar']) }}" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('VAT number') }}</label>
                <input type="text" name="platform_vat_number" value="{{ old('platform_vat_number', $branding['vat_number']) }}" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('CR Number') }}</label>
                <input type="text" name="platform_cr_number" value="{{ old('platform_cr_number', $branding['cr_number']) }}" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Phone') }}</label>
                <input type="text" name="platform_phone" value="{{ old('platform_phone', $branding['phone']) }}" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Email') }}</label>
                <input type="email" name="platform_email" value="{{ old('platform_email', $branding['email']) }}" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Address') }}</label>
                <input type="text" name="platform_address" value="{{ old('platform_address', $branding['address']) }}" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save branding') }}</button>
    </form>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-1">{{ __('Certificates & compliance documents') }}</h3>
        <p class="text-sm text-slate-500 mb-4">{{ __('Uploaded documents shown publicly on the Certificates page.') }}</p>
        <a href="{{ route('admin.certificates.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Manage certificates') }} →</a>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
    @csrf

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-1">{{ __('Signups') }}</h3>
        <p class="text-sm text-slate-500 mb-4">{{ __('Applies to every new company that registers from here on — existing trials keep their original end date.') }}</p>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Trial length (days)') }}</label>
                <input type="number" name="trial_days" min="1" max="365" required value="{{ old('trial_days', $settings['trial_days']) }}" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Support email shown to customers') }}</label>
                <input type="email" name="support_email" required value="{{ old('support_email', $settings['support_email']) }}" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-1">{{ __('Maintenance mode') }}</h3>
        <p class="text-sm text-slate-500 mb-4">{{ __('While on, everyone except super admins sees a maintenance page instead of the app.') }}</p>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="maintenance_mode" value="1" @checked($settings['maintenance_mode']) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Put the platform into maintenance mode') }}
        </label>
        <div class="mt-3">
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Message shown on the maintenance page (optional)') }}</label>
            <textarea name="maintenance_message" rows="2" class="w-full rounded-lg border border-slate-200 text-sm">{{ old('maintenance_message', $settings['maintenance_message']) }}</textarea>
        </div>
    </div>

    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save settings') }}</button>
    </form>
</div>
@endsection
