@extends('layouts.app')

@section('title', __('Ledger'))

@section('content')
@include('user.accounting.partials.tabs')

<div class="mb-6">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('General Ledger') }}</h2>
    <p class="text-sm text-slate-500 mt-1">{{ __('Every journal line posted to a single account, with a running balance.') }}</p>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-center gap-3">
    <select name="account_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[16rem]">
        @foreach ($accounts as $acc)
            <option value="{{ $acc->id }}" @selected($account && $account->id === $acc->id)>{{ $acc->label() }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <span class="text-slate-400 text-sm">{{ __('to') }}</span>
    <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Filter') }}</button>
</form>

@if ($account)
    <div class="bg-white rounded-xl border border-slate-100">
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
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">{{ __('No activity on this account yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@else
    <div class="bg-white rounded-xl border border-slate-100 px-6 py-16 text-center text-sm text-slate-400">{{ __('Add an account to the chart of accounts to see its ledger here.') }}</div>
@endif
@endsection
