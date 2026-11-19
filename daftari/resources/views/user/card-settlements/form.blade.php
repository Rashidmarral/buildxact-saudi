@extends('layouts.app')

@section('title', __('New settlement'))

@section('content')
<div x-data="{
        gross: {{ old('gross_amount', 0) }},
        fee: {{ old('fee_amount', 0) }},
        feeVat: {{ old('fee_vat_amount', 0) }},
        net() { return (Number(this.gross || 0) - Number(this.fee || 0) - Number(this.feeVat || 0)).toFixed(2); },
    }">
<form method="POST" action="{{ route('app.card-settlements.store') }}" class="max-w-xl space-y-6">
    @csrf

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Payment mode') }}</label>
            <select name="payment_mode_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None / unspecified') }}</option>
                @foreach ($paymentModes as $mode)
                    <option value="{{ $mode->id }}" @selected(old('payment_mode_id') == $mode->id)>{{ $mode->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">{{ __('Which clearing account this settlement relieves — defaults to POS Card Clearing when left unspecified.') }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Deposited to') }}</label>
            <select name="bank_account_id" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select a bank account') }}</option>
                @foreach ($bankAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('bank_account_id') == $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Settlement date') }}</label>
            <input type="date" name="settlement_date" value="{{ old('settlement_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Gross amount') }}</label>
                <input type="number" step="0.01" min="0.01" name="gross_amount" x-model="gross" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Fee (MDR)') }}</label>
                <input type="number" step="0.01" min="0" name="fee_amount" x-model="fee" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('VAT on fee') }}</label>
                <input type="number" step="0.01" min="0" name="fee_vat_amount" x-model="feeVat" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
        <div class="rounded-lg bg-slate-50 px-4 py-3 flex items-center justify-between">
            <span class="text-sm font-medium text-slate-600">{{ __('Net deposited') }}</span>
            <span class="text-lg font-bold text-slate-900" x-text="net()"></span>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Reference') }}</label>
            <input type="text" name="reference" value="{{ old('reference') }}" placeholder="{{ __('Processor settlement reference') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('app.card-settlements.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>
</div>
@endsection
