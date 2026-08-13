@extends('layouts.auth')

@section('title', __('Start your free trial'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Start your free trial') }}</h1>
<p class="mt-1 text-sm text-slate-500">{{ __(':days days free, no credit card required.', ['days' => config('daftari.trial_days')]) }}</p>

<form method="POST" action="{{ route('register') }}" class="mt-6 space-y-5">
    @csrf
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Company name') }}</label>
        <input type="text" name="company_name" value="{{ old('company_name') }}" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Your name') }}</label>
        <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
        <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
        <input type="password" name="password" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Confirm password') }}</label>
        <input type="password" name="password_confirmation" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Plan') }}</label>
        <select name="plan_id" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            @foreach ($plans as $plan)
                <option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>
                    {{ $plan->name }} — SAR {{ number_format($plan->price_monthly, 0) }}/{{ __('month') }}
                </option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Create account') }}</button>
</form>

<p class="mt-6 text-center text-sm text-slate-500">
    {{ __('Already have an account?') }}
    <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Log in') }}</a>
</p>
@endsection
