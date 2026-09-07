@extends('layouts.site')

@section('title', __('Get Started') . ' · Daftari')

@section('content')
<section class="mx-auto max-w-2xl px-6 pb-6 pt-16 text-center">
    <h1 class="text-3xl font-extrabold text-slate-900 md:text-4xl">{{ __('Tell us about your business') }}</h1>
    <p class="mt-4 text-slate-600">{{ __("Share a few details and our team will reach out to set up a demo, answer questions, or help you check your ZATCA e-invoicing readiness.") }}</p>
</section>

<section class="mx-auto max-w-2xl px-6 pb-20">
    @if (session('status'))
        <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-8 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
            <ul class="list-disc ps-4 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('get-started.submit') }}" class="space-y-5 bg-white rounded-2xl border border-slate-100 p-8 shadow-card">
        @csrf
        <input type="hidden" name="source" value="{{ $source }}">
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Phone (optional)') }}</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Company name (optional)') }}</label>
                <input type="text" name="company_name" value="{{ old('company_name') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Industry') }}</label>
            <select name="industry" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select your industry') }}</option>
                @foreach (\App\Models\Lead::INDUSTRIES as $option)
                    <option value="{{ $option }}" @selected(old('industry', $industry) === $option)>{{ (new \App\Models\Lead(['industry' => $option]))->industryLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('How can we help? (optional)') }}</label>
            <textarea name="message" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('message') }}</textarea>
        </div>
        <button type="submit" class="btn-shine w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Request a demo') }}</button>
        <p class="text-center text-xs text-slate-400">{{ __('No credit card required · :days-day free trial', ['days' => config('daftari.trial_days')]) }}</p>
    </form>
</section>
@endsection
