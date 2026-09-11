@extends('installer.layout')

@php($step = 6)

@section('title', __('Complete'))

@section('content')
<div class="text-center">
    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
        @include('partials.icon', ['name' => 'check-circle', 'class' => 'h-8 w-8'])
    </span>
    <h1 class="mt-4 text-xl font-bold text-slate-900">{{ __('Installation successful') }}</h1>
    <p class="mt-1 text-sm text-slate-500">{{ __('Daftari is ready. Sign in with the administrator account you just created.') }}</p>
</div>

<ul class="mt-6 space-y-2 text-sm">
    @foreach ($log as $line)
        <li class="flex items-center gap-2 text-slate-600">
            <span class="inline-flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                @include('partials.icon', ['name' => 'check-circle', 'class' => 'h-3 w-3'])
            </span>
            {{ $line }}
        </li>
    @endforeach
</ul>

<div class="mt-6 rounded-lg border border-slate-100 bg-slate-50 p-4 text-sm">
    <p class="text-slate-500">{{ __('Administrator panel URL') }}</p>
    <p class="mt-0.5 break-all font-mono text-slate-800">{{ $adminUrl }}</p>
    <p class="mt-3 text-slate-500">{{ __('Signed in as') }}</p>
    <p class="mt-0.5 font-medium text-slate-800">{{ $adminEmail }}</p>
</div>

<a href="{{ $loginUrl }}" class="mt-8 block w-full rounded-lg bg-brand-600 px-6 py-3 text-center font-semibold text-white hover:bg-brand-700">{{ __('Log in') }}</a>

<p class="mt-4 text-center text-xs text-slate-400">{{ __('For security, /install is now disabled. Re-enable it from the server with php artisan installer:enable if you ever need to run it again.') }}</p>
@endsection
