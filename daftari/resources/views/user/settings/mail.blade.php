@extends('layouts.app')

@section('title', __('Email'))

@section('content')
<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-lg font-semibold text-slate-900">{{ __('Email') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Send invoices, quotations, statements, and payment reminders to clients from your own email address and domain instead of a shared one.') }}</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        <form method="POST" action="{{ route('app.settings.mail.update') }}" class="space-y-4">
            @csrf
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">{{ __('Connection') }}</h3>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_enabled" value="1" {{ $config?->is_enabled ? 'checked' : '' }} class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Enabled') }}
                </label>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('SMTP host') }}</label>
                    <input type="text" name="smtp_host" value="{{ old('smtp_host', $config?->smtp_host ?? '') }}" placeholder="smtp.yourdomain.com" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Port') }}</label>
                    <input type="number" name="smtp_port" value="{{ old('smtp_port', $config?->smtp_port ?? 587) }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Encryption') }}</label>
                    <select name="smtp_encryption" class="w-full rounded-lg border border-slate-200 text-sm">
                        @php($current = old('smtp_encryption', $config?->smtp_encryption ?? 'tls'))
                        <option value="tls" @selected($current === 'tls')>TLS</option>
                        <option value="ssl" @selected($current === 'ssl')>SSL</option>
                        <option value="none" @selected($current === 'none')>{{ __('None') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Username') }}</label>
                    <input type="text" name="smtp_username" value="{{ old('smtp_username', $config?->smtp_username ?? '') }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Password') }}</label>
                    <input type="password" name="smtp_password" placeholder="{{ $config?->smtp_password ? __('Leave blank to keep the current password') : '' }}" class="w-full rounded-lg border border-slate-200 text-sm font-mono">
                </div>
            </div>

            <div class="border-t border-slate-100 pt-4 grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('From address') }}</label>
                    <input type="email" name="from_address" value="{{ old('from_address', $config?->from_address ?? '') }}" placeholder="billing@yourcompany.com" class="w-full rounded-lg border border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('From name') }}</label>
                    <input type="text" name="from_name" value="{{ old('from_name', $config?->from_name ?? '') }}" placeholder="{{ Auth::user()->company->name }}" class="w-full rounded-lg border border-slate-200 text-sm">
                </div>
            </div>

            <p class="text-xs text-slate-400">{{ __('Applies to invoices, quotations, account statements, and overdue-payment reminders sent to your clients. Team invites and your own Daftari subscription emails always use Daftari\'s own address.') }}</p>

            <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </form>
    </div>

    @if ($config?->is_enabled)
        <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-3">
            <h3 class="font-semibold text-slate-900">{{ __('Send a test email') }}</h3>
            <form method="POST" action="{{ route('app.settings.mail.test') }}" class="flex gap-2 max-w-sm">
                @csrf
                <input type="email" name="test_email" required placeholder="you@example.com" class="flex-1 rounded-lg border border-slate-200 text-sm">
                <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Send test') }}</button>
            </form>
        </div>
    @endif
</div>
@endsection
