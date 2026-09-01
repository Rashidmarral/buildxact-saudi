@extends('layouts.admin')

@section('title', __('Platform settings'))

@section('content')
<div x-data="{ tab: 'general' }" class="max-w-4xl">

    <div class="flex flex-wrap items-center gap-3 mb-6">
        <a href="{{ route('admin.settings.payment-gateways') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Payment gateways') }}</a>
        <a href="{{ route('admin.certificates.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Certificates & compliance documents') }}</a>
        <a href="{{ route('admin.currencies.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Currencies') }}</a>
    </div>

    <div class="flex gap-1 border-b border-slate-200 mb-6 overflow-x-auto">
        @foreach ([
            'general' => __('General'),
            'identity' => __('Platform Identity'),
            'branding' => __('Branding'),
            'signup' => __('Signup'),
            'maintenance' => __('Maintenance'),
            'storage' => __('Storage'),
            'system' => __('System'),
        ] as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                class="whitespace-nowrap px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ============ General ============ --}}
    <div x-show="tab === 'general'" x-cloak>
        <form method="POST" action="{{ route('admin.settings.general.update') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
            @csrf
            <div>
                <h3 class="font-semibold text-slate-900 mb-1">{{ __('General') }}</h3>
                <p class="text-sm text-slate-500">{{ __('Platform-wide defaults applied to new companies and to every visitor before they choose otherwise.') }}</p>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Platform name') }}</label>
                    <input type="text" name="general_platform_name" required maxlength="255" value="{{ old('general_platform_name', $settings['general_platform_name']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Platform URL') }}</label>
                    <input type="url" name="general_platform_url" required maxlength="255" value="{{ old('general_platform_url', $settings['general_platform_url']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Default country') }}</label>
                    <select name="general_default_country" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($countries as $code => $label)
                            <option value="{{ $code }}" @selected(old('general_default_country', $settings['general_default_country']) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Default timezone') }}</label>
                    <select name="general_default_timezone" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($timezones as $tz)
                            <option value="{{ $tz }}" @selected(old('general_default_timezone', $settings['general_default_timezone']) === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-400 mt-1">{{ __('Stored platform-wide; companies do not yet have their own timezone field.') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Default language') }}</label>
                    <select name="general_default_language" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($languages as $code => $label)
                            <option value="{{ $code }}" @selected(old('general_default_language', $settings['general_default_language']) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Default currency') }}</label>
                    <select name="general_default_currency" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency->code }}" @selected(old('general_default_currency', $settings['general_default_currency']) === $currency->code)>{{ $currency->code }} — {{ $currency->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Date format') }}</label>
                    <select name="general_date_format" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($dateFormats as $format)
                            <option value="{{ $format }}" @selected(old('general_date_format', $settings['general_date_format']) === $format)>{{ \Illuminate\Support\Carbon::now()->format($format) }} ({{ $format }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Time format') }}</label>
                    <select name="general_time_format" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="24h" @selected(old('general_time_format', $settings['general_time_format']) === '24h')>{{ __('24-hour') }} (14:30)</option>
                        <option value="12h" @selected(old('general_time_format', $settings['general_time_format']) === '12h')>{{ __('12-hour') }} (2:30 PM)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Fiscal year start') }}</label>
                    <select name="general_fiscal_year_start" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        @foreach ([1=>__('January'),2=>__('February'),3=>__('March'),4=>__('April'),5=>__('May'),6=>__('June'),7=>__('July'),8=>__('August'),9=>__('September'),10=>__('October'),11=>__('November'),12=>__('December')] as $num => $label)
                            <option value="{{ $num }}" @selected((int) old('general_fiscal_year_start', $settings['general_fiscal_year_start']) === $num)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="space-y-2 pt-2 border-t border-slate-100">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="general_allow_registrations" value="1" @checked(old('general_allow_registrations', $settings['general_allow_registrations'])) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Allow new company registrations') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="general_allow_demo_accounts" value="1" @checked(old('general_allow_demo_accounts', $settings['general_allow_demo_accounts'])) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Allow demo accounts') }}
                </label>
            </div>

            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save General settings') }}</button>
        </form>
    </div>

    {{-- ============ Platform Identity ============ --}}
    <div x-show="tab === 'identity'" x-cloak>
        <form method="POST" action="{{ route('admin.settings.identity.update') }}" enctype="multipart/form-data" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
            @csrf
            <div>
                <h3 class="font-semibold text-slate-900 mb-1">{{ __('Platform Identity') }}</h3>
                <p class="text-sm text-slate-500">{{ __('Your legal/registered identity — shown on SaaS subscription receipts and the marketing site footer. Leave VAT/CR/address blank until you have your real registered numbers.') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-6">
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
                <div class="flex items-center gap-4">
                    @if ($branding['favicon_path'])
                        <img src="{{ Storage::url($branding['favicon_path']) }}" alt="" class="h-14 w-14 rounded-lg object-cover border border-slate-200 bg-slate-50 p-2">
                    @else
                        <div class="h-14 w-14 rounded-lg border border-dashed border-slate-200 flex items-center justify-center text-slate-300 text-xs text-center">{{ __('Favicon') }}</div>
                    @endif
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Favicon') }}</label>
                        <input type="file" name="favicon" accept="image/png,image/x-icon,image/webp" class="text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-600 hover:file:bg-slate-200">
                    </div>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Company name') }}</label>
                    <input type="text" name="platform_name" required maxlength="255" value="{{ old('platform_name', $settings['platform_name']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Company name (Arabic)') }}</label>
                    <input type="text" name="platform_name_ar" maxlength="255" value="{{ old('platform_name_ar', $settings['platform_name_ar']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('VAT number') }}</label>
                    <input type="text" name="platform_vat_number" maxlength="20" value="{{ old('platform_vat_number', $settings['platform_vat_number']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('CR number') }}</label>
                    <input type="text" name="platform_cr_number" maxlength="20" value="{{ old('platform_cr_number', $settings['platform_cr_number']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Phone') }}</label>
                    <input type="text" name="platform_phone" maxlength="30" value="{{ old('platform_phone', $settings['platform_phone']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Email') }}</label>
                    <input type="email" name="platform_email" maxlength="255" value="{{ old('platform_email', $settings['platform_email']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Website') }}</label>
                    <input type="url" name="platform_website" maxlength="255" value="{{ old('platform_website', $settings['platform_website']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('National address') }}</label>
                    <input type="text" name="platform_address" maxlength="255" value="{{ old('platform_address', $settings['platform_address']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save Identity settings') }}</button>
        </form>
    </div>

    {{-- ============ Branding ============ --}}
    <div x-show="tab === 'branding'" x-cloak>
        <form method="POST" action="{{ route('admin.settings.branding') }}" enctype="multipart/form-data" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
            @csrf
            <div>
                <h3 class="font-semibold text-slate-900 mb-1">{{ __('Branding') }}</h3>
                <p class="text-sm text-slate-500">{{ __('Colors and logo variants used across the app, login screen, PDFs, and emails. Leave a color blank to keep the default Daftari palette.') }}</p>
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Primary color') }}</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input type="color" value="{{ old('branding_primary_color', $branding['primary_color'] ?: '#0f9068') }}" oninput="this.nextElementSibling.value = this.value" class="h-9 w-9 rounded border border-slate-200 p-0.5">
                        <input type="text" name="branding_primary_color" value="{{ old('branding_primary_color', $branding['primary_color']) }}" placeholder="#0f9068" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Secondary color') }}</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input type="color" value="{{ old('branding_secondary_color', $branding['secondary_color'] ?: '#3fcd97') }}" oninput="this.nextElementSibling.value = this.value" class="h-9 w-9 rounded border border-slate-200 p-0.5">
                        <input type="text" name="branding_secondary_color" value="{{ old('branding_secondary_color', $branding['secondary_color']) }}" placeholder="#3fcd97" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Sidebar color') }}</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input type="color" value="{{ old('branding_sidebar_color', $branding['sidebar_color'] ?: '#020617') }}" oninput="this.nextElementSibling.value = this.value" class="h-9 w-9 rounded border border-slate-200 p-0.5">
                        <input type="text" name="branding_sidebar_color" value="{{ old('branding_sidebar_color', $branding['sidebar_color']) }}" placeholder="#020617" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                @foreach ([
                    'login_logo' => ['label' => __('Login page logo'), 'path' => $branding['login_logo_path']],
                    'pdf_logo' => ['label' => __('PDF logo'), 'path' => $branding['pdf_logo_path']],
                    'email_logo' => ['label' => __('Email logo'), 'path' => $branding['email_logo_path']],
                    'favicon' => ['label' => __('Favicon'), 'path' => $branding['favicon_path']],
                ] as $field => $meta)
                    <div class="flex items-center gap-4">
                        @if ($meta['path'])
                            <img src="{{ Storage::url($meta['path']) }}" alt="" class="h-12 w-12 rounded-lg object-cover border border-slate-200 bg-slate-50 p-1">
                        @else
                            <div class="h-12 w-12 rounded-lg border border-dashed border-slate-200 flex items-center justify-center text-slate-300 text-[10px] text-center">{{ __('None') }}</div>
                        @endif
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">{{ $meta['label'] }}</label>
                            <input type="file" name="{{ $field }}" accept="image/png,image/jpeg,image/webp,image/x-icon" class="text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-slate-600 hover:file:bg-slate-200">
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-slate-400">{{ __('Favicon here is the same setting as on the Platform Identity tab — uploading it in either place updates both.') }}</p>

            <div class="pt-2 border-t border-slate-100">
                <div class="flex items-center gap-4">
                    @if ($branding['social_image_path'])
                        <img src="{{ Storage::url($branding['social_image_path']) }}" alt="" class="h-12 w-20 rounded-lg object-cover border border-slate-200 bg-slate-50 p-1">
                    @else
                        <div class="h-12 w-20 rounded-lg border border-dashed border-slate-200 flex items-center justify-center text-slate-300 text-[10px] text-center">{{ __('None') }}</div>
                    @endif
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Social share image') }}</label>
                        <input type="file" name="social_image" accept="image/png,image/jpeg,image/webp" class="text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-slate-600 hover:file:bg-slate-200">
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-2">{{ __('Shown as the preview image when the marketing site is shared on WhatsApp, LinkedIn, X, or Slack. Recommended size 1200×630px. Falls back to the platform logo if not set.') }}</p>
            </div>

            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save Branding') }}</button>
        </form>
    </div>

    {{-- ============ Signup ============ --}}
    <div x-show="tab === 'signup'" x-cloak>
        <form method="POST" action="{{ route('admin.settings.signup.update') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
            @csrf
            <div>
                <h3 class="font-semibold text-slate-900 mb-1">{{ __('Signup') }}</h3>
                <p class="text-sm text-slate-500">{{ __('Applies to every new company that registers from here on — existing trials keep their original end date.') }}</p>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Trial days') }}</label>
                    <input type="number" name="trial_days" min="1" max="365" required value="{{ old('trial_days', $settings['trial_days']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Default plan') }}</label>
                    <select name="signup_default_plan_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('None (first active plan)') }}</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected((string) old('signup_default_plan_id', $settings['signup_default_plan_id']) === (string) $plan->id)>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-400 mt-1">{{ __('Pre-selected on the registration form — the customer can still change it.') }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Support email shown to customers') }}</label>
                    <input type="email" name="support_email" required value="{{ old('support_email', $settings['support_email']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            <div class="space-y-2 pt-2 border-t border-slate-100">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="general_allow_registrations" value="1" @checked(old('general_allow_registrations', $settings['general_allow_registrations'])) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Allow registration') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="signup_require_email_verification" value="1" @checked(old('signup_require_email_verification', $settings['signup_require_email_verification'])) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Require email verification') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="signup_require_phone_verification" value="1" @checked(old('signup_require_phone_verification', $settings['signup_require_phone_verification'])) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Require phone verification') }}
                </label>
                <p class="text-xs text-amber-600">{{ __('Phone verification has no OTP flow built yet — this stores the preference only and does not currently block signup.') }}</p>
            </div>

            <div class="pt-2 border-t border-slate-100">
                <h4 class="text-sm font-semibold text-slate-900">{{ __('Subscription lifecycle (automatic rules)') }}</h4>
                <p class="text-xs text-slate-500 mb-3">{{ __('Controls how long a subscription stays in each stage before automatically moving to the next — trial → past due → grace period → suspended → cancelled.') }}</p>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Trial ending reminder (days before)') }}</label>
                        <input type="number" name="subscription_trial_reminder_days_before" min="1" max="30" required value="{{ old('subscription_trial_reminder_days_before', $settings['subscription_trial_reminder_days_before']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Grace period length (days)') }}</label>
                        <input type="number" name="subscription_grace_period_days" min="0" max="90" required value="{{ old('subscription_grace_period_days', $settings['subscription_grace_period_days']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <p class="text-xs text-slate-400 mt-1">{{ __('Days past due before entering the grace period.') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Suspend after grace period (days)') }}</label>
                        <input type="number" name="subscription_suspend_after_grace_days" min="0" max="90" required value="{{ old('subscription_suspend_after_grace_days', $settings['subscription_suspend_after_grace_days']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Cancel after suspended (days)') }}</label>
                        <input type="number" name="subscription_cancel_after_suspended_days" min="0" max="365" required value="{{ old('subscription_cancel_after_suspended_days', $settings['subscription_cancel_after_suspended_days']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
            </div>

            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save Signup settings') }}</button>
        </form>
    </div>

    {{-- ============ Maintenance ============ --}}
    <div x-show="tab === 'maintenance'" x-cloak>
        <form method="POST" action="{{ route('admin.settings.maintenance.update') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
            @csrf
            <div>
                <h3 class="font-semibold text-slate-900 mb-1">{{ __('Maintenance') }}</h3>
                <p class="text-sm text-slate-500">{{ __('While on, everyone outside the allowed exceptions sees a maintenance page instead of the app.') }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="maintenance_mode" value="1" @checked(old('maintenance_mode', $settings['maintenance_mode'])) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Enable maintenance mode now') }}
            </label>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Maintenance message') }}</label>
                <textarea name="maintenance_message" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('maintenance_message', $settings['maintenance_message']) }}</textarea>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Scheduled maintenance start') }}</label>
                    <input type="datetime-local" name="maintenance_scheduled_start" value="{{ old('maintenance_scheduled_start', $settings['maintenance_scheduled_start']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Scheduled maintenance end') }}</label>
                    <input type="datetime-local" name="maintenance_scheduled_end" value="{{ old('maintenance_scheduled_end', $settings['maintenance_scheduled_end']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
            <p class="text-xs text-slate-400">{{ __('Both an admin toggle above and this scheduled window can independently switch maintenance mode on — either one being true is enough.') }}</p>

            <label class="flex items-center gap-2 text-sm text-slate-700 pt-2 border-t border-slate-100">
                <input type="checkbox" name="maintenance_allow_super_admin" value="1" @checked(old('maintenance_allow_super_admin', $settings['maintenance_allow_super_admin'])) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Allow Super Admin during maintenance') }}
            </label>

            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save Maintenance settings') }}</button>
        </form>
    </div>

    {{-- ============ Storage ============ --}}
    <div x-show="tab === 'storage'" x-cloak x-data="{ driver: '{{ old('storage_driver', $settings['storage_driver']) }}' }">
        <form method="POST" action="{{ route('admin.settings.storage.update') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
            @csrf
            <div>
                <h3 class="font-semibold text-slate-900 mb-1">{{ __('Storage') }}</h3>
                <p class="text-sm text-slate-500">{{ __('Where uploaded files (logos, attachments, letterheads) are stored. S3 credentials are encrypted at rest and never shown again once saved.') }}</p>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Storage driver') }}</label>
                    <select name="storage_driver" x-model="driver" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="local">{{ __('Local') }}</option>
                        <option value="s3">{{ __('S3-compatible storage') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Maximum upload size (MB)') }}</label>
                    <input type="number" name="storage_max_upload_size_mb" min="1" max="100" required value="{{ old('storage_max_upload_size_mb', $settings['storage_max_upload_size_mb']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <p class="text-xs text-slate-400 mt-1">{{ __('Enforced on the logo/branding uploads on this settings page.') }}</p>
                </div>
            </div>

            <div x-show="driver === 's3'" x-cloak class="grid sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Access key') }}</label>
                    <input type="password" name="storage_s3_key" autocomplete="new-password" placeholder="{{ $settings['storage_s3_key_configured'] ? __('•••••••• (configured — leave blank to keep)') : __('Not configured') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Secret key') }}</label>
                    <input type="password" name="storage_s3_secret" autocomplete="new-password" placeholder="{{ $settings['storage_s3_secret_configured'] ? __('•••••••• (configured — leave blank to keep)') : __('Not configured') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Region') }}</label>
                    <input type="text" name="storage_s3_region" value="{{ old('storage_s3_region', $settings['storage_s3_region']) }}" placeholder="me-south-1" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Bucket') }}</label>
                    <input type="text" name="storage_s3_bucket" value="{{ old('storage_s3_bucket', $settings['storage_s3_bucket']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Custom endpoint (optional)') }}</label>
                    <input type="url" name="storage_s3_endpoint" value="{{ old('storage_s3_endpoint', $settings['storage_s3_endpoint']) }}" placeholder="https://s3.example.com" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <p class="text-xs text-slate-400 mt-1">{{ __('For S3-compatible providers other than AWS (e.g. DigitalOcean Spaces, Cloudflare R2, MinIO).') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Public URL override (optional)') }}</label>
                    <input type="url" name="storage_s3_url" value="{{ old('storage_s3_url', $settings['storage_s3_url']) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save Storage settings') }}</button>
        </form>
    </div>

    {{-- ============ System ============ --}}
    <div x-show="tab === 'system'" x-cloak class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h3 class="font-semibold text-slate-900 mb-1">{{ __('System') }}</h3>
            <p class="text-sm text-slate-500">{{ __('Application version') }}: <span class="font-mono font-semibold text-slate-700">{{ $appVersion }}</span></p>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Cache management') }}</h3>
            <div class="grid sm:grid-cols-2 gap-3">
                @foreach ([
                    'cache-clear' => __('Clear application cache'),
                    'config-cache' => __('Cache configuration'),
                    'route-cache' => __('Cache routes'),
                    'view-cache' => __('Cache views'),
                ] as $action => $label)
                    <form method="POST" action="{{ route('admin.settings.system.run', $action) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:border-brand-300 hover:text-brand-700">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
