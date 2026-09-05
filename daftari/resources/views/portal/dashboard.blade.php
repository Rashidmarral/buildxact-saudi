@extends('layouts.portal')

@section('title', __('Dashboard'))

@section('content')
<h1 class="text-xl font-bold text-slate-900 mb-1">{{ __('Welcome, :name', ['name' => $client->name]) }}</h1>
<p class="text-sm text-slate-500 mb-6">{{ $client->company->name }}</p>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-8">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-medium text-slate-500 mb-1">{{ __('Outstanding balance') }}</p>
        <p class="text-2xl font-bold text-slate-900">{{ \App\Support\Money::format($outstanding) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-medium text-slate-500 mb-1">{{ __('Total invoiced') }}</p>
        <p class="text-2xl font-bold text-slate-900">{{ \App\Support\Money::format($totalInvoiced) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-medium text-slate-500 mb-1">{{ __('Open invoices') }}</p>
        <p class="text-2xl font-bold text-slate-900">{{ $openCount }}</p>
    </div>
</div>

<div class="flex items-center justify-between mb-3">
    <h2 class="font-semibold text-slate-900">{{ __('Recent invoices') }}</h2>
    <a href="{{ route('portal.invoices') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('View all') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($recentInvoices->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No invoices yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <tbody>
                @foreach ($recentInvoices as $invoice)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-900">{{ $invoice->invoice_number }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $invoice->issue_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ \App\Support\Money::format($invoice->total) }}</td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('public.invoices.show', [$invoice->id, $invoice->public_token]) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('View & Pay') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
