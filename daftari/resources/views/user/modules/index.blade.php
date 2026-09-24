@extends('layouts.app')

@section('title', __('Modules'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Modules') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('Optional paid add-ons for your account. Request one below and our team will enable it once it\'s arranged — it then appears in your sidebar automatically.') }}</p>
</div>

@if (session('status'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($modules as $module)
        <div class="rounded-xl border border-slate-100 bg-white p-5 flex flex-col">
            <div class="flex items-start justify-between mb-2">
                <h3 class="font-semibold text-slate-900">{{ $module['label'] }}</h3>
                @if ($module['enabled'])
                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Installed') }}</span>
                @elseif ($module['latestRequest']?->status === 'requested')
                    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">{{ __('Pending review') }}</span>
                @elseif ($module['latestRequest']?->status === 'rejected')
                    <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700">{{ __('Not approved') }}</span>
                @else
                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500">{{ __('Available') }}</span>
                @endif
            </div>

            <p class="text-lg font-bold text-slate-900 mb-1">
                @if ($module['price'] !== null)
                    {{ number_format($module['price'], 2) }} {{ __('SAR') }} <span class="text-xs font-normal text-slate-400">/ {{ __('month') }}</span>
                @else
                    <span class="text-sm font-medium text-slate-500">{{ __('Contact sales for pricing') }}</span>
                @endif
            </p>

            @if ($module['latestRequest']?->status === 'rejected' && $module['latestRequest']->admin_note)
                <p class="text-xs text-red-600 mb-3">{{ $module['latestRequest']->admin_note }}</p>
            @endif

            <div class="mt-auto pt-3">
                @if ($module['enabled'])
                    <p class="text-xs text-slate-400">{{ __('This module is active on your account.') }}</p>
                @elseif ($module['latestRequest']?->status === 'requested')
                    <button type="button" disabled class="w-full rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-400">{{ __('Request sent') }}</button>
                @else
                    <form method="POST" action="{{ route('app.modules.request', $module['key']) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                            {{ $module['latestRequest']?->status === 'rejected' ? __('Request again') : __('Request to install') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection
