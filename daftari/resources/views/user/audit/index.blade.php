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
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-semibold {{ $badge[0] }}">
            {{ $badge[1] }}
        </span>
        <a href="{{ route('app.audit.index', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <a href="{{ route('app.audit.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
    </div>
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

<div class="bg-white rounded-xl border border-slate-100 p-6 mt-6">
    <div class="flex items-center justify-between mb-1">
        <h3 class="font-semibold text-slate-900">{{ __('Transaction detail') }}</h3>
        <span class="text-xs text-slate-400">{{ __(':count entries', ['count' => $transactions->count()]) }}</span>
    </div>
    <p class="text-sm text-slate-600 mb-4">{{ __('Every posted transaction in this period — the same detail included in the downloadable PDF.') }}</p>

    @if ($transactions->isEmpty())
        <p class="text-sm text-slate-400 py-6 text-center">{{ __('No transactions were posted in this period.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="py-2 pe-3 font-medium">{{ __('Date') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Entry') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Description') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Source') }}</th>
                        <th class="py-2 pe-3 font-medium text-end">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $entry)
                        <tr class="border-b border-slate-50 bg-slate-50/60">
                            <td class="py-2 pe-3 text-slate-500">{{ $entry->entry_date->format('Y-m-d') }}</td>
                            <td class="py-2 pe-3"><a href="{{ route('app.journals.show', $entry) }}" class="font-semibold text-brand-700 hover:underline">{{ $entry->entry_number }}</a></td>
                            <td class="py-2 pe-3">{{ $entry->description }}</td>
                            <td class="py-2 pe-3 text-slate-500">
                                {{ $entry->source_label }}
                                @if ($entry->source_url)
                                    <a href="{{ $entry->source_url }}" class="text-brand-600 hover:underline">{{ __('View') }} &rarr;</a>
                                @endif
                            </td>
                            <td class="py-2 pe-3 text-end font-semibold">{{ \App\Support\Money::format($entry->totalDebit()) }}</td>
                        </tr>
                        @foreach ($entry->lines as $line)
                            <tr class="border-b border-slate-50 last:border-0 text-slate-500">
                                <td></td>
                                <td class="py-1 pe-3" colspan="2">{{ $line->account?->name ?? '—' }}</td>
                                <td class="py-1 pe-3"></td>
                                <td class="py-1 pe-3 text-end">
                                    @if ($line->debit > 0)
                                        {{ \App\Support\Money::format($line->debit) }} {{ __('Dr') }}
                                    @elseif ($line->credit > 0)
                                        {{ \App\Support\Money::format($line->credit) }} {{ __('Cr') }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-slate-800 font-semibold text-slate-900">
                        <td colspan="4" class="py-2 pe-3">{{ __('Total') }}</td>
                        <td class="py-2 pe-3 text-end">{{ \App\Support\Money::format($transactionTotals['debit']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
@endsection
