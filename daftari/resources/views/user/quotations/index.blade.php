@extends('layouts.app')

@section('title', __('Quotations & Proforma Invoices'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Create, track, and convert quotations to invoices.') }}</p>
    <div class="flex gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Export CSV') }}</a>
        <a href="{{ route('app.quotations.create', ['type' => 'quotation']) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New quotation') }}</a>
        <a href="{{ route('app.quotations.create', ['type' => 'proforma']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('+ New proforma invoice') }}</a>
    </div>
</div>

<div class="flex items-center gap-2 mb-4 text-sm">
    <a href="{{ route('app.quotations.index') }}" class="rounded-lg px-3 py-1.5 {{ ! request('type') ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('All') }}</a>
    <a href="{{ route('app.quotations.index', ['type' => 'quotation']) }}" class="rounded-lg px-3 py-1.5 {{ request('type') === 'quotation' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Quotation') }}</a>
    <a href="{{ route('app.quotations.index', ['type' => 'proforma']) }}" class="rounded-lg px-3 py-1.5 {{ request('type') === 'proforma' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Proforma Invoice') }}</a>
</div>

<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    @foreach ([
        '' => __('All') . " ({$counts['all']})",
        'draft' => __('Draft') . " ({$counts['draft']})",
        'issued' => __('Issued') . " ({$counts['issued']})",
        'accepted' => __('Accepted') . " ({$counts['accepted']})",
        'converted' => __('Converted') . " ({$counts['converted']})",
        'expired' => __('Expired') . " ({$counts['expired']})",
        'rejected' => __('Rejected') . " ({$counts['rejected']})",
    ] as $value => $label)
        <a href="{{ route('app.quotations.index', array_filter(['type' => request('type'), 'status' => $value ?: null])) }}"
           class="rounded-full border px-3 py-1 {{ request('status', '') === $value ? 'border-brand-500 text-brand-700 bg-brand-50' : 'border border-slate-200 text-slate-500 hover:border-slate-300' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

@if ($quotations->isEmpty())
    <div class="bg-white rounded-xl border border-slate-100">
        <div class="px-6 py-16 text-center">
            <p class="text-sm text-slate-500 mb-4">{{ __('No quotations yet. Create your first quotation and share it with your customer.') }}</p>
            <a href="{{ route('app.quotations.create') }}" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New quotation') }}</a>
        </div>
    </div>
@else
    <div x-data="bulkSelect()">
        @include('user.partials.bulk-actions-toolbar', [
            'formId' => 'bulk-quotations',
            'exportRoute' => route('app.quotations.bulk-export'),
            'destroyRoute' => route('app.quotations.bulk-destroy'),
            'destroyLabel' => __('Delete selected'),
            'destroyConfirm' => __('Delete the selected quotations? Converted quotations will be skipped.'),
        ])

        <div class="bg-white rounded-xl border border-slate-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="w-10 px-6 py-3"><input type="checkbox" :checked="allChecked" @change="toggleAll($event.target.checked)"></th>
                        <th class="px-6 py-3 font-medium">{{ __('Number') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Client') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Type') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Issue date') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Total') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody x-ref="rows">
                    @foreach ($quotations as $quotation)
                        <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" data-row-id="{{ $quotation->id }}" onclick="window.location='{{ route('app.quotations.show', $quotation) }}'">
                            <td class="px-6 py-3" onclick="event.stopPropagation()"><input type="checkbox" :checked="selected.includes('{{ $quotation->id }}')" @change="toggleOne('{{ $quotation->id }}', $event.target.checked)"></td>
                            <td class="px-6 py-3 font-medium text-brand-700">{{ $quotation->quotation_number }}</td>
                            <td class="px-6 py-3">{{ $quotation->client->name }}</td>
                            <td class="px-6 py-3">{{ $quotation->type === 'proforma' ? __('Proforma Invoice') : __('Quotation') }}</td>
                            <td class="px-6 py-3">{{ $quotation->issue_date->format('Y-m-d') }}</td>
                            <td class="px-6 py-3">{{ \App\Support\Money::format($quotation->total) }}</td>
                            <td class="px-6 py-3">@include('user.quotations.partials.status-badge', ['status' => $quotation->status])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@include('partials.pagination', ['paginator' => $quotations])
@endsection
