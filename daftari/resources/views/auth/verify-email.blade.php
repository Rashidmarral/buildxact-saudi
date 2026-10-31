@extends('layouts.auth')

@section('title', __('Verify your email'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Verify your email address') }}</h1>
<p class="mt-2 text-sm text-slate-500">{{ __("Thanks for signing up! Before getting started, click the link we emailed to :email to confirm it's really you.", ['email' => auth()->user()->email]) }}</p>

@if (session('status'))
    <div class="mt-4 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">{{ session('status') }}</div>
@endif

@if ($devVerifyUrl)
    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
        <p class="font-semibold">{{ __('Development mode: no email transport is configured, so here is the verification link directly.') }}</p>
        <a href="{{ $devVerifyUrl }}" class="mt-1 block break-all font-mono text-amber-900 underline">{{ $devVerifyUrl }}</a>
    </div>
@endif

<form method="POST" action="{{ route('verification.send') }}" class="mt-6">
    @csrf
    <button type="submit" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Resend verification email') }}</button>
</form>

<form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
    @csrf
    <button type="submit" class="text-sm font-semibold text-slate-500 hover:underline">{{ __('Log out') }}</button>
</form>
@endsection
