@extends('layouts.app')

@section('title', __('Preview: Close Period'))

@section('content')
<div class="max-w-3xl">
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Preview: Close Period') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Review this before locking the books through :date. This step changes nothing yet — only "Confirm & Close" below does.', ['date' => $lockDate->format('Y-m-d')]) }}</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
        <h3 class="text-sm font-semibold text-slate-800 mb-4">{{ __('Net income for :from to :to', ['from' => $fiscalYearStart->format('Y-m-d'), 'to' => $lockDate->format('Y-m-d')]) }}</h3>
        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Net sales') }}</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ \App\Support\Money::format($incomeStatement['netSales']) }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Gross profit') }}</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ \App\Support\Money::format($incomeStatement['grossProfit']) }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase text-slate-500">{{ __('Net profit') }}</p>
                <p class="mt-1 text-lg font-bold {{ $incomeStatement['netProfit'] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ \App\Support\Money::format($incomeStatement['netProfit']) }}</p>
            </div>
        </div>
        <p class="mt-4 text-xs text-slate-400">{{ __('This is what the books currently imply as retained earnings for the stretch being closed — no closing journal entry is posted; the ledger keeps accumulating revenue/expense accounts as usual.') }}</p>
    </div>

    @if ($draftInvoices->isNotEmpty() || $draftBills->isNotEmpty() || $pendingExpenses->isNotEmpty())
        <div class="bg-white rounded-xl border border-amber-200 p-6 mb-6">
            <h3 class="text-sm font-semibold text-amber-800 mb-1">{{ __('Open items dated on or before the lock date') }}</h3>
            <p class="text-sm text-slate-500 mb-4">{{ __('These are still draft or pending approval — the date lock only blocks posted documents, so these will sit unresolved in a "closed" period unless you act on them first.') }}</p>

            @if ($draftInvoices->isNotEmpty())
                <p class="text-xs font-semibold uppercase text-slate-500 mt-4 mb-2">{{ __('Invoices') }} ({{ $draftInvoices->count() }})</p>
                <ul class="text-sm text-slate-700 space-y-1">
                    @foreach ($draftInvoices->take(10) as $invoice)
                        <li><a href="{{ route('app.invoices.show', $invoice) }}" class="text-brand-700 hover:underline">{{ $invoice->invoice_number }}</a> — {{ $invoice->issue_date->format('Y-m-d') }} — {{ \App\Support\Money::format($invoice->total) }} ({{ $invoice->status }})</li>
                    @endforeach
                    @if ($draftInvoices->count() > 10)<li class="text-slate-400">{{ __('and :count more', ['count' => $draftInvoices->count() - 10]) }}</li>@endif
                </ul>
            @endif

            @if ($draftBills->isNotEmpty())
                <p class="text-xs font-semibold uppercase text-slate-500 mt-4 mb-2">{{ __('Bills') }} ({{ $draftBills->count() }})</p>
                <ul class="text-sm text-slate-700 space-y-1">
                    @foreach ($draftBills->take(10) as $bill)
                        <li><a href="{{ route('app.bills.show', $bill) }}" class="text-brand-700 hover:underline">{{ $bill->bill_number }}</a> — {{ $bill->bill_date->format('Y-m-d') }} — {{ \App\Support\Money::format($bill->total) }} ({{ $bill->status }})</li>
                    @endforeach
                    @if ($draftBills->count() > 10)<li class="text-slate-400">{{ __('and :count more', ['count' => $draftBills->count() - 10]) }}</li>@endif
                </ul>
            @endif

            @if ($pendingExpenses->isNotEmpty())
                <p class="text-xs font-semibold uppercase text-slate-500 mt-4 mb-2">{{ __('Expenses') }} ({{ $pendingExpenses->count() }})</p>
                <ul class="text-sm text-slate-700 space-y-1">
                    @foreach ($pendingExpenses->take(10) as $expense)
                        <li>{{ $expense->description ?: $expense->vendor_name }} — {{ $expense->expense_date->format('Y-m-d') }} — {{ \App\Support\Money::format($expense->amount) }} ({{ __('pending approval') }})</li>
                    @endforeach
                    @if ($pendingExpenses->count() > 10)<li class="text-slate-400">{{ __('and :count more', ['count' => $pendingExpenses->count() - 10]) }}</li>@endif
                </ul>
            @endif
        </div>
    @else
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 mb-6">
            {{ __('No draft or pending-approval invoices, bills, or expenses dated on or before this lock date.') }}
        </div>
    @endif

    <div class="flex gap-3">
        <form method="POST" action="{{ route('app.settings.approvals.close-period') }}">
            @csrf
            <input type="hidden" name="accounting_lock_date" value="{{ $lockDate->toDateString() }}">
            <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Confirm & Close through :date', ['date' => $lockDate->format('Y-m-d')]) }}</button>
        </form>
        <a href="{{ route('app.settings.approvals') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</div>
@endsection
