@extends('layouts.auth')

@section('title', __('Create your account'))

@section('content')
@php
    // A failed server-side validation redirect lands back on this same
    // page with old()/$errors set — reopen whichever step actually has
    // the error instead of always resetting to step 1, or the user would
    // never see why the submission was rejected.
    $initialStep = 1;
    if ($errors->hasAny(['company_name', 'organization_size', 'industry', 'vat_number', 'primary_customer_type'])) {
        $initialStep = 3;
    } elseif ($errors->hasAny(['first_name', 'last_name', 'phone', 'job_title'])) {
        $initialStep = 2;
    }
@endphp
<div
    x-data="{
        step: {{ $initialStep }},
        totalSteps: 3,
        email: '{{ old('email', '') }}',
        password: '',
        passwordConfirmation: '',
        get passwordLongEnough() { return this.password.length >= 8 },
        get passwordsMatch() { return this.password.length > 0 && this.password === this.passwordConfirmation },
        get emailLooksValid() { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email) },
        step1Error: null,
        step2Error: null,
        // Mirrors App\Rules\SaudiPhoneNumber's normalization/format check
        // client-side, so an obviously-fake number (too short, all the
        // same digit, missing the leading trunk digit) is caught here
        // too — the server rule is still the real gate either way.
        isValidSaudiPhone(raw) {
            let digits = String(raw).replace(/[^0-9]/g, '');
            if (digits.startsWith('00966')) digits = digits.slice(5);
            else if (digits.startsWith('966')) digits = digits.slice(3);
            else if (digits.startsWith('0')) digits = digits.slice(1);
            if (!/^[1-9]\d{8}$/.test(digits)) return false;
            if (/^(\d)\1{8}$/.test(digits)) return false;
            return true;
        },
        goToStep2() {
            this.step1Error = null;
            if (!this.emailLooksValid) { this.step1Error = @js(__('Enter a valid email address.')); return; }
            if (!this.passwordLongEnough) { this.step1Error = @js(__('Password must be at least 8 characters.')); return; }
            if (!this.passwordsMatch) { this.step1Error = @js(__('Passwords do not match.')); return; }
            this.step = 2;
        },
        goToStep3($el) {
            this.step2Error = null;
            const form = $el.closest('form');
            if (!form.first_name.value.trim() || !form.last_name.value.trim()) {
                this.step2Error = @js(__('Please enter your first and last name.'));
                return;
            }
            const phone = form.phone.value.trim();
            if (form.phone.hasAttribute('required') && !phone) {
                this.step2Error = @js(__('Phone number is required.'));
                return;
            }
            if (phone && !this.isValidSaudiPhone(phone)) {
                this.step2Error = @js(__('Enter a valid Saudi phone number, e.g. 05XXXXXXXX.'));
                return;
            }
            if (!form.job_title.value) {
                this.step2Error = @js(__('Please select your position / job title.'));
                return;
            }
            this.step = 3;
        },
    }"
