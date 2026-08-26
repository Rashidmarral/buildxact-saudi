@extends('layouts.portal')

@section('title', __('Invoices'))

@section('content')
<h1 class="text-xl font-bold text-slate-900 mb-6">{{ __('Invoices') }}</h1>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($invoices->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No invoices yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Invoice') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Issue date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Total') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoices as $invoice)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-900">{{ $invoice->invoice_number }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $invoice->issue_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">SAR {{ number_format($invoice->total, 2) }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $invoice->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : ($invoice->status === 'cancelled' ? 'bg-slate-100 text-slate-500' : 'bg-amber-50 text-amber-700') }}">
                                {{ ucfirst(str_replace('_', ' ', $invoice->status)) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('public.invoices.show', [$invoice->id, $invoice->public_token]) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('View & Pay') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $invoices->links() }}</div>
@endsection
