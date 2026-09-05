@extends('installer.layout')

@php($step = 3)

@section('title', __('Application'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Application settings') }}</h1>
<p class="mt-1 text-sm text-slate-500">{{ __('These become the defaults for this installation — every value can be changed later from Platform Settings.') }}</p>

<form method="POST" action="{{ route('install.application.store') }}" class="mt-6 space-y-5">
    @csrf
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Application name') }}</label>
        <input type="text" name="name" value="{{ old('name', $values['name'] ?? '') }}" required autofocus class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Application URL') }}</label>
        <input type="url" name="url" value="{{ old('url', $values['url'] ?? url('/')) }}" required placeholder="https://example.com" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Timezone') }}</label>
            <select name="timezone" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @foreach ($timezones as $tz)
                    <option value="{{ $tz }}" @selected(old('timezone', $values['timezone'] ?? '') === $tz)>{{ $tz }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Language') }}</label>
            <select name="locale" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @foreach ($locales as $code => $meta)
                    <option value="{{ $code }}" @selected(old('locale', $values['locale'] ?? '') === $code)>{{ $meta['native'] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Currency') }}</label>
            <select name="currency" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @foreach ($currencies as $code)
                    <option value="{{ $code }}" @selected(old('currency', $values['currency'] ?? '') === $code)>{{ $code }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="flex items-center justify-between gap-4 pt-2">
        <a href="{{ route('install.database') }}" class="text-sm font-semibold text-slate-500 hover:text-brand-700">{{ __('Back') }}</a>
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Continue') }}</button>
    </div>
</form>
@endsection
