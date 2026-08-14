@extends('layouts.app')

@section('title', __('New Payment Voucher'))

@section('content')
<div class="mb-2">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('New Payment Voucher') }}</h2>
    <p class="text-sm text-slate-500 mt-1">{{ __('Create a clear voucher with the amount, party, and payment details in one place.') }}</p>
</div>

<form method="POST" action="{{ route('app.payment-vouchers.store') }}">
    @csrf

    <div class="flex items-center justify-between my-6">
        <a href="{{ route('app.payment-vouchers.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">&larr; {{ __('Back') }}</a>
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h3 class="font-semibold text-slate-900">{{ __('Party details') }}</h3>
            <p class="text-xs text-slate-500 mt-1 mb-4">{{ __('Choose a party from your records or enter the details manually.') }}</p>

            <div class="flex items-center gap-1 mb-5 text-sm bg-slate-100 rounded-lg p-1 w-fit">
                <button type="button" data-tab="manual" class="party-tab rounded-md px-3 py-1.5 font-semibold bg-white shadow-sm">{{ __('Manual') }}</button>
                <button type="button" data-tab="customer" class="party-tab rounded-md px-3 py-1.5 font-semibold text-slate-500">{{ __('Customer') }}</button>
                <button type="button" data-tab="supplier" class="party-tab rounded-md px-3 py-1.5 font-semibold text-slate-500">{{ __('Supplier') }}</button>
            </div>
            <input type="hidden" name="party_type" id="party_type" value="{{ old('party_type', 'manual') }}">

            <div id="panel-customer" class="party-panel hidden mb-4">
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Customer') }}</label>
                <select name="client_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('Select a customer') }}</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">{{ __('For refunds paid back to a customer.') }}</p>
            </div>

            <div id="panel-supplier" class="party-panel hidden space-y-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Supplier') }}</label>
                    <select name="supplier_id" id="supplier_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('Select a supplier') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Apply to bill (optional)') }}</label>
                    <select name="bill_id" id="bill_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('None') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Related expense (optional)') }}</label>
                    <select name="expense_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($expenses as $expense)
                            <option value="{{ $expense->id }}" @selected(old('expense_id') == $expense->id)>{{ $expense->vendor_name }} — SAR {{ number_format($expense->amount, 2) }} ({{ $expense->expense_date->format('Y-m-d') }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Party name (Arabic)') }}</label>
                    <input type="text" name="party_name_ar" value="{{ old('party_name_ar') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Party name (English)') }}</label>
                    <input type="text" name="payee_name" value="{{ old('payee_name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('VAT number') }}</label>
                    <input type="text" name="party_vat_number" value="{{ old('party_vat_number') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Phone') }}</label>
                    <input type="text" name="party_phone" value="{{ old('party_phone') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Email') }}</label>
                    <input type="email" name="party_email" value="{{ old('party_email') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Address') }}</label>
                    <input type="text" name="party_address" value="{{ old('party_address') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h3 class="font-semibold text-slate-900">{{ __('Voucher details') }}</h3>
            <p class="text-xs text-slate-500 mt-1 mb-4">{{ __('Enter the date, accounts, and amount for this voucher.') }}</p>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Voucher date') }}</label>
                    <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Financial account') }}</label>
                        <select name="bank_account_id" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">{{ __('Select an account') }}</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected(old('bank_account_id') == $account->id)>{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Counter account (optional)') }}</label>
                        <select name="counter_account_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">{{ __('Automatic') }}</option>
                            @foreach ($glAccounts as $gl)
                                <option value="{{ $gl->id }}" @selected(old('counter_account_id') == $gl->id)>{{ $gl->label() }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">{{ __('Leave on Automatic to post against Accounts Payable / Operating Expenses as usual.') }}</p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Amount') }}</label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Payment method') }}</label>
                    <select name="method" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        <option value="cash">{{ __('Cash') }}</option>
                        <option value="bank_transfer">{{ __('Bank transfer') }}</option>
                        <option value="card">{{ __('Card') }}</option>
                        <option value="cheque">{{ __('Cheque') }}</option>
                    </select>
                </div>
            </div>

            <div class="mt-6">
                <h4 class="font-semibold text-slate-900">{{ __('Additional details') }}</h4>
                <p class="text-xs text-slate-500 mt-1 mb-3">{{ __('Add a reference or description to make the transaction easier to find later.') }}</p>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reference') }}</label>
                <input type="text" name="reference" value="{{ old('reference') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <label class="block text-xs font-semibold uppercase text-slate-500 mt-4">{{ __('Notes') }}</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const tabs = document.querySelectorAll('.party-tab');
    const typeInput = document.getElementById('party_type');
    const panels = { customer: document.getElementById('panel-customer'), supplier: document.getElementById('panel-supplier') };

    function activate(tab) {
        typeInput.value = tab;
        tabs.forEach(btn => {
            const active = btn.dataset.tab === tab;
            btn.classList.toggle('bg-white', active);
            btn.classList.toggle('shadow-sm', active);
            btn.classList.toggle('text-slate-500', !active);
        });
        Object.entries(panels).forEach(([key, el]) => el.classList.toggle('hidden', key !== tab));
    }

    tabs.forEach(btn => btn.addEventListener('click', () => activate(btn.dataset.tab)));
    activate(typeInput.value || 'manual');

    const supplierSelect = document.getElementById('supplier_id');
    const billSelect = document.getElementById('bill_id');

    function loadBills() {
        billSelect.innerHTML = '<option value="">' + @json(__('None')) + '</option>';
        if (!supplierSelect.value) return;
        fetch('{{ url('/app/suppliers') }}/' + supplierSelect.value + '/outstanding-bills')
            .then(r => r.json())
            .then(bills => {
                bills.forEach(bill => {
                    const opt = document.createElement('option');
                    opt.value = bill.id;
                    opt.textContent = bill.bill_number + ' — SAR ' + bill.balance;
                    billSelect.appendChild(opt);
                });
            });
    }

    supplierSelect.addEventListener('change', loadBills);
    if (supplierSelect.value) loadBills();
})();
</script>
@endsection
