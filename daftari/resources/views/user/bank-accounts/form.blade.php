@extends('layouts.app')

@section('title', $account->exists ? __('Edit Account') : __('New Account'))

@section('content')
<form method="POST" action="{{ $account->exists ? route('app.bank-accounts.update', $account) : route('app.bank-accounts.store') }}" class="max-w-2xl space-y-6">
    @csrf
    @if ($account->exists) @method('PUT') @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Account type') }}</label>
                <select name="type" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="bank" @selected(old('type', $account->type ?? 'bank') === 'bank')>{{ __('Bank') }}</option>
                    <option value="cash" @selected(old('type', $account->type) === 'cash')>{{ __('Cash') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Currency') }}</label>
                <select name="currency" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    @foreach (\App\Models\Currency::active()->orderBy('sort_order')->get() as $currencyOption)
                        <option value="{{ $currencyOption->code }}" @selected(old('currency', $account->currency ?: \App\Support\Money::defaultCode()) === $currencyOption->code)>{{ $currencyOption->code }} - {{ $currencyOption->name }} ({{ $currencyOption->symbol }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-5">
            <h4 class="font-semibold text-slate-900">{{ __('Bank details') }}</h4>
            <p class="text-xs text-slate-500 mt-1 mb-3">{{ __('These details appear on invoices as your payment information.') }}</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Bank name') }}</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $account->bank_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Account holder name') }}</label>
                    <input type="text" name="account_holder_name" value="{{ old('account_holder_name', $account->account_holder_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('IBAN') }}</label>
                    <input type="text" name="iban" value="{{ old('iban', $account->iban) }}" placeholder="SA00 0000 0000 0000 0000 0000" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Account number') }}</label>
                    <input type="text" name="account_number" value="{{ old('account_number', $account->account_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Bank phone') }}</label>
                    <input type="text" name="bank_phone" value="{{ old('bank_phone', $account->bank_phone) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Bank address') }}</label>
                    <input type="text" name="bank_address" value="{{ old('bank_address', $account->bank_address) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-5">
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Account name') }}</label>
            <input type="text" name="name" value="{{ old('name', $account->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div class="border-t border-slate-100 pt-5">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active ?? true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Active account') }}
            </label>
            <p class="text-xs text-slate-400 mt-1">{{ __('Inactive accounts are hidden from voucher and transfer forms but keep their history.') }}</p>
        </div>

        <div class="border-t border-slate-100 pt-5">
            <h4 class="font-semibold text-slate-900">{{ __('Opening balance (optional)') }}</h4>
            <div class="grid sm:grid-cols-2 gap-4 mt-3">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Opening balance amount') }}</label>
                    @if ($account->exists)
                        <input type="number" step="0.01" value="{{ $account->opening_balance }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
                        <input type="hidden" name="opening_balance" value="{{ $account->opening_balance }}">
                        <p class="text-xs text-slate-400 mt-1">{{ __("Opening balance can't be changed after the account has moved money — its current balance is calculated from recorded activity.") }}</p>
                    @else
                        <input type="number" step="0.01" name="opening_balance" value="{{ old('opening_balance', 0) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    @endif
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Opening balance date') }}</label>
                    <input type="date" name="opening_balance_date" value="{{ old('opening_balance_date', optional($account->opening_balance_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reference (optional)') }}</label>
                <input type="text" name="opening_balance_reference" value="{{ old('opening_balance_reference', $account->opening_balance_reference) }}" placeholder="{{ __('e.g., Initial balance from previous system') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('app.bank-accounts.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
