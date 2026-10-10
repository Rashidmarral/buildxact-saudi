@extends('layouts.portal')

@section('title', __('Sign in'))

@section('content')
<div class="mx-auto max-w-sm bg-white rounded-xl border border-slate-100 p-6">
    <h1 class="text-lg font-semibold text-slate-900 mb-1">{{ __('Client sign-in') }}</h1>
    <p class="text-sm text-slate-500 mb-5">{{ __("Enter the email address on file with your supplier, and we'll send you a one-time sign-in link.") }}</p>

    @if (session('status'))
        <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('portal.login.send') }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Email address') }}</label>
            <input type="email" name="email" required autofocus class="w-full rounded-lg border border-slate-200 text-sm">
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Send sign-in link') }}</button>
    </form>
</div>
@endsection
