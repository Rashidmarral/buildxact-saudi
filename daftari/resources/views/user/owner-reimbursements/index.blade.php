@extends('layouts.app')

@section('title', __('Owner Reimbursements'))

@section('content')
@include('user.bank-accounts.partials.tabs')

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Expenses paid personally — out of your own pocket, not from a company account — that the company owes back to you.') }}</p>
    <div class="flex items-center gap-2">
        <a href="{{ route('app.owner-reimbursements.pdf') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <a href="{{ route('app.expenses.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New expense') }}</a>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-5 mb-6 flex items-center justify-between">
    <div>
        <p class="text-sm text-slate-500">{{ __('Currently owed to you') }}</p>
        <p class="text-xs text-slate-400 mt-0.5">{{ trans_choice(':count expense|:count expenses', $outstandingCount, ['count' => $outstandingCount]) }}</p>
    </div>
    <p class="text-xl font-bold text-slate-900">{{ \App\Support\Money::format($totalOutstanding) }}</p>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
    @if ($expenses->isEmpty())
        <div class="px-6 py-16 text-center">
            <p class="text-sm text-slate-500 mb-4">{{ __('No owner-paid expenses yet. When you pay for something personally, tick "I paid this personally" on the expense form.') }}</p>
            <a href="{{ route('app.expenses.create') }}" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New expense') }}</a>
        </div>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Description') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Category') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Amount') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($expenses as $expense)
                    @php($settled = $expense->isSettled())
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3">{{ $expense->expense_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 text-slate-700">{{ $expense->vendor_name ?: $expense->description }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $expense->category?->name ?? '—' }}</td>
                        <td class="px-6 py-3 text-right font-semibold tabular-nums">{{ \App\Support\Money::format($expense->gross_amount ?? ($expense->amount + $expense->vat_amount)) }}</td>
                        <td class="px-6 py-3">
                            @if ($settled)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs text-emerald-700">{{ __('Reimbursed') }}</span>
                            @else
                                <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-xs text-amber-700">{{ __('Outstanding') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            @if ($settled)
                                <a href="{{ route('app.expenses.edit', $expense) }}" class="text-brand-700 hover:underline">{{ __('View') }}</a>
                            @else
                                <a href="{{ route('app.payment-vouchers.create', ['expense_id' => $expense->id]) }}" class="text-brand-700 hover:underline">{{ __('Settle') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">@include('partials.pagination', ['paginator' => $expenses])</div>
@endsection
