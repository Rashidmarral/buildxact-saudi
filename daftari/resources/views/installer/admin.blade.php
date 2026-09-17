@extends('installer.layout')

@php($step = 4)

@section('title', __('Administrator'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Create the first administrator') }}</h1>
<p class="mt-1 text-sm text-slate-500">{{ __('This account has full platform access — you can add more administrators later.') }}</p>

<form method="POST" action="{{ route('install.admin.store') }}" class="mt-6 space-y-5">
    @csrf
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
        <input type="text" name="name" value="{{ old('name', $values['name'] ?? '') }}" required autofocus class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
        <input type="email" name="email" value="{{ old('email', $values['email'] ?? '') }}" required autocomplete="off" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
            <input type="password" name="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            <p class="mt-1 text-xs text-slate-400">{{ __('At least 8 characters.') }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Confirm password') }}</label>
            <input type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="flex items-center justify-between gap-4 pt-2">
        <a href="{{ route('install.application') }}" class="text-sm font-semibold text-slate-500 hover:text-brand-700">{{ __('Back') }}</a>
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Continue') }}</button>
    </div>
</form>
@endsection
