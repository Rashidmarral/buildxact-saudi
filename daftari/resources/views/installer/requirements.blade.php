@extends('installer.layout')

@php($step = 1)

@section('title', __('Requirements'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Requirements check') }}</h1>
<p class="mt-1 text-sm text-slate-500">{{ __('Everything below must pass before installation can continue.') }}</p>

<div class="mt-6 space-y-6">
    @foreach ($grouped as $group => $rows)
        <div>
            <h2 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">{{ $group }}</h2>
            <ul class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-100">
                @foreach ($rows as $row)
                    <li class="flex items-center justify-between gap-4 px-4 py-2.5 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-700">{{ $row['label'] }}</p>
                            <p class="truncate text-xs text-slate-400">{{ $row['detail'] }}</p>
                        </div>
                        @if ($row['status'] === 'pass')
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                @include('partials.icon', ['name' => 'check-circle', 'class' => 'h-3.5 w-3.5'])
                                {{ __('Passed') }}
                            </span>
                        @elseif ($row['status'] === 'warn')
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                @include('partials.icon', ['name' => 'alert', 'class' => 'h-3.5 w-3.5'])
                                {{ __('Warning') }}
                            </span>
                        @else
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                                @include('partials.icon', ['name' => 'close', 'class' => 'h-3.5 w-3.5'])
                                {{ __('Failed') }}
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>

<form method="POST" action="{{ route('install.requirements.store') }}" class="mt-8 flex items-center justify-between gap-4">
    @csrf
    <a href="{{ url()->current() }}" class="text-sm font-semibold text-slate-500 hover:text-brand-700">{{ __('Re-check') }}</a>

    @if ($canContinue)
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Continue') }}</button>
    @else
        <button type="button" disabled class="cursor-not-allowed rounded-lg bg-slate-200 px-6 py-2.5 font-semibold text-slate-400">{{ __('Resolve failed items to continue') }}</button>
    @endif
</form>
@endsection
