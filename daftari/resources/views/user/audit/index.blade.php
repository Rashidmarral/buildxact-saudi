@extends('layouts.app')

@section('title', __('Company Audit'))

@section('content')
@php
    $badge = match ($overallStatus) {
        'ok' => ['bg-emerald-50 text-emerald-700 border-emerald-200', __('Audit-ready')],
        'warning' => ['bg-amber-50 text-amber-700 border-amber-200', __('Needs attention')],
        default => ['bg-red-50 text-red-700 border-red-200', __('Issues found')],
    };
    $issueCount = collect($sections)->sum(fn ($s) => $s['items']->count());
@endphp

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Company Audit') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('A self-check of your books and ZATCA compliance for the selected period — no accountant required to read it.') }}</p>
    </div>
    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-semibold {{ $badge[0] }}">
        {{ $badge[1] }}
    </span>
</div>

@include('user.reports.partials.period-selector')

@if ($issueCount === 0)
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-6 mb-6 text-center">
        <p class="font-semibold text-emerald-800">{{ __('No issues found for this period.') }}</p>
        <p class="mt-1 text-sm text-emerald-700">{{ __('Your books balance, every posted document has a ledger entry, and your ZATCA submissions and company profile are complete.') }}</p>
    </div>
@else
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 mb-6">
        <p class="font-semibold text-amber-800">{{ __(':count item(s) need attention before this period is fully audit-ready.', ['count' => $issueCount]) }}</p>
        <p class="mt-1 text-sm text-amber-700">{{ __('Review each flagged section below — most are a single click away from being fixed.') }}</p>
    </div>
@endif

<div class="space-y-6">
    @foreach ($sections as $section)
        @php
            $sectionBadge = match ($section['status']) {
                'ok' => ['bg-emerald-50 text-emerald-700 border-emerald-200', __('OK')],
                'warning' => ['bg-amber-50 text-amber-700 border-amber-200', __('Warning')],
                default => ['bg-red-50 text-red-700 border-red-200', __('Critical')],
            };
        @endphp
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-1">
                <h3 class="font-semibold text-slate-900">{{ $section['label'] }}</h3>
                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $sectionBadge[0] }}">{{ $sectionBadge[1] }}</span>
            </div>
            <p class="text-sm text-slate-600">{{ $section['summary'] }}</p>

            @if ($section['items']->isNotEmpty())
                <ul class="mt-4 divide-y divide-slate-50 border-t border-slate-100">
                    @foreach ($section['items'] as $item)
                        <li class="py-2 flex items-center justify-between text-sm">
                            <span class="text-slate-700">{{ $item['label'] }}</span>
                            <a href="{{ $item['url'] }}" class="text-brand-600 hover:underline font-medium">{{ __('View') }} &rarr;</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
</div>
@endsection
