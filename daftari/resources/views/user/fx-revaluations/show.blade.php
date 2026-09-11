@extends('layouts.app')

@section('title', __('FX Revaluation'))

@php
    $company = Auth::user()->company;
    $totalUnrealized = $fxRevaluation->lines->sum(fn ($line) => $line->document_type === 'bill' ? -$line->unrealized_gain_loss : $line->unrealized_gain_loss);
@endphp

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('FX Revaluation') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('As of') }} {{ $fxRevaluation->as_of_date->format('Y-m-d') }}</p>
    </div>
    <div class="flex items-center gap-3">
        @if ($fxRevaluation->isActive())
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">{{ __('Active') }}</span>
        @else
            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">{{ __('Superseded by a later revaluation') }}</span>
        @endif
        @if ($fxRevaluation->journalEntry)
            <a href="{{ route('app.journals.show', $fxRevaluation->journalEntry) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('View journal entry') }}</a>
        @endif
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto mb-6">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Document') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Party') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Currency') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Balance') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Booked rate') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Revaluation rate') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Unrealized') }} ({{ $company->currency }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($fxRevaluation->lines as $line)
                @php $displayed = $line->document_type === 'bill' ? -$line->unrealized_gain_loss : $line->unrealized_gain_loss; @endphp
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-6 py-3 text-slate-800">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500 me-1">{{ $line->document_type === 'invoice' ? __('AR') : __('AP') }}</span>
                        {{ $line->document_number }}
                    </td>
                    <td class="px-6 py-3 text-slate-600">{{ $line->party_name }}</td>
                    <td class="px-6 py-3 text-slate-600">{{ $line->currency }}</td>
                    <td class="px-6 py-3 text-end">{{ number_format($line->foreign_balance, 2) }}</td>
                    <td class="px-6 py-3 text-end">{{ number_format($line->booked_rate, 6) }}</td>
                    <td class="px-6 py-3 text-end">{{ number_format($line->revaluation_rate, 6) }}</td>
                    <td class="px-6 py-3 text-end font-medium {{ $displayed < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ number_format($displayed, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-slate-800 font-semibold text-slate-900">
                <td class="px-6 py-3" colspan="6">{{ __('Total unrealized gain/loss') }}</td>
                <td class="px-6 py-3 text-end {{ $totalUnrealized < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ number_format($totalUnrealized, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</div>

@if ($fxRevaluation->notes)
    <div class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <p class="text-xs font-medium text-slate-500 mb-1">{{ __('Notes') }}</p>
        <p class="text-sm text-slate-700 whitespace-pre-line">{{ $fxRevaluation->notes }}</p>
    </div>
@endif

<a href="{{ route('app.fx-revaluations.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Back to all revaluations') }}</a>
@endsection
