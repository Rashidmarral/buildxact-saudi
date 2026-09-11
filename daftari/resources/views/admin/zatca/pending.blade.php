@extends('layouts.admin')

@section('title', __('Pending ZATCA sync'))

@section('content')
@php $pendingTotal = $pendingInvoices->count() + $pendingCreditNotes->count() + $pendingDebitNotes->count(); @endphp

<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Pending ZATCA sync') }} — {{ $company->name }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Every document waiting to be cleared or reported to ZATCA for this company — its own invoices and, if this is your subscription-billing company, subscription invoices too, listed together exactly as ZATCA sees them.') }}</p>
    </div>
    <a href="{{ route('admin.zatca.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back to ZATCA') }}</a>
</div>

@if (! $company->isZatcaOnboarded())
    <div class="bg-white rounded-xl border border-slate-100 p-8 text-center">
        <p class="text-sm text-slate-500">{{ __('This company must complete ZATCA onboarding before its invoices can be synced.') }}</p>
    </div>
@else
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-center justify-between mb-1">
            <h3 class="font-semibold text-slate-900">{{ __('Pending sync') }} ({{ $pendingTotal }})</h3>
            @if ($pendingTotal > 0)
                <form method="POST" action="{{ route('admin.zatca.companies.sync', $company) }}" onsubmit="return confirm('{{ __('Sync all :count pending document(s) for :name now?', ['count' => $pendingTotal, 'name' => $company->name]) }}')">
                    @csrf
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Sync all pending') }}</button>
                </form>
            @endif
        </div>
        <p class="text-xs text-slate-500 mb-4">{{ __('Sync everything at once, or pick a specific document below to sync it on its own.') }}</p>

        @if ($pendingTotal === 0)
            <p class="py-6 text-center text-sm text-slate-400">{{ __('Nothing pending — every eligible document has been synced.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2">{{ __('Document') }}</th>
                            <th class="py-2">{{ __('Kind') }}</th>
                            <th class="py-2">{{ __('Type') }}</th>
                            <th class="py-2">{{ __('Client') }}</th>
                            <th class="py-2 text-end">{{ __('Total') }}</th>
                            <th class="py-2 text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingInvoices as $doc)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="py-2 font-medium text-slate-800">{{ $doc->invoice_number }}</td>
                                <td class="py-2 text-slate-500">{{ __('Invoice') }}</td>
                                <td class="py-2 text-slate-500">{{ $doc->type === 'standard' ? __('B2B') : __('B2C') }}</td>
                                <td class="py-2 text-slate-500">{{ $doc->client?->name }}</td>
                                <td class="py-2 text-end">{{ \App\Support\Money::format($doc->total) }}</td>
                                <td class="py-2 text-end">
                                    <form method="POST" action="{{ route('admin.zatca.invoices.sync', $doc) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-brand-300 hover:text-brand-600">{{ __('Sync') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        @foreach ($pendingCreditNotes as $doc)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="py-2 font-medium text-slate-800">{{ $doc->credit_note_number }}</td>
                                <td class="py-2 text-slate-500">{{ __('Credit note') }}</td>
                                <td class="py-2 text-slate-500">{{ $doc->invoice->type === 'standard' ? __('B2B') : __('B2C') }}</td>
                                <td class="py-2 text-slate-500">{{ $doc->client?->name }}</td>
                                <td class="py-2 text-end">{{ \App\Support\Money::format($doc->total) }}</td>
                                <td class="py-2 text-end">
                                    <form method="POST" action="{{ route('admin.zatca.credit-notes.sync', $doc) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-brand-300 hover:text-brand-600">{{ __('Sync') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        @foreach ($pendingDebitNotes as $doc)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="py-2 font-medium text-slate-800">{{ $doc->debit_note_number }}</td>
                                <td class="py-2 text-slate-500">{{ __('Debit note') }}</td>
                                <td class="py-2 text-slate-500">{{ $doc->invoice->type === 'standard' ? __('B2B') : __('B2C') }}</td>
                                <td class="py-2 text-slate-500">{{ $doc->client?->name }}</td>
                                <td class="py-2 text-end">{{ \App\Support\Money::format($doc->total) }}</td>
                                <td class="py-2 text-end">
                                    <form method="POST" action="{{ route('admin.zatca.debit-notes.sync', $doc) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-brand-300 hover:text-brand-600">{{ __('Sync') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
@endsection
