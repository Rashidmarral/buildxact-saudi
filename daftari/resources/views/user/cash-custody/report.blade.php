@extends('layouts.app')

@section('title', __('Custody Report'))

@section('content')
@include('user.bank-accounts.partials.tabs')

<div class="flex items-center justify-between mb-6">
    <div>
        <a href="{{ route('app.cash-custody.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('← Custody / Advances') }}</a>
        <h1 class="text-xl font-bold text-slate-900 mt-1">{{ __('Custody Report') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('All-time advances given, amounts spent by category, cash returned, and the balance still outstanding — per custodian.') }}</p>
    </div>
    <a href="{{ route('app.cash-custody.pdf') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
</div>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ __('Total advanced') }}</p>
        <p class="text-lg font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($totals['advanced']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ __('Total spent') }}</p>
        <p class="text-lg font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($totals['spent']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ __('Total returned') }}</p>
        <p class="text-lg font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($totals['returned']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-4">
        <p class="text-xs text-slate-400">{{ __('Outstanding now') }}</p>
        <p class="text-lg font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($totals['balance']) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
    @if ($rows->isEmpty())
        <p class="px-6 py-10 text-center text-sm text-slate-400">{{ __('No custody accounts yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Held by') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Advanced') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Spent') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Returned') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-slate-50 last:border-0 align-top">
                        <td class="px-6 py-3">
                            <p class="font-medium text-slate-900">{{ $row['account']->personal_owner_name ?: $row['account']->name }}</p>
                            @if ($row['spent_by_category']->isNotEmpty())
                                <div class="flex flex-wrap gap-1.5 mt-1.5">
                                    @foreach ($row['spent_by_category']->take(4) as $cat)
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">{{ $cat['category'] }}: {{ \App\Support\Money::format($cat['total']) }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right tabular-nums">{{ \App\Support\Money::format($row['advanced']) }}</td>
                        <td class="px-6 py-3 text-right tabular-nums">{{ \App\Support\Money::format($row['spent']) }}</td>
                        <td class="px-6 py-3 text-right tabular-nums">{{ \App\Support\Money::format($row['returned']) }}</td>
                        <td class="px-6 py-3 text-right font-semibold tabular-nums">{{ \App\Support\Money::format($row['balance']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
