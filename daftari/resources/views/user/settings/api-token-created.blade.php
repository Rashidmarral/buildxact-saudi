@extends('layouts.app')

@section('title', __('API token created'))

@section('content')
<div class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6">
    <h1 class="text-lg font-semibold text-slate-900">{{ __('Copy your new API token') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('This is the only time the full token will be shown. Store it somewhere safe — you will not be able to view it again.') }}</p>

    <div class="mt-5 rounded-lg border border-slate-100 bg-slate-50 p-4">
        <code class="block break-all font-mono text-sm text-slate-800" id="api-token-value">{{ $plainTextToken }}</code>
    </div>

    <a href="{{ route('app.settings.api-tokens') }}" class="mt-6 inline-block rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __("I've copied this token") }}</a>
</div>
@endsection
