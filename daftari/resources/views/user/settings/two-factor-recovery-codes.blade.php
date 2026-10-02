@extends('layouts.app')

@section('title', __('Recovery codes'))

@section('content')
<div class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6">
    <h1 class="text-lg font-semibold text-slate-900">{{ __('Save your recovery codes') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('Each code can be used once to sign in if you lose access to your authenticator app. Store them somewhere safe — this is the only time they will be shown.') }}</p>

    <div class="mt-5 grid grid-cols-2 gap-2 rounded-lg border border-slate-100 bg-slate-50 p-5 font-mono text-sm">
        @foreach ($codes as $code)
            <div class="text-slate-800">{{ $code }}</div>
        @endforeach
    </div>

    <a href="{{ route('app.settings.two-factor') }}" class="mt-6 inline-block rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __("I've saved these codes") }}</a>
</div>
@endsection
