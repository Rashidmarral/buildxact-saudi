@extends('layouts.app')

@section('title', __('Purchase Requisitions'))

@section('content')
<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    <a href="{{ route('app.purchase-requisitions.index') }}" class="rounded-lg px-3 py-1.5 bg-slate-900 text-white">{{ __('Purchase requisitions') }}</a>
    <a href="{{ route('app.purchase-orders.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Purchase orders') }}</a>
</div>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <p class="text-sm text-slate-500">{{ __('What\'s needed on site, flagged before a supplier or price is chosen — approve one to turn it into a purchase order.') }}</p>
    <a href="{{ route('app.purchase-requisitions.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New requisition') }}</a>
</div>

<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    @foreach ([
        '' => __('All') . " ({$counts['all']})",
        'requested' => __('Requested') . " ({$counts['requested']})",
        'approved' => __('Approved') . " ({$counts['approved']})",
        'rejected' => __('Rejected') . " ({$counts['rejected']})",
        'converted' => __('Converted to PO') . " ({$counts['converted']})",
    ] as $value => $label)
        <a href="{{ route('app.purchase-requisitions.index', array_filter(['status' => $value ?: null])) }}"
           class="rounded-full border px-3 py-1 {{ request('status', '') === $value ? 'border-brand-500 text-brand-700 bg-brand-50' : 'border-slate-200 text-slate-500 hover:border-slate-300' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<x-list-toolbar>
<div class="bg-white rounded-xl border border-slate-100">
    @if ($requisitions->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No purchase requisitions yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Number') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Requested by') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Project') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Needed by') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Items') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($requisitions as $requisition)
                    @php($badge = ['requested' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-red-50 text-red-700', 'converted' => 'bg-slate-100 text-slate-600'][$requisition->status])
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-900">{{ $requisition->requisition_number }}</td>
                        <td class="px-6 py-3">{{ $requisition->requestedBy?->name ?? '—' }}</td>
                        <td class="px-6 py-3">{{ $requisition->project?->name ?? '—' }}</td>
                        <td class="px-6 py-3">{{ optional($requisition->needed_by_date)->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $requisition->items->pluck('description')->take(2)->implode(', ') }}{{ $requisition->items->count() > 2 ? ' +'.($requisition->items->count() - 2) : '' }}</td>
                        <td class="px-6 py-3"><span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium {{ $badge }}">{{ __(ucfirst($requisition->status)) }}</span></td>
                        <td class="px-6 py-3 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                @if ($requisition->status === 'requested')
                                    <form method="POST" action="{{ route('app.purchase-requisitions.approve', $requisition) }}">
                                        @csrf
                                        <x-icon-button type="submit" icon="check-circle" color="emerald" title="{{ __('Approve') }}" />
                                    </form>
                                    <x-icon-button type="button" icon="x-circle" color="red" title="{{ __('Reject') }}" onclick="document.getElementById('reject-{{ $requisition->id }}').showModal()" />
                                    <x-icon-button href="{{ route('app.purchase-requisitions.edit', $requisition) }}" icon="pencil" title="{{ __('Edit') }}" />
                                    <dialog id="reject-{{ $requisition->id }}" class="rounded-xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
                                        <form method="POST" action="{{ route('app.purchase-requisitions.reject', $requisition) }}" class="p-6 space-y-3">
                                            @csrf
                                            <h3 class="font-semibold text-slate-900">{{ __('Reject requisition :number', ['number' => $requisition->requisition_number]) }}</h3>
                                            <textarea name="rejection_reason" required rows="3" placeholder="{{ __('Reason') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" onclick="this.closest('dialog').close()" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600">{{ __('Cancel') }}</button>
                                                <button type="submit" class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white">{{ __('Reject') }}</button>
                                            </div>
                                        </form>
                                    </dialog>
                                @elseif ($requisition->status === 'approved')
                                    <x-icon-button href="{{ route('app.purchase-requisitions.create-po', $requisition) }}" icon="arrow-right" color="brand" title="{{ __('Create purchase order') }}" />
                                @elseif ($requisition->status === 'converted' && $requisition->purchase_order_id)
                                    <x-icon-button href="{{ route('app.purchase-orders.show', $requisition->purchase_order_id) }}" icon="eye" title="{{ __('View purchase order') }}" />
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
</x-list-toolbar>
<div class="mt-4">@include('partials.pagination', ['paginator' => $requisitions])</div>
@endsection
