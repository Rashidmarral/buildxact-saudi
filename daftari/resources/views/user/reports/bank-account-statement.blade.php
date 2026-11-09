@extends('layouts.app')

@section('title', __('Bank/Cash Account Statement'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Bank/Cash Account Statement') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Money in and out of one bank or cash account, with opening, running and closing balance, straight from its ledger.') }}</p>
    </div>
    @if ($bankAccount)
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('app.reports.bank-account-statement', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
            <a href="{{ route('app.reports.bank-account-statement', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        </div>
    @endif
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-center gap-3">
    <select name="bank_account_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[16rem]">
        @foreach ($bankAccounts as $acc)
            <option value="{{ $acc->id }}" @selected($bankAccount && $bankAccount->id === $acc->id)>{{ $acc->name }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ $period['from']->toDateString() }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <span class="text-slate-400 text-sm">{{ __('to') }}</span>
    <input type="date" name="to" value="{{ $period['to']->toDateString() }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <input type="hidden" name="period" value="custom">
    <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Filter') }}</button>
</form>

@if ($bankAccount)
    <div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-5 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-5 py-3 font-medium">{{ __('Entry') }}</th>
                    <th class="px-5 py-3 font-medium">{{ __('Description') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Debit') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Credit') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-slate-50 text-slate-400 italic">
                    <td class="px-5 py-3" colspan="5">{{ __('Opening balance') }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format($openingBalance, 2) }}</td>
                </tr>
                @forelse ($lines as $line)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-3 text-slate-500">{{ $line->journalEntry->entry_date->format('Y-m-d') }}</td>
                        <td class="px-5 py-3"><a href="{{ route('app.journals.show', $line->journalEntry) }}" class="text-brand-700 hover:underline">{{ $line->journalEntry->entry_number }}</a></td>
                        <td class="px-5 py-3">{{ $line->journalEntry->description }}</td>
                        <td class="px-5 py-3 text-end">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                        <td class="px-5 py-3 text-end">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                        <td class="px-5 py-3 text-end font-medium">{{ number_format($line->running_balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">{{ __('No activity on this account in this period.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-slate-800 font-semibold text-slate-900">
                    <td class="px-5 py-3" colspan="5">{{ __('Closing balance') }}</td>
                    <td class="px-5 py-3 text-end">{{ number_format($closingBalance, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@else
    <div class="bg-white rounded-xl border border-slate-100 px-6 py-16 text-center text-sm text-slate-400">{{ __('Add a bank account to see this report.') }}</div>
@endif
@endsection
