@extends('layouts.app')

@section('title', __('Custody / Advances'))

@section('content')
@include('user.bank-accounts.partials.tabs')

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Cash floats held by site staff or managers — an advance given to someone, spent on your behalf, with a running balance of what is still unaccounted for.') }}</p>
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('app.cash-custody.report') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Custody Report') }}</a>
        <a href="{{ route('app.bank-accounts.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add custody account') }}</a>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-5 mb-6 flex items-center justify-between">
    <p class="text-sm text-slate-500">{{ __('Total currently out with custodians') }}</p>
    <p class="text-xl font-bold text-slate-900">{{ \App\Support\Money::format($totalOutstanding) }}</p>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
    @if ($accounts->isEmpty())
        <div class="px-6 py-16 text-center">
            <p class="text-sm text-slate-500 mb-4">{{ __('No custody accounts yet. Add a cash or bank account and mark it "Personal / held by" with the person\'s name — e.g. "Khalid – Site Custody".') }}</p>
            <a href="{{ route('app.bank-accounts.create') }}" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add custody account') }}</a>
        </div>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Held by') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Account') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Type') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Current balance') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($accounts as $account)
                    @php($idle = $idleDays[$account->id] ?? null)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-900">{{ $account->personal_owner_name ?: '—' }}</td>
                        <td class="px-6 py-3">{{ $account->name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $account->type === 'cash' ? __('Cash') : __('Bank') }}</td>
                        <td class="px-6 py-3 text-right">
                            <span class="font-semibold tabular-nums">{{ \App\Support\Money::format($account->currentBalance()) }}</span>
                            @if ($idle !== null && $idle >= $idleAlertThreshold && (float) $account->currentBalance() != 0)
                                <span class="block mt-0.5 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">{{ __('Outstanding :days days', ['days' => $idle]) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('app.bank-transfers.create', ['to_bank_account_id' => $account->id]) }}" class="text-brand-700 hover:underline">{{ __('Give advance') }}</a>
                            <span class="text-slate-300 mx-1">&middot;</span>
                            <a href="{{ route('app.expenses.create', ['bank_account_id' => $account->id]) }}" class="text-brand-700 hover:underline">{{ __('Record spend') }}</a>
                            <span class="text-slate-300 mx-1">&middot;</span>
                            <a href="{{ route('app.bank-transfers.create', ['from_bank_account_id' => $account->id]) }}" class="text-brand-700 hover:underline">{{ __('Return cash') }}</a>
                            <span class="text-slate-300 mx-1">&middot;</span>
                            @if ($hasProjectCashFlow)
                                <a href="{{ route('app.project-cash-flow.bank-account.show', $account) }}" class="text-slate-500 hover:underline">{{ __('Statement') }}</a>
                            @else
                                <a href="{{ route('app.bank-accounts.edit', $account) }}" class="text-slate-500 hover:underline">{{ __('Edit') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
