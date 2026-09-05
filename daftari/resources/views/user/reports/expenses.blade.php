@extends('layouts.app')

@section('title', __('Expense Report'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Expense Report') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Expenses recorded for the selected period.') }}</p>
    </div>
    <a href="{{ route('app.reports.expenses', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
</div>

@include('user.reports.partials.period-selector')

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-center gap-3">
    <input type="hidden" name="period" value="{{ $period['preset'] }}">
    <input type="hidden" name="from" value="{{ $period['from']->toDateString() }}">
    <input type="hidden" name="to" value="{{ $period['to']->toDateString() }}">
    <select name="category_id" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">{{ __('All categories') }}</option>
        @foreach ($categories as $cat)<option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>@endforeach
    </select>
    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by code or name') }}" class="flex-1 min-w-[12rem] rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filter') }}</button>
</form>

<div class="grid sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5"><p class="text-xs text-slate-400">{{ __('Total expenses') }}</p><p class="text-2xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($total) }}</p></div>
    <div class="bg-white rounded-xl border border-slate-100 p-5"><p class="text-xs text-slate-400">{{ __('VAT') }}</p><p class="text-2xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($vatTotal) }}</p></div>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-5 py-3 font-medium">{{ __('Date') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Vendor') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Category') }}</th>
                <th class="px-5 py-3 font-medium">{{ __('Description') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('Amount') }}</th>
                <th class="px-5 py-3 font-medium text-end">{{ __('VAT') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expenses as $expense)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-5 py-3 text-slate-500">{{ $expense->expense_date->format('Y-m-d') }}</td>
                    <td class="px-5 py-3">{{ $expense->vendor_name ?: '—' }}</td>
                    <td class="px-5 py-3">{{ $expense->category->name ?? '—' }}</td>
                    <td class="px-5 py-3">{{ $expense->description }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($expense->amount) }}</td>
                    <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($expense->vat_amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">{{ __('No expenses in this period.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
