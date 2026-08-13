@extends('layouts.app')

@section('title', __('Purchase orders'))

@section('content')
<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    <a href="{{ route('app.bills.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Bills') }}</a>
    <a href="{{ route('app.purchase-orders.index') }}" class="rounded-lg px-3 py-1.5 bg-slate-900 text-white">{{ __('Purchase orders') }}</a>
    <a href="{{ route('app.suppliers.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Suppliers') }}</a>
</div>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Approve and track orders before they become bills.') }}</p>
    <a href="{{ route('app.purchase-orders.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New purchase order') }}</a>
</div>

<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    @foreach ([
        '' => __('All') . " ({$counts['all']})",
        'draft' => __('Draft') . " ({$counts['draft']})",
        'approved' => __('Approved') . " ({$counts['approved']})",
        'converted' => __('Converted') . " ({$counts['converted']})",
        'void' => __('Void') . " ({$counts['void']})",
    ] as $value => $label)
        <a href="{{ route('app.purchase-orders.index', array_filter(['status' => $value ?: null])) }}"
           class="rounded-full border px-3 py-1 {{ request('status', '') === $value ? 'border-brand-500 text-brand-700 bg-brand-50' : 'border-slate-200 text-slate-500 hover:border-slate-300' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($orders->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No purchase orders yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Number') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Supplier') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Order date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Total') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('app.purchase-orders.show', $order) }}'">
                        <td class="px-6 py-3 font-medium text-brand-700">{{ $order->po_number }}</td>
                        <td class="px-6 py-3">{{ $order->supplier->name }}</td>
                        <td class="px-6 py-3">{{ $order->order_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">SAR {{ number_format($order->total, 2) }}</td>
                        <td class="px-6 py-3">@include('user.purchase-orders.partials.status-badge', ['status' => $order->status])</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
