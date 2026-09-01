@extends('layouts.app')

@section('title', __('Bills'))

@section('content')
<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    <a href="{{ route('app.bills.index') }}" class="rounded-lg px-3 py-1.5 bg-slate-900 text-white">{{ __('Bills') }}</a>
    <a href="{{ route('app.purchase-orders.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Purchase orders') }}</a>
    <a href="{{ route('app.suppliers.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Suppliers') }}</a>
</div>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Bills recorded from your suppliers.') }}</p>
    <div class="flex items-center gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Export CSV') }}</a>
        <a href="{{ route('app.bills.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New bill') }}</a>
    </div>
</div>

<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    @foreach ([
        '' => __('All') . " ({$counts['all']})",
        'draft' => __('Draft') . " ({$counts['draft']})",
        'posted' => __('Posted') . " ({$counts['posted']})",
        'void' => __('Void') . " ({$counts['void']})",
    ] as $value => $label)
        <a href="{{ route('app.bills.index', array_filter(['status' => $value ?: null])) }}"
           class="rounded-full border px-3 py-1 {{ request('status', '') === $value ? 'border-brand-500 text-brand-700 bg-brand-50' : 'border-slate-200 text-slate-500 hover:border-slate-300' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($bills->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No bills yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Number') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Supplier') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Total') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Balance') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bills as $bill)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('app.bills.show', $bill) }}'">
                        <td class="px-6 py-3 font-medium text-brand-700">{{ $bill->bill_number }}</td>
                        <td class="px-6 py-3">{{ $bill->supplier->name }}</td>
                        <td class="px-6 py-3">{{ $bill->bill_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ \App\Support\Money::format($bill->total) }}</td>
                        <td class="px-6 py-3">{{ \App\Support\Money::format($bill->balanceDue()) }}</td>
                        <td class="px-6 py-3">@include('user.bills.partials.status-badge', ['status' => $bill->status])</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@include('partials.pagination', ['paginator' => $bills])
@endsection
