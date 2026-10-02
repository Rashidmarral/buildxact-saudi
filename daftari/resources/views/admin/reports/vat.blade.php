@extends('layouts.admin')

@section('title', __('VAT Report'))

@section('content')
<div class="mb-6">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('VAT Report') }}</h2>
    <p class="text-sm text-slate-500 mt-1">{{ __('Output VAT for any company on the platform — including one that resells Daftari subscriptions alongside its own real business under a single CR.') }}</p>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-end gap-3">
    @foreach (request()->except(['company_id', 'page']) as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Company') }}</label>
        <select name="company_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm min-w-[16rem]">
            <option value="">{{ __('Select a company...') }}</option>
            @foreach ($companies as $c)
                <option value="{{ $c->id }}" @selected($company && $company->id === $c->id)>{{ $c->name }}{{ $c->vat_number ? ' — '.$c->vat_number : '' }}</option>
            @endforeach
        </select>
    </div>
</form>

@if (! $company)
    <div class="bg-white rounded-xl border border-slate-100 p-8 text-center">
        <p class="text-sm text-slate-500">{{ __('Select a company above to view its VAT report.') }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ __('Defaults to the platform\'s designated subscription-billing company (Admin → Platform Settings → Identity) when one is configured.') }}</p>
    </div>
@else
    @include('user.reports.partials.period-selector', ['extra' => ['company_id' => $company->id]])

    <div class="bg-brand-600 text-white rounded-xl p-6 mb-6">
        <p class="text-xs font-semibold uppercase text-brand-100">{{ __('Combined Output VAT — the figure to file with ZATCA') }}</p>
        <p class="mt-2 text-2xl font-bold">{{ \App\Support\Money::format($combinedOutputVat) }}</p>
        <p class="mt-1 text-sm text-brand-100">{{ __('Net sales excl. VAT') }}: {{ \App\Support\Money::format($combinedNetSales) }}</p>
        <p class="mt-3 text-xs text-brand-100">{{ __('Both revenue streams below post real output VAT to this company\'s single VAT registration. Always file the combined figure above — never either stream alone.') }}</p>
    </div>

    <div class="grid sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Own business invoices') }}</p>
            <p class="mt-2 text-xl font-bold text-slate-900">{{ \App\Support\Money::format($ownOutputVat) }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('Net sales excl. VAT') }}: {{ \App\Support\Money::format($ownNetSales) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Subscription resale invoices') }}</p>
            <p class="mt-2 text-xl font-bold text-slate-900">{{ \App\Support\Money::format($subscriptionOutputVat) }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ __('Net sales excl. VAT') }}: {{ \App\Support\Money::format($subscriptionNetSales) }}</p>
        </div>
    </div>
    <p class="text-xs text-slate-400 -mt-3 mb-6">{{ __('This split is for internal bookkeeping only (e.g. reconciling subscription revenue) — it has no bearing on the VAT return.') }}</p>

    <div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
        @if ($invoices->isEmpty())
            <p class="px-6 py-8 text-sm text-slate-500">{{ __('No invoices in this period.') }}</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100 whitespace-nowrap">
                        <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Invoice #') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Client') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Type') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Net') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('VAT') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        @php $isSubscription = $invoice->client?->platform_billed_company_id !== null; @endphp
                        <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                            <td class="px-6 py-3">{{ \App\Support\PlatformFormat::date($invoice->issue_date) }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $invoice->invoice_number }}</td>
                            <td class="px-4 py-3">{{ $invoice->client?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($isSubscription)
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ __('Subscription resale') }}</span>
                                @else
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Own business') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ \App\Support\Money::format($invoice->subtotal) }}</td>
                            <td class="px-4 py-3">{{ \App\Support\Money::format($invoice->vat_total) }}</td>
                            <td class="px-6 py-3 font-semibold">{{ \App\Support\Money::format($invoice->total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
@endif
@endsection
