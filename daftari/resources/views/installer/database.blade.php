@extends('installer.layout')

@php($step = 2)

@section('title', __('Database'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Database connection') }}</h1>
<p class="mt-1 text-sm text-slate-500">{{ __('Enter the credentials for the MySQL database this installation should use.') }}</p>

<form method="POST" action="{{ route('install.database.store') }}" class="mt-6 space-y-5">
    @csrf
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-slate-700">{{ __('Database host') }}</label>
            <input type="text" name="host" value="{{ old('host', $values['host'] ?? '127.0.0.1') }}" required autofocus placeholder="127.0.0.1" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Port') }}</label>
            <input type="number" name="port" value="{{ old('port', $values['port'] ?? '3306') }}" required min="1" max="65535" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Database name') }}</label>
        <input type="text" name="database" value="{{ old('database', $values['database'] ?? '') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Username') }}</label>
        <input type="text" name="username" value="{{ old('username', $values['username'] ?? '') }}" required autocomplete="off" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
        <input type="password" name="password" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>

    <div class="flex items-center justify-between gap-4 pt-2">
        <a href="{{ route('install.requirements') }}" class="text-sm font-semibold text-slate-500 hover:text-brand-700">{{ __('Back') }}</a>
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Test connection & continue') }}</button>
    </div>
</form>
@endsection
