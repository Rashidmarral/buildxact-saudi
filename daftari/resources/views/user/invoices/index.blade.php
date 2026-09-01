@extends('layouts.app')

@section('title', __('Invoices'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <form method="GET" class="flex items-center gap-2">
        <select name="status" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (['draft' => __('Draft'), 'sent' => __('Sent'), 'paid' => __('Paid'), 'partially_paid' => __('Partially paid'), 'overdue' => __('Overdue'), 'cancelled' => __('Cancelled')] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
    <div class="flex items-center gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Export CSV') }}</a>
        <a href="{{ route('app.invoices.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New invoice') }}</a>
    </div>
</div>

@if (! $invoices->isEmpty())
    <div x-data="bulkSelect()">
        @include('user.partials.bulk-actions-toolbar', [
            'formId' => 'bulk-invoices',
            'exportRoute' => route('app.invoices.bulk-export'),
            'destroyRoute' => route('app.invoices.bulk-destroy'),
            'destroyLabel' => __('Delete selected'),
            'destroyConfirm' => __('Delete the selected invoices? Only draft, non-ZATCA-locked invoices will be removed.'),
        ])

        <div class="bg-white rounded-xl border border-slate-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="w-10 px-6 py-3"><input type="checkbox" :checked="allChecked" @change="toggleAll($event.target.checked)"></th>
                        <th class="px-6 py-3 font-medium">{{ __('Invoice') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Client') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Total') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Balance due') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody x-ref="rows">
                    @foreach ($invoices as $invoice)
                        <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" data-row-id="{{ $invoice->id }}" onclick="window.location='{{ route('app.invoices.show', $invoice) }}'">
                            <td class="px-6 py-3" onclick="event.stopPropagation()"><input type="checkbox" :checked="selected.includes('{{ $invoice->id }}')" @change="toggleOne('{{ $invoice->id }}', $event.target.checked)"></td>
                            <td class="px-6 py-3 font-medium text-brand-700">{{ $invoice->invoice_number }}</td>
                            <td class="px-6 py-3">{{ $invoice->client->name }}</td>
                            <td class="px-6 py-3">{{ $invoice->issue_date->format('Y-m-d') }}</td>
                            <td class="px-6 py-3">{{ \App\Support\Money::format($invoice->total) }}</td>
                            <td class="px-6 py-3">{{ \App\Support\Money::format($invoice->balanceDue()) }}</td>
                            <td class="px-6 py-3">@include('user.invoices.partials.status-badge', ['status' => $invoice->status])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="bg-white rounded-xl border border-slate-100">
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No invoices yet.') }}</p>
    </div>
@endif

@include('partials.pagination', ['paginator' => $invoices])
@endsection
