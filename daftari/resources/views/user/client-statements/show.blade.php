@extends('layouts.app')

@section('title', $client->display_name.' — '.__('Statement of Account'))

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <a href="{{ route('app.client-statements.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('← Client Statements') }}</a>
        <h2 class="text-lg font-semibold text-slate-900 mt-1">{{ $client->display_name }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Statement of Account') }} — {{ $client->client_code }}</p>
    </div>
    <a href="{{ route('app.client-statements.pdf', array_merge(['client' => $client], request()->only('period', 'from', 'to', 'project_id', 'po_reference', 'requisition_reference'))) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Download PDF') }}</a>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Period') }}</label>
        <select name="period" onchange="this.form.submit()" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="this_month" @selected($period['preset'] === 'this_month')>{{ __('This month') }}</option>
            <option value="last_month" @selected($period['preset'] === 'last_month')>{{ __('Last month') }}</option>
            <option value="this_quarter" @selected($period['preset'] === 'this_quarter')>{{ __('This quarter') }}</option>
            <option value="this_year" @selected($period['preset'] === 'this_year')>{{ __('Current year') }}</option>
            <option value="last_year" @selected($period['preset'] === 'last_year')>{{ __('Last year') }}</option>
            <option value="custom" @selected($period['preset'] === 'custom')>{{ __('Custom') }}</option>
        </select>
    </div>
    @if ($period['preset'] === 'custom')
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('From') }}</label>
            <input type="date" name="from" value="{{ $period['from']->toDateString() }}" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('To') }}</label>
            <input type="date" name="to" value="{{ $period['to']->toDateString() }}" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
    @endif
    @if ($projects->isNotEmpty())
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Project (optional)') }}</label>
            <select name="project_id" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('All projects') }}</option>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}" @selected($project && $project->id === $p->id)>{{ $p->code }} - {{ $p->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Purchase order (optional)') }}</label>
        <input type="text" name="po_reference" value="{{ $poReference }}" placeholder="{{ __('e.g. PO 25-6121') }}" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    </div>
    <div>
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Requisition (optional)') }}</label>
        <input type="text" name="requisition_reference" value="{{ $requisitionReference }}" placeholder="{{ __('e.g. PR 25-8244') }}" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    </div>
    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Apply') }}</button>
</form>

<div class="grid sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Total invoiced') }}</p>
        <p class="text-xl font-bold text-slate-900 mt-1">{{ \App\Support\Money::format($totalInvoiced) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Total received') }}</p>
        <p class="text-xl font-bold text-emerald-600 mt-1">{{ \App\Support\Money::format($totalReceived) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Balance due') }}</p>
        <p class="text-xl font-bold {{ $closingBalance > 0 ? 'text-amber-600' : 'text-slate-900' }} mt-1">{{ \App\Support\Money::format($closingBalance) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 mb-6">
    <h3 class="font-semibold text-slate-900 px-6 pt-6 pb-2">{{ __('Invoice details') }}</h3>
    @if ($invoiceRows->isEmpty())
        <p class="px-6 py-6 text-sm text-slate-500">{{ __('No invoices in this period.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Invoice no.') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Description') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3 font-medium text-end">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoiceRows as $row)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-700">{{ $row['number'] }}</td>
                        <td class="px-6 py-3">{{ $row['date']->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $row['description'] ?: '—' }}</td>
                        <td class="px-6 py-3">
                            @php
                                $statusColors = ['Paid' => 'bg-emerald-50 text-emerald-700', 'Partially paid' => 'bg-amber-50 text-amber-700', 'Overdue' => 'bg-red-50 text-red-600', 'Unpaid' => 'bg-slate-100 text-slate-600'];
                            @endphp
                            <span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium {{ $statusColors[$row['status_label']] ?? 'bg-slate-100 text-slate-600' }}">{{ $row['status_label'] }}</span>
                        </td>
                        <td class="px-6 py-3 text-end font-medium">{{ \App\Support\Money::format($row['total']) }}</td>
                    </tr>
                @endforeach
                <tr class="font-semibold bg-slate-50">
                    <td class="px-6 py-3" colspan="4">{{ __('Total invoiced') }}</td>
                    <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($totalInvoiced) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
</div>

<div class="bg-white rounded-xl border border-slate-100 mb-6">
    <h3 class="font-semibold text-slate-900 px-6 pt-6 pb-2">{{ __('Payments received') }}</h3>
    @if ($paymentRows->isEmpty())
        <p class="px-6 py-6 text-sm text-slate-500">{{ __('No payments received in this period.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Reference') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Invoice') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Method') }}</th>
                    <th class="px-6 py-3 font-medium text-end">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($paymentRows as $row)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3">{{ $row['reference'] ?: '—' }}</td>
                        <td class="px-6 py-3">{{ $row['date']->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 font-medium text-slate-700">{{ $row['invoice_number'] }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $row['method'] ?: '—' }}</td>
                        <td class="px-6 py-3 text-end font-medium">{{ \App\Support\Money::format($row['amount']) }}</td>
                    </tr>
                @endforeach
                <tr class="font-semibold bg-slate-50">
                    <td class="px-6 py-3" colspan="4">{{ __('Total received') }}</td>
                    <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($totalReceived) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
</div>

<div class="bg-white rounded-xl border border-slate-100">
    <h3 class="font-semibold text-slate-900 px-6 pt-6 pb-2">{{ __('Account ledger — running balance') }}</h3>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Reference') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Description') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Invoiced') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Paid') }}</th>
                <th class="px-6 py-3 font-medium text-end">{{ __('Balance') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-slate-50 text-slate-400 italic">
                <td class="px-6 py-3" colspan="5">{{ __('Opening balance') }}</td>
                <td class="px-6 py-3 text-end">{{ \App\Support\Money::format($openingBalance) }}</td>
            </tr>
            @foreach ($ledgerRows as $row)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-6 py-3">{{ $row['date']->format('Y-m-d') }}</td>
                    <td class="px-6 py-3 font-medium text-slate-700">{{ $row['reference'] }}</td>
                    <td class="px-6 py-3 text-slate-500">{{ $row['description'] }}</td>
                    <td class="px-6 py-3 text-end">{{ $row['invoiced'] > 0 ? \App\Support\Money::format($row['invoiced']) : '—' }}</td>
                    <td class="px-6 py-3 text-end text-emerald-600">{{ $row['paid'] > 0 ? \App\Support\Money::format($row['paid']) : '—' }}</td>
                    <td class="px-6 py-3 text-end font-semibold">{{ \App\Support\Money::format($row['balance']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="flex items-center justify-between px-6 py-4 bg-amber-50 rounded-b-xl">
        <p class="text-sm font-semibold text-amber-900">{{ __('Closing balance due as of :date', ['date' => now()->format('d M Y')]) }}</p>
        <p class="text-xl font-bold text-amber-900">{{ \App\Support\Money::format($closingBalance) }}</p>
    </div>
</div>
@endsection
