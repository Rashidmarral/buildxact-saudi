@extends('layouts.app')

@section('title', __('Account Statement'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Account Statement Report') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Invoices and payments for a single customer, with a running balance.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.reports.account-statement', array_filter(['type' => $type, 'client_id' => request('client_id'), 'supplier_id' => request('supplier_id'), 'period' => $period['preset'], 'from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString(), 'export' => 'pdf-ar'])) }}"
           class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold {{ $party ? 'text-slate-600 hover:border-slate-300' : 'text-slate-300 pointer-events-none' }}">{{ __('Download Arabic') }}</a>
        <a href="{{ route('app.reports.account-statement', array_filter(['type' => $type, 'client_id' => request('client_id'), 'supplier_id' => request('supplier_id'), 'period' => $period['preset'], 'from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString(), 'export' => 'pdf-en'])) }}"
           class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold {{ $party ? 'text-slate-600 hover:border-slate-300' : 'text-slate-300 pointer-events-none' }}">{{ __('Download English') }}</a>
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
