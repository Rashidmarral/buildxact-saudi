@extends('layouts.auth')

@section('title', __('Invitation not available'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('This invitation is no longer valid') }}</h1>
<p class="mt-2 text-sm text-slate-500">{{ __('It may have already been accepted. Ask your account owner to send a new invite.') }}</p>

<p class="mt-6 text-center text-sm text-slate-500">
    <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Back to log in') }}</a>
</p>
@endsection
