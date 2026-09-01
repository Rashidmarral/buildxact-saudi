@extends('layouts.app')

@section('title', __('Tax Report'))

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Tax Report') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Output tax, input tax, and expense tax for the selected period — verify against your ZATCA VAT return before filing.') }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('app.reports.vat', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('CSV') }}</a>
        <a href="{{ route('app.reports.vat', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('PDF') }}</a>
        <button type="button" onclick="window.print()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300 print:hidden">{{ __('Print') }}</button>
    </div>
</div>

@include('user.reports.partials.period-selector', ['extra' => array_filter([
    'tab' => $tab,
    'warehouse_id' => request('warehouse_id'),
    'client_id' => request('client_id'),
    'supplier_id' => request('supplier_id'),
])])

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 grid sm:grid-cols-4 gap-3">
    <input type="hidden" name="period" value="{{ $period['preset'] }}">
    <input type="hidden" name="from" value="{{ $period['from']->toDateString() }}">
    <input type="hidden" name="to" value="{{ $period['to']->toDateString() }}">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div>
        <label class="block text-xs font-medium text-slate-500">{{ __('Warehouse') }}</label>
        <select name="warehouse_id" onchange="this.form.submit()" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">{{ __('All warehouses') }}</option>
            @foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(request('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-500">{{ __('Customer') }}</label>
        <select name="client_id" onchange="this.form.submit()" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">{{ __('All customers') }}</option>
            @foreach ($clients as $client)<option value="{{ $client->id }}" @selected(request('client_id') == $client->id)>{{ $client->name }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-slate-500">{{ __('Supplier') }}</label>
        <select name="supplier_id" onchange="this.form.submit()" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">{{ __('All suppliers') }}</option>
            @foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>@endforeach
        </select>
    </div>
</form>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Output Tax (Sales)') }}</p>
        <p class="mt-2 text-xl font-bold text-slate-900">{{ \App\Support\Money::format($outputTax) }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ __('Net Sales Excl. Tax') }}: {{ \App\Support\Money::format($netSalesExclTax) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Input Tax (Purchases)') }}</p>
        <p class="mt-2 text-xl font-bold text-slate-900">{{ \App\Support\Money::format($inputTaxPurchases) }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ __('Net Purchases Excl. Tax') }}: {{ \App\Support\Money::format($netPurchasesExclTax) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs font-semibold uppercase text-slate-400">{{ __('Expense Tax') }}</p>
        <p class="mt-2 text-xl font-bold text-slate-900">{{ \App\Support\Money::format($expenseTax) }}</p>
        <p class="mt-1 text-xs text-slate-400">{{ __('Total Expenses') }}: {{ \App\Support\Money::format($totalExpenses) }}</p>
    </div>
    <div class="bg-brand-600 text-white rounded-xl p-5">
        <p class="text-xs font-semibold uppercase text-brand-100">{{ __('Net Tax Position') }}</p>
        <p class="mt-2 text-xl font-bold">{{ \App\Support\Money::format($netTaxPosition) }}</p>
        <p class="mt-1 text-xs text-brand-100">{{ __('Output Tax - Input Tax') }}</p>
    </div>
</div>

<div class="border-b border-slate-200 mb-4 flex gap-6 print:hidden">
    @php($tabs = ['sales' => __('Output Tax (Sales)'), 'purchases' => __('Input Tax (Purchases)'), 'expenses' => __('Expense Tax')])
    @foreach ($tabs as $key => $label)
        <a href="{{ route('app.reports.vat', array_merge(request()->except('page'), ['tab' => $key])) }}" class="pb-3 text-sm font-semibold border-b-2 {{ $tab === $key ? 'border-brand-600 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-700' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    @if ($tab === 'sales')
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="py-2 px-4">{{ __('Date') }}</th>
                    <th class="py-2 px-4">{{ __('Reference') }}</th>
                    <th class="py-2 px-4">{{ __('Customer') }}</th>
                    <th class="py-2 px-4">{{ __('Tax Number') }}</th>
                    <th class="py-2 px-4">{{ __('Warehouse') }}</th>
                    <th class="py-2 px-4 text-end">{{ __('Net Amount Excl. Tax') }}</th>
                    <th class="py-2 px-4 text-end">{{ __('Discount') }}</th>
                    <th class="py-2 px-4 text-end">{{ __('Tax Amount') }}</th>
                    <th class="py-2 px-4 text-end">{{ __('Total Amount') }}</th>
                    <th class="py-2 px-4">{{ __('Payment Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($salesRows as $row)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="py-2 px-4 text-slate-500">{{ $row->issue_date?->format('Y-m-d') }}</td>
                        <td class="py-2 px-4"><a href="{{ route('app.invoices.show', $row) }}" class="text-brand-600 hover:underline">{{ $row->invoice_number }}</a></td>
                        <td class="py-2 px-4">{{ $row->client->name ?? '—' }}</td>
                        <td class="py-2 px-4 text-slate-500">{{ $row->client->vat_number ?? '—' }}</td>
                        <td class="py-2 px-4 text-slate-500">{{ $row->warehouse->name ?? '—' }}</td>
                        <td class="py-2 px-4 text-end">{{ \App\Support\Money::format($row->subtotal) }}</td>
                        <td class="py-2 px-4 text-end">{{ \App\Support\Money::format($row->discount_total) }}</td>
                        <td class="py-2 px-4 text-end">{{ \App\Support\Money::format($row->vat_total) }}</td>
                        <td class="py-2 px-4 text-end font-semibold">{{ \App\Support\Money::format($row->total) }}</td>
                        <td class="py-2 px-4">
                            @php($paid = $row->isFullyPaid())
                            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $paid ? 'bg-emerald-50 text-emerald-700' : ((float) $row->amount_paid > 0 ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                                {{ $paid ? __('Paid') : ((float) $row->amount_paid > 0 ? __('Partial') : __('Unpaid')) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="py-8 text-center text-slate-400">{{ __('No sales in this period.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    @elseif ($tab === 'purchases')
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="py-2 px-4">{{ __('Date') }}</th>
                    <th class="py-2 px-4">{{ __('Reference') }}</th>
                    <th class="py-2 px-4">{{ __('Supplier') }}</th>
                    <th class="py-2 px-4">{{ __('Tax Number') }}</th>
                    <th class="py-2 px-4 text-end">{{ __('Net Amount Excl. Tax') }}</th>
                    <th class="py-2 px-4 text-end">{{ __('Discount') }}</th>
                    <th class="py-2 px-4 text-end">{{ __('Tax Amount') }}</th>
                    <th class="py-2 px-4 text-end">{{ __('Total Amount') }}</th>
                    <th class="py-2 px-4">{{ __('Payment Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($purchaseRows as $row)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="py-2 px-4 text-slate-500">{{ $row->bill_date?->format('Y-m-d') }}</td>
                        <td class="py-2 px-4"><a href="{{ route('app.bills.show', $row) }}" class="text-brand-600 hover:underline">{{ $row->bill_number }}</a></td>
                        <td class="py-2 px-4">{{ $row->supplier->name ?? '—' }}</td>
                        <td class="py-2 px-4 text-slate-500">{{ $row->supplier->vat_number ?? '—' }}</td>
                        <td class="py-2 px-4 text-end">{{ \App\Support\Money::format($row->subtotal) }}</td>
                        <td class="py-2 px-4 text-end">{{ \App\Support\Money::format($row->discount_total) }}</td>
                        <td class="py-2 px-4 text-end">{{ \App\Support\Money::format($row->vat_total) }}</td>
                        <td class="py-2 px-4 text-end font-semibold">{{ \App\Support\Money::format($row->total) }}</td>
                        <td class="py-2 px-4">
                            @php($paid = $row->isFullyPaid())
                            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $paid ? 'bg-emerald-50 text-emerald-700' : ((float) $row->amount_paid > 0 ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                                {{ $paid ? __('Paid') : ((float) $row->amount_paid > 0 ? __('Partial') : __('Unpaid')) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-slate-400">{{ __('No purchases in this period.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="py-2 px-4">{{ __('Date') }}</th>
                    <th class="py-2 px-4">{{ __('Reference') }}</th>
                    <th class="py-2 px-4">{{ __('Vendor') }}</th>
                    <th class="py-2 px-4">{{ __('Category') }}</th>
                    <th class="py-2 px-4 text-end">{{ __('Net Amount Excl. Tax') }}</th>
                    <th class="py-2 px-4 text-end">{{ __('Tax Amount') }}</th>
                    <th class="py-2 px-4 text-end">{{ __('Total Amount') }}</th>
                    <th class="py-2 px-4">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenseRows as $row)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="py-2 px-4 text-slate-500">{{ $row->expense_date?->format('Y-m-d') }}</td>
                        <td class="py-2 px-4 text-slate-500">{{ $row->reference ?: '—' }}</td>
                        <td class="py-2 px-4">{{ $row->vendor_name ?: '—' }}</td>
                        <td class="py-2 px-4 text-slate-500">{{ $row->category->name ?? '—' }}</td>
                        <td class="py-2 px-4 text-end">{{ \App\Support\Money::format($row->amount) }}</td>
                        <td class="py-2 px-4 text-end">{{ \App\Support\Money::format($row->vat_amount) }}</td>
                        <td class="py-2 px-4 text-end font-semibold">{{ \App\Support\Money::format($row->gross_amount ?? $row->amount) }}</td>
                        <td class="py-2 px-4">
                            @php($badge = match ($row->status) { 'approved' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-red-50 text-red-700', default => 'bg-slate-100 text-slate-600' })
                            <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ ucfirst((string) $row->status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-slate-400">{{ __('No expenses in this period.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</div>

<p class="mt-4 text-xs text-slate-400">{{ __('This is a summary for your own records. Verify figures against your ZATCA VAT return before filing.') }}</p>
@endsection
