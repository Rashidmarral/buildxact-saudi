@extends('layouts.app')

@section('title', __('Cash & Banks'))

@section('content')
<div class="flex flex-wrap items-center gap-2 mb-6 text-sm">
    <a href="{{ route('app.bank-accounts.index') }}" class="rounded-lg px-3 py-1.5 bg-slate-900 text-white">{{ __('Accounts') }}</a>
    <a href="{{ route('app.receipt-vouchers.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Receipt vouchers') }}</a>
    <a href="{{ route('app.payment-vouchers.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Payment vouchers') }}</a>
    <a href="{{ route('app.bank-transfers.index') }}" class="rounded-lg px-3 py-1.5 text-slate-600 hover:bg-slate-100">{{ __('Transfers') }}</a>
</div>

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Bank and cash accounts your receipts, payments, and transfers move through.') }}</p>
    <a href="{{ route('app.bank-accounts.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add account') }}</a>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    @forelse ($accounts as $account)
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-900">{{ $account->name }}</span>
                <span class="text-xs text-slate-400">{{ $account->type === 'cash' ? __('Cash') : __('Bank') }}</span>
            </div>
            @if ($account->bank_name)<p class="text-xs text-slate-400 mt-1">{{ $account->bank_name }}</p>@endif
            <div class="mt-3 text-2xl font-bold text-slate-900">SAR {{ auth()->user()->company->formatNumber($account->currentBalance()) }}</div>
            <div class="mt-4 flex gap-3 text-xs">
                <a href="{{ route('app.bank-accounts.edit', $account) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                <form method="POST" action="{{ route('app.bank-accounts.destroy', $account) }}" onsubmit="return confirm('{{ __('Delete this account?') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                </form>
            </div>
        </div>
    @empty
        <div class="col-span-full bg-white rounded-xl border border-slate-100 px-6 py-16 text-center">
            <p class="text-sm text-slate-500 mb-4">{{ __('No accounts yet. Add your first bank or cash account to start recording receipts and payments.') }}</p>
            <a href="{{ route('app.bank-accounts.create') }}" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add account') }}</a>
        </div>
    @endforelse
</div>
@endsection