>
    <div class="mb-6">
        <div class="flex items-center justify-end text-xs font-medium text-slate-400">
            <span x-text="@js(__('Step :step of :total')).replace(':step', step).replace(':total', totalSteps)"></span>
        </div>
        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-brand-700 transition-all duration-500 ease-smooth" :style="{ width: (step / totalSteps * 100) + '%' }"></div>
        </div>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5" @submit="if (step !== 3) $event.preventDefault()">
        @csrf

        {{-- Step 1: account --}}
        <div x-show="step === 1" x-cloak>
            <h1 class="text-xl font-bold text-slate-900">{{ __('Create a new account') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __(':days-day free trial — no credit card required.', ['days' => config('daftari.trial_days')]) }}</p>

            <div class="mt-6 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Email address') }}</label>
                    <input type="email" name="email" x-model="email" required autofocus placeholder="{{ __('you@example.com') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
                    <input type="password" name="password" x-model="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <p class="mt-1.5 flex items-center gap-1.5 text-xs" :class="passwordLongEnough ? 'text-emerald-600' : 'text-slate-400'">
                        <span x-text="passwordLongEnough ? '✓' : '✕'"></span>
                        {{ __('At least 8 characters') }}
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Confirm password') }}</label>
                    <input type="password" name="password_confirmation" x-model="passwordConfirmation" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>

                @if ($errors->has('email') || $errors->has('password'))
                    <p class="text-sm text-red-600">{{ $errors->first('email') ?: $errors->first('password') }}</p>
                @endif
                <p class="text-sm text-red-600" x-show="step1Error" x-text="step1Error" x-cloak></p>

                <button type="button" @click="goToStep2()" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Continue') }}</button>
            </div>

            <p class="mt-6 text-center text-sm text-slate-500">
                {{ __('Already have an account?') }}
                <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Sign in here') }}</a>
            </p>
        </div>

        {{-- Step 2: profile --}}
        <div x-show="step === 2" x-cloak>
            <h1 class="text-xl font-bold text-slate-900">{{ __('Your profile') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Who will be managing this account?') }}</p>

            <div class="mt-6 space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('First name') }}</label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('Last name') }}</label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">
                        {{ __('Mobile number') }}
                        @unless ($phoneRequired) <span class="font-normal text-slate-400">({{ __('optional') }})</span> @endunless
                    </label>
                    <input type="text" name="phone" value="{{ old('phone') }}" @required($phoneRequired) placeholder="05XXXXXXXX" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    @if ($phoneRequired)
                        <p class="mt-1 text-xs text-slate-400">{{ __("We'll text you a code to verify it after you sign up.") }}</p>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Position / Job title') }}</label>
                    <select name="job_title" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        <option value="" disabled @selected(! old('job_title'))>—</option>
                        @foreach ($jobTitles as $value => $label)
                            <option value="{{ $value }}" @selected(old('job_title') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($errors->has('first_name') || $errors->has('last_name') || $errors->has('phone') || $errors->has('job_title'))
                    <p class="text-sm text-red-600">{{ $errors->first('first_name') ?: ($errors->first('last_name') ?: ($errors->first('phone') ?: $errors->first('job_title'))) }}</p>
                @endif
                <p class="text-sm text-red-600" x-show="step2Error" x-text="step2Error" x-cloak></p>

                <div class="flex gap-3">
                    <button type="button" @click="step = 1" class="rounded-lg border border-slate-200 px-5 py-3 font-semibold text-slate-600 hover:border-slate-300">{{ __('Back') }}</button>
                    <button type="button" @click="goToStep3($el)" class="flex-1 rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Continue') }}</button>
                </div>
            </div>
        </div>

        {{-- Step 3: business --}}
        <div x-show="step === 3" x-cloak>
            <h1 class="text-xl font-bold text-slate-900">{{ __('Set up your business') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Enter your business details exactly as shown on your official Commercial Registration to get started correctly and compliantly.') }}</p>

            <div class="mt-6 flex gap-3 rounded-xl border border-brand-100 bg-brand-50/60 p-4">
                @include('partials.icon', ['name' => 'building', 'class' => 'h-5 w-5 shrink-0 text-brand-700'])
                <div>
                    <p class="text-sm font-semibold text-slate-800">{{ __("Let's get your business ready on Daftari") }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ __('Your organization name must match your official Commercial Registration exactly to ensure compliance.') }}</p>
                </div>
            </div>

            <div class="mt-6 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Organization name') }}</label>
                    <input type="text" name="company_name" value="{{ old('company_name') }}" required placeholder="{{ __('e.g. Al-Nour Trading Est.') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Organization size') }}</label>
                    <select name="organization_size" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        <option value="" disabled @selected(! old('organization_size'))>—</option>
                        @foreach ($organizationSizes as $value => $label)
                            <option value="{{ $value }}" @selected(old('organization_size') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Industry') }}</label>
                    <select name="industry" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        <option value="" disabled @selected(! old('industry'))>—</option>
                        @foreach ($industries as $value => $label)
                            <option value="{{ $value }}" @selected(old('industry') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('VAT registration number') }}</label>
                    <input type="text" name="vat_number" value="{{ old('vat_number') }}" placeholder="300..." class="mt-1 w-full rounded-lg border border-slate-200 font-mono focus:border-brand-500 focus:ring-brand-500">
                    <p class="mt-1 text-xs text-slate-400">{{ __('Leave blank if not VAT registered (Non-Taxable).') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Primary customer type') }}</label>
                    <select name="primary_customer_type" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        @foreach ($customerTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('primary_customer_type', 'mixed') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">{{ __('This determines your default invoice template settings.') }}</p>
                </div>

                @if ($errors->has('company_name') || $errors->has('organization_size') || $errors->has('industry') || $errors->has('vat_number') || $errors->has('primary_customer_type'))
                    <p class="text-sm text-red-600">
                        {{ $errors->first('company_name') ?: ($errors->first('organization_size') ?: ($errors->first('industry') ?: ($errors->first('vat_number') ?: $errors->first('primary_customer_type')))) }}
                    </p>
                @endif

                <div class="flex gap-3">
                    <button type="button" @click="step = 2" class="rounded-lg border border-slate-200 px-5 py-3 font-semibold text-slate-600 hover:border-slate-300">{{ __('Back') }}</button>
                    <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Create account') }}</button>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-slate-400">
                {!! __('By using Daftari, you agree to the :terms and :privacy.', [
                    'terms' => '<a href="'.route('legal', 'terms').'" class="underline hover:text-slate-600">'.__('Terms of Service').'</a>',
                    'privacy' => '<a href="'.route('legal', 'privacy').'" class="underline hover:text-slate-600">'.__('Privacy Policy').'</a>',
                ]) !!}
            </p>
        </div>
    </form>
</div>
@endsection
