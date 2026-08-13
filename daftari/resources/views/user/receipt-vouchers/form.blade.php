@extends('layouts.app')

@section('title', __('New receipt voucher'))

@section('content')
<form method="POST" action="{{ route('app.receipt-vouchers.store') }}" class="max-w-xl space-y-6">
    @csrf

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Account') }}</label>
                <select name="bank_account_id" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('Select an account') }}</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected(old('bank_account_id') == $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Date') }}</label>
                <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Client (optional)') }}</label>
            <select name="client_id" id="client_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Apply to invoice (optional)') }}</label>
            <select name="invoice_id" id="invoice_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
            </select>
            <p class="text-xs text-slate-400 mt-1">{{ __("If selected, this receipt is recorded as a payment against that invoice's balance.") }}</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Received from') }}</label>
            <input type="text" name="payer_name" value="{{ old('payer_name') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Amount (SAR)') }}</label>
                <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Payment method') }}</label>
                <select name="method" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="cash">{{ __('Cash') }}</option>
                    <option value="bank_transfer">{{ __('Bank transfer') }}</option>
                    <option value="card">{{ __('Card') }}</option>
                    <option value="cheque">{{ __('Cheque') }}</option>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Reference (optional)') }}</label>
            <input type="text" name="reference" value="{{ old('reference') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('app.receipt-vouchers.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>

<script>
(function () {
    const clientSelect = document.getElementById('client_id');
    const invoiceSelect = document.getElementById('invoice_id');

    function loadInvoices() {
        invoiceSelect.innerHTML = '<option value="">' + @json(__('None')) + '</option>';
        if (!clientSelect.value) return;
        fetch('{{ url('/app/clients') }}/' + clientSelect.value + '/outstanding-invoices')
            .then(r => r.json())
            .then(invoices => {
                invoices.forEach(inv => {
                    const opt = document.createElement('option');
                    opt.value = inv.id;
                    opt.textContent = inv.invoice_number + ' — SAR ' + inv.balance;
                    invoiceSelect.appendChild(opt);
                });
            });
    }

    clientSelect.addEventListener('change', loadInvoices);
    if (clientSelect.value) loadInvoices();
})();
</script>
@endsection
