@extends('layouts.app')

@section('title', __('Uncleared Bank Items'))

@section('content')
<div class="flex items-start justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Uncleared Bank Items') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Deposits not yet on the bank statement and payments or cheques not yet presented, as at a date.') }}</p>
    </div>
    @if ($bankAccount)
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('app.reports.uncleared-bank-items', array_merge(request()->query(), ['export' => 'csv'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download CSV') }}</a>
            <a href="{{ route('app.reports.uncleared-bank-items', array_merge(request()->query(), ['export' => 'pdf'])) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        </div>
    @endif
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-center gap-3">
    <select name="bank_account_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[16rem]">
        @foreach ($accounts as $acc)
            <option value="{{ $acc->id }}" @selected($bankAccount && $bankAccount->id === $acc->id)>{{ $acc->name }}</option>
        @endforeach
    </select>
    <label class="text-xs text-slate-400">{{ __('As at') }}</label>
    <input type="date" name="as_at" value="{{ $asOf->toDateString() }}" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
</form>

@if ($bankAccount)
    <div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-5 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-5 py-3 font-medium">{{ __('Description') }}</th>
                    <th class="px-5 py-3 font-medium text-end">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-5 py-3 text-slate-500">{{ $row['date']->format('Y-m-d') }}</td>
                        <td class="px-5 py-3">{{ $row['label'] }}</td>
                        <td class="px-5 py-3 text-end {{ $row['amount'] >= 0 ? 'text-emerald-700' : 'text-slate-700' }}">{{ \App\Support\Money::format($row['amount']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-10 text-center text-slate-400">{{ __('Nothing outstanding — every booked transaction has cleared the bank.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr class="border-t-2 border-slate-800 font-semibold text-slate-900">
                        <td class="px-5 py-3" colspan="2">{{ __('Net effect on balance') }}</td>
                        <td class="px-5 py-3 text-end">{{ \App\Support\Money::format($total) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@else
    <div class="bg-white rounded-xl border border-slate-100 px-6 py-16 text-center text-sm text-slate-400">{{ __('Add a bank account to see this report.') }}</div>
@endif
@endsection
