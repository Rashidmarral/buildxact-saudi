@extends('layouts.app')

@section('title', __('Account Statement'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Account Statement Report') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Invoices and payments for a single customer, with a running balance.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.reports.account-statement', array_filter(['type' => $type, 'client_id' => request('client_id'), 'supplier_id' => request('supplier_id'), 'period' => $period['preset'], 'from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString(), 'export' => 'pdf'])) }}"
           class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold {{ $party ? 'text-slate-600 hover:border-slate-300' : 'text-slate-300 pointer-events-none' }}">{{ __('Download PDF') }}</a>
    </div>
</div>

<div class="flex items-center gap-2 mb-6 text-sm border-b border-slate-100">
    <a href="{{ route('app.reports.account-statement', ['type' => 'customer']) }}" class="px-3 py-2 border-b-2 {{ $type === 'customer' ? 'border-brand-600 text-brand-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">{{ __('Customer') }}</a>
    <a href="{{ route('app.reports.account-statement', ['type' => 'supplier']) }}" class="px-3 py-2 border-b-2 {{ $type === 'supplier' ? 'border-brand-600 text-brand-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">{{ __('Supplier') }}</a>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-center gap-3">
    <input type="hidden" name="type" value="{{ $type }}">
    @if ($type === 'customer')
        <select name="client_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[16rem]">
            <option value="">{{ __('Select a customer') }}</option>
            @foreach ($clients as $c)<option value="{{ $c->id }}" @selected($party && $party->id === $c->id)>{{ $c->name }}</option>@endforeach
        </select>
    @else
        <select name="supplier_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[16rem]">
            <option value="">{{ __('Select a supplier') }}</option>
            @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected($party && $party->id === $s->id)>{{ $s->name }}</option>@endforeach
        </select>
    @endif

    <label class="text-xs font-semibold uppercase text-slate-500">{{ __('Period') }}</label>
    <select name="period" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="this_month" @selected($period['preset'] === 'this_month')>{{ __('This month') }}</option>
        <option value="last_month" @selected($period['preset'] === 'last_month')>{{ __('Last month') }}</option>
        <option value="this_quarter" @selected($period['preset'] === 'this_quarter')>{{ __('This quarter') }}</option>
        <option value="this_year" @selected($period['preset'] === 'this_year')>{{ __('Current year') }}</option>
        <option value="last_year" @selected($period['preset'] === 'last_year')>{{ __('Last year') }}</option>
        <option value="custom" @selected($period['preset'] === 'custom')>{{ __('Custom') }}</option>
    </select>
    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Apply') }}</button>
    @if ($party)
        <a href="{{ route('app.reports.account-statement', array_filter(['type' => $type, 'client_id' => request('client_id'), 'supplier_id' => request('supplier_id'), 'period' => $period['preset'], 'export' => 'csv'])) }}" class="ms-auto rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
    @endif
</form>

@if ($party)
    @php
        $isCustomer = $type === 'customer';
        $agingTotal = array_sum($agingBuckets ?? []);
    @endphp

    <div class="grid sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ $isCustomer ? __('Total invoiced') : __('Total billed') }}</p>
            <p class="text-xl font-semibold text-slate-900 mt-1">{{ \App\Support\Money::format($totalInvoiced) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ $isCustomer ? __('Total received') : __('Total paid') }}</p>
            <p class="text-xl font-semibold text-teal-700 mt-1">{{ \App\Support\Money::format($totalReceived) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ $isCustomer ? __('Balance due') : __('Amount owed') }}</p>
            <p class="text-xl font-semibold {{ $closingBalance > 0 ? 'text-amber-600' : 'text-slate-900' }} mt-1">{{ \App\Support\Money::format($closingBalance) }}</p>
        </div>
    </div>

    @if ($agingTotal > 0.004)
        <div class="bg-white rounded-xl border border-slate-100 mb-6 p-5">
            <p class="text-xs font-semibold uppercase text-slate-500 mb-3">{{ __('Aging (as of today)') }}</p>
            <div class="grid grid-cols-5 gap-3 text-center">
                <div><p class="text-[11px] text-slate-400 uppercase">{{ __('Current') }}</p><p class="text-sm font-semibold text-teal-700 mt-1">{{ \App\Support\Money::format($agingBuckets['current']) }}</p></div>
                <div><p class="text-[11px] text-slate-400 uppercase">{{ __('1-30 days') }}</p><p class="text-sm font-semibold text-slate-900 mt-1">{{ \App\Support\Money::format($agingBuckets['days_1_30']) }}</p></div>
                <div><p class="text-[11px] text-slate-400 uppercase">{{ __('31-60 days') }}</p><p class="text-sm font-semibold text-slate-900 mt-1">{{ \App\Support\Money::format($agingBuckets['days_31_60']) }}</p></div>
                <div><p class="text-[11px] text-slate-400 uppercase">{{ __('61-90 days') }}</p><p class="text-sm font-semibold text-slate-900 mt-1">{{ \App\Support\Money::format($agingBuckets['days_61_90']) }}</p></div>
                <div><p class="text-[11px] text-slate-400 uppercase">{{ __('90+ days') }}</p><p class="text-sm font-semibold text-red-600 mt-1">{{ \App\Support\Money::format($agingBuckets['days_over_90']) }}</p></div>
            </div>
        </div>
    @endif

    @if ($outstandingRows->isNotEmpty())
        <div class="bg-white rounded-xl border border-slate-100 mb-6">
            <div class="px-5 py-3 border-b border-slate-100">
                <h3 class="text-sm font-semibold text-slate-900">{{ $isCustomer ? __('Outstanding invoices') : __('Outstanding bills') }}</h3>
                <p class="text-xs text-slate-400 mt-0.5">{{ __('As of today — independent of the period filter above.') }}</p>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-5 py-2.5 font-medium">{{ $isCustomer ? __('Invoice No.') : __('Bill No.') }}</th>
                        <th class="px-5 py-2.5 font-medium">{{ __('Date') }}</th>
                        <th class="px-5 py-2.5 font-medium">{{ __('Due date') }}</th>
                        <th class="px-5 py-2.5 font-medium text-end">{{ __('Total') }}</th>
                        <th class="px-5 py-2.5 font-medium text-end">{{ __('Balance due') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($outstandingRows as $row)
                        @php $overdue = $row['due_date'] && $row['due_date']->lt(now()->startOfDay()); @endphp
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-5 py-2.5 font-medium text-slate-800">{{ $row['number'] }}</td>
                            <td class="px-5 py-2.5 text-slate-500">{{ $row['date']->format('Y-m-d') }}</td>
                            <td class="px-5 py-2.5 text-slate-500">
                                {{ $row['due_date']?->format('Y-m-d') ?? '—' }}
                                @if ($overdue)<span class="ms-1 inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-700">{{ __('OVERDUE') }}</span>@endif
                            </td>
                            <td class="px-5 py-2.5 text-end">{{ number_format($row['total'], 2) }}</td>
                            <td class="px-5 py-2.5 text-end font-semibold">{{ number_format($row['balance'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-100">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-5 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-5 py-3 font-medium">{{ __('Description') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Debit') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Credit') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-slate-50 text-slate-400 italic">
                    <td class="px-5 py-3" colspan="4">{{ __('Opening balance') }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format($openingBalance, 2) }}</td>
                </tr>
                @forelse ($lines as $line)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-3 text-slate-500">{{ $line->date->format('Y-m-d') }}</td>
                        <td class="px-5 py-3">{{ $line->description }}</td>
                        <td class="px-5 py-3 text-end">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                        <td class="px-5 py-3 text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                        <td class="px-5 py-3 text-end font-medium">{{ number_format($line->balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">{{ __('No activity for this customer in the selected period.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@else
    <div class="bg-white rounded-xl border border-slate-100 px-6 py-16 text-center text-sm text-slate-400">{{ __('Select a customer or supplier to view the statement.') }}</div>
@endif
@endsection
