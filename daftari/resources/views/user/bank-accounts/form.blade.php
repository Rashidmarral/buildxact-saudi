@extends('layouts.app')

@section('title', $account->exists ? __('Edit Account') : __('New Account'))

@section('content')
<form method="POST" action="{{ $account->exists ? route('app.bank-accounts.update', $account) : route('app.bank-accounts.store') }}" class="max-w-xl space-y-6">
    @csrf
    @if ($account->exists) @method('PUT') @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Account name') }}</label>
            <input type="text" name="name" value="{{ old('name', $account->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Type') }}</label>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="radio" name="type" value="bank" @checked(old('type', $account->type ?? 'bank') === 'bank') class="text-brand-600 focus:ring-brand-500">
                    {{ __('Bank') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="radio" name="type" value="cash" @checked(old('type', $account->type) === 'cash') class="text-brand-600 focus:ring-brand-500">
                    {{ __('Cash') }}
                </label>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Bank name') }}</label>
            <input type="text" name="bank_name" value="{{ old('bank_name', $account->bank_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Account number') }}</label>
                <input type="text" name="account_number" value="{{ old('account_number', $account->account_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('IBAN') }}</label>
                <input type="text" name="iban" value="{{ old('iban', $account->iban) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Opening balance') }}</label>
            <input type="number" step="0.01" name="opening_balance" value="{{ old('opening_balance', $account->opening_balance ?? 0) }}" @if($account->exists) disabled @endif class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500 disabled:bg-slate-50 disabled:text-slate-400">
            @if ($account->exists)
                <p class="text-xs text-slate-400 mt-1">{{ __("Opening balance can't be changed after the account has moved money — its current balance is calculated from recorded activity.") }}</p>
                <input type="hidden" name="opening_balance" value="{{ $account->opening_balance }}">
            @endif
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('app.bank-accounts.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
