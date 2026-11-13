@extends('layouts.auth')

@section('title', __('Accept invitation'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Join :company', ['company' => $member->company->name]) }}</h1>
<p class="mt-2 text-sm text-slate-500">{{ __('Set a password to activate your account, :name.', ['name' => $member->name]) }}</p>

<form method="POST" action="{{ url()->full() }}" class="mt-6 space-y-5">
    @csrf
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
        <input type="email" value="{{ $member->email }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 text-slate-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
        <input type="password" name="password" required autofocus class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        <p class="mt-1 text-xs text-slate-400">{{ __('At least 8 characters.') }}</p>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Confirm password') }}</label>
        <input type="password" name="password_confirmation" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <button type="submit" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Activate account') }}</button>
</form>
@endsection
