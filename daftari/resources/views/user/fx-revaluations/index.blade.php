@extends('layouts.app')

@section('title', __('FX Revaluations'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('FX Revaluations') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Unrealized gain/loss on open foreign-currency invoices and bills, revalued against a current rate you provide.') }}</p>
    </div>
    <a href="{{ route('app.fx-revaluations.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('New revaluation') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($revaluations->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No FX revaluations yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('As of') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Journal entry') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Created') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($revaluations as $revaluation)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3">{{ $revaluation->as_of_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">
                            @if ($revaluation->isActive())
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">{{ __('Active') }}</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">{{ __('Superseded') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-slate-500">{{ $revaluation->journalEntry?->entry_number ?? '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $revaluation->created_at->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('app.fx-revaluations.show', $revaluation) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('View') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $revaluations->links() }}</div>
@endsection
