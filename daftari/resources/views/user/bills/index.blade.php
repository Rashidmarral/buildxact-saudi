@extends('layouts.app')

@section('title', __('Bills'))

@section('content')
<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    <a href="{{ route('app.bills.index') }}" class="rounded-lg px-3 py-1.5 bg-slate-900 text-white">{{ __('Bills') }}</a>
    <a href="{{ route('app.purchase-orders.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Purchase orders') }}</a>
    <a href="{{ route('app.suppliers.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Suppliers') }}</a>
</div>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
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

@if (! $bills->isEmpty())
    <div x-data="bulkSelect()">
        @include('user.partials.bulk-actions-toolbar', [
            'formId' => 'bulk-bills',
            'exportRoute' => route('app.bills.bulk-export'),
            'destroyRoute' => route('app.bills.bulk-void'),
            'destroyLabel' => __('Void selected'),
            'destroyConfirm' => __('Void the selected bills? Only posted bills will be voided.'),
        ])

        <x-list-toolbar>
        <div class="bg-white rounded-xl border border-slate-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="w-10 px-6 py-3"><input type="checkbox" :checked="allChecked" @change="toggleAll($event.target.checked)"></th>
                        <th class="px-6 py-3 font-medium">{{ __('Number') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Supplier') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Total') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Balance') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody x-ref="rows">
                    @foreach ($bills as $bill)
                        <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" data-row-id="{{ $bill->id }}" onclick="window.location='{{ route('app.bills.show', $bill) }}'">
                            <td class="px-6 py-3" onclick="event.stopPropagation()"><input type="checkbox" :checked="selected.includes('{{ $bill->id }}')" @change="toggleOne('{{ $bill->id }}', $event.target.checked)"></td>
                            <td class="px-6 py-3 font-medium text-brand-700">{{ $bill->bill_number }}</td>
                            <td class="px-6 py-3">{{ $bill->supplier->display_name }}</td>
                            <td class="px-6 py-3">{{ $bill->bill_date->format('Y-m-d') }}</td>
                            <td class="px-6 py-3">{{ \App\Support\Money::format($bill->total) }}</td>
                            <td class="px-6 py-3">{{ \App\Support\Money::format(max($bill->balanceDue(), 0)) }}</td>
                            <td class="px-6 py-3">@include('user.bills.partials.status-badge', ['status' => $bill->status])</td>
                            <td class="px-6 py-3" onclick="event.stopPropagation()">
                                <x-row-actions>
                                    <x-icon-button menu href="{{ route('app.bills.show', $bill) }}" icon="eye" title="{{ __('View') }}" />
                                    @if ($bill->status === 'draft')
                                        <form method="POST" action="{{ route('app.bills.post', $bill) }}">
                                            @csrf
                                            <x-icon-button menu type="submit" icon="check-circle" color="brand" title="{{ __('Post to ledger') }}" />
                                        </form>
                                    @elseif ($bill->status === 'pending_approval')
                                        <form method="POST" action="{{ route('app.bills.approve', $bill) }}">
                                            @csrf
                                            <x-icon-button menu type="submit" icon="check-circle" color="brand" title="{{ __('Approve') }}" />
                                        </form>
                                        <form method="POST" action="{{ route('app.bills.reject', $bill) }}" onsubmit="return confirm('{{ __('Reject this bill? It will return to draft.') }}')">
                                            @csrf
                                            <x-icon-button menu type="submit" icon="x-circle" color="amber" title="{{ __('Reject') }}" />
                                        </form>
                                    @endif
                                    <x-icon-button menu href="{{ route('app.bills.pdf', $bill) }}" icon="printer" title="{{ __('Download PDF') }}" />
                                    <x-icon-button menu href="{{ route('app.payment-vouchers.create') }}" icon="document" title="{{ __('Record payment') }}" />
                                    @if (! in_array($bill->status, ['void', 'pending_approval'], true))
                                        <form method="POST" action="{{ route('app.bills.void', $bill) }}" onsubmit="return confirm('{{ __('Void this bill?') }}')">
                                            @csrf
                                            <x-icon-button menu type="submit" icon="trash" color="red" title="{{ __('Void') }}" />
                                        </form>
                                    @endif
                                </x-row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        </x-list-toolbar>
    </div>
@else
    <div class="bg-white rounded-xl border border-slate-100">
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No bills yet.') }}</p>
    </div>
@endif
@include('partials.pagination', ['paginator' => $bills])
@endsection
