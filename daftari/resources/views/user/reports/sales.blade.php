@extends('layouts.app')

@section('title', __('Sales Report'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Sales Report') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Detailed sales analytics with advanced filters') }}</p>
    </div>
    <a href="{{ route('app.reports.sales', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
</div>

@include('user.reports.partials.period-selector')

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 grid sm:grid-cols-3 gap-3">
    <input type="hidden" name="period" value="{{ $period['preset'] }}">
    <input type="hidden" name="from" value="{{ $period['from']->toDateString() }}">
    <input type="hidden" name="to" value="{{ $period['to']->toDateString() }}">
    <div>
        <label class="block text-xs font-medium text-slate-500">{{ __('Customer') }}</label>
        <select name="client_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">{{ __('All customers') }}</option>
            @foreach ($clients as $client)<option value="{{ $client->id }}" @selected(request('client_id') == $client->id)>{{ $client->name }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-500">{{ __('Item') }}</label>
        <select name="item_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">{{ __('All items') }}</option>
            @foreach ($products as $product)<option value="{{ $product->id }}" @selected(request('item_id') == $product->id)>{{ $product->name }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-500">{{ __('Salesperson') }}</label>
        <select name="salesperson_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">{{ __('Unassigned') }}</option>
            @foreach ($salespersons as $sp)<option value="{{ $sp->id }}" @selected(request('salesperson_id') == $sp->id)>{{ $sp->name }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-500">{{ __('Invoice status') }}</label>
        <select name="status" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (['draft','sent','paid','partially_paid','overdue','cancelled'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-500">{{ __('Search') }}</label>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by invoice, customer, item, or description') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div class="grid grid-cols-2 gap-2">
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Min amount') }}</label>
            <input type="number" step="0.01" name="min_amount" value="{{ request('min_amount') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Max amount') }}</label>
            <input type="number" step="0.01" name="max_amount" value="{{ request('max_amount') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-500">{{ __('Sort by') }}</label>
        <select name="sort" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="date" @selected(request('sort','date') === 'date')>{{ __('Date') }}</option>
            <option value="amount" @selected(request('sort') === 'amount')>{{ __('Amount') }}</option>
            <option value="customer" @selected(request('sort') === 'customer')>{{ __('Customer') }}</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-500">{{ __('Sort order') }}</label>
        <select name="direction" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="desc" @selected(request('direction','desc') === 'desc')>{{ __('Newest first') }}</option>
            <option value="asc" @selected(request('direction') === 'asc')>{{ __('Oldest first') }}</option>
        </select>
    </div>
    <div class="sm:col-span-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filter') }}</button>
    </div>
</form>

<div class="grid sm:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-4"><p class="text-xs text-slate-400">{{ __('Invoices') }}</p><p class="text-xl font-bold text-slate-900 mt-1">{{ $invoiceCount }}</p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-4"><p class="text-xs text-slate-400">{{ __('Line items') }}</p><p class="text-xl font-bold text-slate-900 mt-1">{{ $lineCount }}</p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-4"><p class="text-xs text-slate-400">{{ __('Discounts') }}</p><p class="text-xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($discountTotal) }}</p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-4"><p class="text-xs text-slate-400">{{ __('Taxable sales') }}</p><p class="text-xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($taxableSales) }}</p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-4"><p class="text-xs text-slate-400">{{ __('Tax') }}</p><p class="text-xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($taxTotal) }}</p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-4"><p class="text-xs text-slate-400">{{ __('Total sales') }}</p><p class="text-xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($totalSales) }}</p></div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6">
    <h3 class="font-semibold text-slate-900 mb-1">{{ __('Sales lines') }}</h3>
    <p class="text-sm text-slate-500 mb-4">{{ __('Invoice lines based on selected filters.') }}</p>

    @if ($items->isEmpty())
        <div class="py-16 text-center">
            <p class="font-semibold text-slate-800">{{ __('No invoices in this period') }}</p>
            <p class="text-sm text-slate-500 mt-1">{{ __('No invoices were issued in the selected range. Try widening the date range or creating a new invoice.') }}</p>
            <a href="{{ route('app.invoices.create') }}" class="inline-block mt-4 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Create invoice') }}</a>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="py-2">{{ __('Date') }}</th>
                        <th class="py-2">{{ __('Invoice') }}</th>
                        <th class="py-2">{{ __('Customer') }}</th>
                        <th class="py-2">{{ __('Description') }}</th>
                        <th class="py-2 text-end">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $row)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="py-2 text-slate-500">{{ $row->invoice->issue_date->format('Y-m-d') }}</td>
                            <td class="py-2"><a href="{{ route('app.invoices.show', $row->invoice) }}" class="text-brand-700 hover:underline">{{ $row->invoice->invoice_number }}</a></td>
                            <td class="py-2">{{ $row->invoice->client->name ?? '—' }}</td>
                            <td class="py-2">{{ $row->line->description }}</td>
                            <td class="py-2 text-end">{{ \App\Support\Money::format($row->line->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
