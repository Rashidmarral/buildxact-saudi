@extends('layouts.app')

@section('title', __('Restaurant Orders'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Restaurant Orders') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Start a dine-in order from an available table, or ring up a takeaway order directly.') }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('app.restaurant.kitchen.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Kitchen display') }}</a>
        <a href="{{ route('app.restaurant.tables.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Manage tables') }}</a>
        <button type="button" onclick="document.getElementById('takeaway-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New takeaway order') }}</button>
    </div>
</div>

@if (session('errors') || $errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ $errors->first() }}
    </div>
@endif

<h2 class="text-sm font-semibold uppercase text-slate-500 mb-3">{{ __('Tables') }}</h2>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    @forelse ($tables as $table)
        @php($openOrder = $table->openOrder())
        <div class="rounded-xl border p-4 {{ $table->status === 'available' ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
            <p class="font-semibold text-slate-900">{{ $table->name }}</p>
            <p class="text-xs text-slate-500 mb-3">{{ $table->area ?: __('No area') }} · {{ __(':count seats', ['count' => $table->seats]) }}</p>
            @if ($openOrder)
                <a href="{{ route('app.restaurant.orders.show', $openOrder) }}" class="block w-full rounded-lg bg-white px-3 py-2 text-center text-sm font-semibold text-slate-700 border border-slate-200 hover:border-brand-400">{{ __('Open order :number', ['number' => $openOrder->order_number]) }}</a>
            @else
                <form method="POST" action="{{ route('app.restaurant.orders.store') }}">
                    @csrf
                    <input type="hidden" name="order_type" value="dine_in">
                    <input type="hidden" name="table_id" value="{{ $table->id }}">
                    <button type="submit" class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Start order') }}</button>
                </form>
            @endif
        </div>
    @empty
        <p class="text-sm text-slate-500">{{ __('No tables set up yet.') }} <a href="{{ route('app.restaurant.tables.index') }}" class="text-brand-700 hover:underline">{{ __('Add one') }}</a>.</p>
    @endforelse
</div>

<h2 class="text-sm font-semibold uppercase text-slate-500 mb-3">{{ __('Open orders') }}</h2>
<div class="bg-white rounded-xl border border-slate-100">
    @if ($openOrders->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No open orders right now.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Order') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Type') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Table / Customer') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($openOrders as $order)
                    @php($statusBadge = ['open' => 'bg-slate-100 text-slate-600', 'kitchen' => 'bg-amber-50 text-amber-700', 'ready' => 'bg-emerald-50 text-emerald-700'][$order->status] ?? 'bg-slate-100 text-slate-600')
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $order->order_number }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $order->order_type === 'dine_in' ? __('Dine-in') : __('Takeaway') }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $order->table->name ?? $order->customer_name ?? '—' }}</td>
                        <td class="px-6 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadge }}">
                                @if ($order->status === 'open') {{ __('Open') }}
                                @elseif ($order->status === 'kitchen') {{ __('In kitchen') }}
                                @else {{ __('Ready') }}
                                @endif
                            </span>
                        </td>
                        <td class="px-6 py-3 text-end">
                            <a href="{{ route('app.restaurant.orders.show', $order) }}" class="text-brand-700 hover:underline">{{ __('Open') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<dialog id="takeaway-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.restaurant.orders.store') }}" class="p-6 space-y-4">
        @csrf
        <input type="hidden" name="order_type" value="takeaway">
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('New takeaway order') }}</h3>
            <button type="button" onclick="document.getElementById('takeaway-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Customer name') }}</label>
            <input type="text" name="customer_name" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Phone') }}</label>
            <input type="text" name="customer_phone" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('takeaway-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Start order') }}</button>
        </div>
    </form>
</dialog>
@endsection
