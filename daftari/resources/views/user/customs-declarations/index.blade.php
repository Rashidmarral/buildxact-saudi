@extends('layouts.app')

@section('title', __('Customs Declarations'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Customs Declarations') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('The import documents behind your recoverable import VAT.') }}</p>
    </div>
    <button type="button" onclick="document.getElementById('add-declaration-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Declaration') }}</button>
</div>

<div class="grid sm:grid-cols-2 gap-5 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-sm text-slate-500">{{ __('Declarations') }}</p>
        <p class="text-2xl font-bold text-slate-900 mt-1">{{ $totalCount }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-sm text-slate-500">{{ __('Recorded import VAT') }}</p>
        <p class="text-2xl font-bold text-slate-900 mt-1">SAR {{ number_format($totalVat, 2) }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($declarations->isEmpty())
        <p class="px-6 py-16 text-center text-sm text-slate-500">{{ __('No customs declarations recorded yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Declaration') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Port of entry') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Supplier') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Customs value') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Import VAT') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Linked bills') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($declarations as $declaration)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $declaration->declaration_number ?: '#'.$declaration->id }}</td>
                        <td class="px-6 py-3">{{ $declaration->declaration_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ $declaration->port_of_entry ?: '—' }}</td>
                        <td class="px-6 py-3">{{ $declaration->supplier->name ?? '—' }}</td>
                        <td class="px-6 py-3">SAR {{ number_format($declaration->customs_value, 2) }}</td>
                        <td class="px-6 py-3">SAR {{ number_format($declaration->vat_amount, 2) }}</td>
                        <td class="px-6 py-3">{{ $declaration->bills->count() }}</td>
                        <td class="px-6 py-3 text-right">
                            <form method="POST" action="{{ route('app.customs-declarations.destroy', $declaration) }}" onsubmit="return confirm('{{ __('Delete this declaration?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<div class="mt-4">{{ $declarations->links() }}</div>

<dialog id="add-declaration-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-2xl backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.customs-declarations.store') }}" class="p-6 space-y-5">
        @csrf
        <div class="flex items-start justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900">{{ __('New customs declaration') }}</h3>
                <p class="text-sm text-slate-500 mt-1">{{ __('Enter the figures as they appear on the customs declaration, not as they appear on the supplier\'s invoice.') }}</p>
            </div>
            <button type="button" onclick="document.getElementById('add-declaration-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Declaration number') }}</label>
                <input type="text" name="declaration_number" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <p class="text-xs text-slate-400 mt-1">{{ __('The number printed on the customs declaration') }}</p>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Declaration date') }}</label>
                <input type="date" name="declaration_date" value="{{ now()->toDateString() }}" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Port of entry') }}</label>
                <input type="text" name="port_of_entry" placeholder="{{ __('For example: Jeddah Islamic Port') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Supplier') }}</label>
                <select id="declaration-supplier" name="supplier_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('Select supplier') }}</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">{{ __('Optional. Links the declaration to a supplier record.') }}</p>
            </div>
        </div>

        <div class="bg-slate-50 rounded-xl p-4">
            <h4 class="font-semibold text-slate-900">{{ __('Import VAT base') }}</h4>
            <p class="text-sm text-slate-500 mt-1 mb-3">{{ __('The base is the assessed customs (CIF) value plus customs duty. The supplier\'s invoice total appears in no box on the return.') }}</p>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Customs value (CIF)') }}</label>
                    <input type="number" step="0.01" min="0" id="customs-value" name="customs_value" value="0" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <p class="text-xs text-slate-400 mt-1">{{ __('The value as assessed by Saudi Customs, not the supplier\'s invoice total.') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Customs duty') }}</label>
                    <input type="number" step="0.01" min="0" id="customs-duty" name="customs_duty" value="0" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <p class="text-xs text-slate-400 mt-1">{{ __('Duty assessed on the declaration') }}</p>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('VAT base') }}</label>
                    <input type="text" id="vat-base" disabled value="SAR 0.00" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-100 text-sm text-slate-500">
                    <p class="text-xs text-slate-400 mt-1">{{ __('Customs value + customs duty. Calculated for you.') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('VAT rate') }}</label>
                    <select id="vat-rate" name="vat_rate" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="15">15%</option>
                        <option value="0">0%</option>
                    </select>
                    <p class="text-xs text-slate-400 mt-1">{{ __('Import VAT is either 15% or zero.') }}</p>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('VAT amount') }}</label>
                    <input type="text" id="vat-amount" disabled value="SAR 0.00" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-100 text-sm text-slate-500">
                    <p class="text-xs text-slate-400 mt-1">{{ __('VAT base × rate. Calculated for you.') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('SADAD reference') }}</label>
                    <input type="text" name="sadad_reference" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <p class="text-xs text-slate-400 mt-1">{{ __('The SADAD number the VAT was paid under at clearance') }}</p>
                </div>
            </div>
        </div>

        <div>
            <h4 class="font-semibold text-slate-900">{{ __('Linked bills') }}</h4>
            <p class="text-sm text-slate-500 mt-1 mb-2">{{ __('Link the supplier bills for this shipment. Optional.') }}</p>
            <div id="linked-bills-list" class="space-y-1 text-sm text-slate-500">{{ __('No purchase bills to link.') }}</div>
        </div>

        <div class="flex justify-end gap-3">
            <button type="button" onclick="document.getElementById('add-declaration-modal').close()" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    const cif = document.getElementById('customs-value');
    const duty = document.getElementById('customs-duty');
    const rate = document.getElementById('vat-rate');
    const baseField = document.getElementById('vat-base');
    const amountField = document.getElementById('vat-amount');

    function recalc() {
        const base = (parseFloat(cif.value) || 0) + (parseFloat(duty.value) || 0);
        const vat = base * (parseFloat(rate.value) || 0) / 100;
        baseField.value = 'SAR ' + base.toFixed(2);
        amountField.value = 'SAR ' + vat.toFixed(2);
    }

    [cif, duty, rate].forEach(el => el.addEventListener('input', recalc));
    rate.addEventListener('change', recalc);
    recalc();

    const supplierSelect = document.getElementById('declaration-supplier');
    const billsList = document.getElementById('linked-bills-list');
    const noBillsText = @json(__('No purchase bills to link.'));
    const loadingText = @json(__('Loading bills…'));

    supplierSelect.addEventListener('change', function () {
        const supplierId = this.value;
        if (! supplierId) {
            billsList.textContent = noBillsText;
            return;
        }

        billsList.textContent = loadingText;

        fetch(`{{ url('/app/suppliers') }}/${supplierId}/bills`)
            .then(r => r.json())
            .then(bills => {
                if (! bills.length) {
                    billsList.textContent = noBillsText;
                    return;
                }

                billsList.innerHTML = bills.map(bill => `
                    <label class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 px-3 py-2 hover:bg-slate-50">
                        <span class="flex items-center gap-2">
                            <input type="checkbox" name="bill_ids[]" value="${bill.id}" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <span class="text-slate-700">${bill.bill_number}</span>
                            <span class="text-slate-400">${bill.bill_date}</span>
                        </span>
                        <span class="text-slate-500">SAR ${bill.total}</span>
                    </label>
                `).join('');
            });
    });
})();
</script>
@endsection
