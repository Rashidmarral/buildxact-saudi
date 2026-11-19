@extends('installer.layout')

@php($step = 5)

@section('title', __('Install'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Ready to install') }}</h1>
<p class="mt-1 text-sm text-slate-500">{{ __('Review your settings, then run the installation. This will migrate the database, seed required setup data, and create your administrator account.') }}</p>

<dl class="mt-6 space-y-4 text-sm">
    <div class="rounded-lg border border-slate-100 p-4">
        <dt class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">{{ __('Database') }}</dt>
        <dd class="text-slate-700">{{ $database['host'] }}:{{ $database['port'] }} / {{ $database['database'] }} ({{ $database['username'] }})</dd>
    </div>
    <div class="rounded-lg border border-slate-100 p-4">
        <dt class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">{{ __('Application') }}</dt>
        <dd class="text-slate-700">{{ $application['name'] }} · {{ $application['url'] }} · {{ $application['timezone'] }} · {{ strtoupper($application['locale']) }} · {{ $application['currency'] }}</dd>
    </div>
    <div class="rounded-lg border border-slate-100 p-4">
        <dt class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">{{ __('Administrator') }}</dt>
        <dd class="text-slate-700">{{ $admin['name'] }} &lt;{{ $admin['email'] }}&gt;</dd>
    </div>
</dl>

<form method="POST" action="{{ route('install.finish.store') }}" class="mt-8 flex items-center justify-between gap-4">
    @csrf
    <a href="{{ route('install.admin') }}" class="text-sm font-semibold text-slate-500 hover:text-brand-700">{{ __('Back') }}</a>
    <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Install now') }}</button>
</form>
@endsection
