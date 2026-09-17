@extends('layouts.app')

@section('title', __('Cash & Banks'))

@section('content')
@include('user.bank-accounts.partials.tabs')

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Bank and cash accounts your receipts, payments, and transfers move through.') }}</p>
    <a href="{{ route('app.bank-accounts.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add account') }}</a>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    @forelse ($accounts as $account)
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-slate-900 flex items-center gap-1.5">
                    @if ($account->is_active)
                        <svg class="w-4 h-4 text-emerald-500 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-label="{{ __('Active') }}"><path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.5 7.5a1 1 0 01-1.42 0l-3.5-3.5a1 1 0 111.42-1.42L8.5 12.088l6.79-6.79a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    @endif
                    {{ $account->name }}
                </span>
                <span class="text-xs text-slate-400">{{ $account->type === 'cash' ? __('Cash') : __('Bank') }}</span>
            </div>
            @if ($account->bank_name)<p class="text-xs text-slate-400 mt-1">{{ $account->bank_name }}</p>@endif
            <div class="mt-3 text-2xl font-bold text-slate-900">{{ $account->currency }} {{ auth()->user()->company->formatNumber($account->currentBalance()) }}</div>
            @unless ($account->is_active)
                <span class="inline-block mt-2 rounded-full bg-slate-100 text-slate-500 text-xs font-medium px-2.5 py-1">{{ __('Inactive') }}</span>
            @endunless
            <div class="mt-4 flex gap-3 text-xs">
                <a href="{{ route('app.bank-reconciliations.index', $account) }}" class="text-brand-700 hover:underline">{{ __('Reconcile') }}</a>
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
