@extends('layouts.app')

@section('title', __('Transactions'))

@section('content')
@include('user.bank-accounts.partials.tabs')

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <p class="text-sm text-slate-500">{{ __('All receipts, payments, and transfers across your accounts.') }}</p>
    <form method="GET" class="flex items-center gap-2">
        <select name="bank_account_id" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">{{ __('All accounts') }}</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected($accountId == $account->id)>{{ $account->name }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($transactions->isEmpty())
        <p class="px-6 py-16 text-center text-sm text-slate-500">{{ __('No transactions yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Type') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Number') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Party / Accounts') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Account') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transactions as $t)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ $t['url'] }}'">
                        <td class="px-6 py-3">{{ $t['date']->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">
                            @if ($t['type'] === 'receipt')
                                <span class="inline-block rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium px-2.5 py-1">{{ __('Receipt') }}</span>
                            @elseif ($t['type'] === 'payment')
                                <span class="inline-block rounded-full bg-amber-50 text-amber-700 text-xs font-medium px-2.5 py-1">{{ __('Payment') }}</span>
                            @else
                                <span class="inline-block rounded-full bg-slate-100 text-slate-600 text-xs font-medium px-2.5 py-1">{{ __('Transfer') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 font-medium text-brand-700">{{ $t['number'] ?? '—' }}</td>
                        <td class="px-6 py-3">{{ $t['party'] }}</td>
                        <td class="px-6 py-3">{{ $t['account'] }}</td>
                        <td class="px-6 py-3">
                            @if ($t['type'] === 'payment')
                                <span class="text-red-600">- SAR {{ number_format($t['amount'], 2) }}</span>
                            @elseif ($t['type'] === 'receipt')
                                <span class="text-emerald-600">+ SAR {{ number_format($t['amount'], 2) }}</span>
                            @else
                                SAR {{ number_format($t['amount'], 2) }}
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            @if ($t['status'] === 'void')
                                <span class="inline-block rounded-full bg-red-50 text-red-600 text-xs font-medium px-2.5 py-1">{{ __('Void') }}</span>
                            @else
                                <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Issued') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
