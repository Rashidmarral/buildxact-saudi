@extends('layouts.app')

@section('title', __('New Manual Journal Entry'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('New manual journal entry') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('For adjustments, corrections, and opening balances that have no source document — total debits must equal total credits.') }}</p>
    </div>
    <div class="flex items-center gap-3">
        <button type="submit" form="manual-journal-entry-form" class="rounded-lg bg-brand-700 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">{{ __('Post entry') }}</button>
        <a href="{{ route('app.journals.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back') }}</a>
    </div>
</div>

@if ($errors->any())
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc ps-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('app.journals.manual.store') }}" id="manual-journal-entry-form" class="space-y-6">
    @csrf

    <div class="bg-white rounded-xl border border-slate-100 p-6 grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Date') }}</label>
            <input type="date" name="entry_date" required value="{{ old('entry_date', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Description') }}</label>
            <input type="text" name="description" required value="{{ old('description') }}" placeholder="{{ __('e.g. Correct miscoded expense from July') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-slate-900">{{ __('Journal lines') }}</h3>
            <button type="button" id="add-row" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('+ Add line') }}</button>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="py-2 pe-3 font-medium">{{ __('Account') }}</th>
                    <th class="py-2 pe-3 font-medium text-end w-32">{{ __('Debit') }}</th>
                    <th class="py-2 pe-3 font-medium text-end w-32">{{ __('Credit') }}</th>
                    <th class="py-2 pe-3 font-medium">{{ __('Memo') }}</th>
                    <th class="py-2 w-8"></th>
                </tr>
            </thead>
            <tbody id="lines-body"></tbody>
            <tfoot>
                <tr class="border-t-2 border-slate-800 font-semibold text-slate-900">
                    <td class="py-2 pe-3">{{ __('Total') }}</td>
                    <td class="py-2 pe-3 text-end" id="total-debit">0.00</td>
                    <td class="py-2 pe-3 text-end" id="total-credit">0.00</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
        <p id="balance-warning" class="mt-2 text-xs font-medium text-red-600" hidden>{{ __('Total debits must equal total credits before this can be posted.') }}</p>
    </div>
</form>

<script>
const accounts = @json($accounts->map(fn ($a) => ['id' => $a->id, 'label' => $a->code.' — '.$a->name]));

const tbody = document.getElementById('lines-body');
let rowIndex = 0;

function accountOptions() {
    return accounts.map(a => `<option value="${a.id}">${a.label}</option>`).join('');
}

function addRow() {
    const idx = rowIndex++;
    const tr = document.createElement('tr');
    tr.className = 'border-b border-slate-50';
    tr.innerHTML = `
        <td class="py-2 pe-3">
            <select name="lines[${idx}][account_id]" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">${@json(__('Select an account'))}</option>
                ${accountOptions()}
            </select>
        </td>
        <td class="py-2 pe-3"><input type="number" step="0.01" min="0" name="lines[${idx}][debit]" data-role="debit" class="w-full rounded-lg border border-slate-200 text-end text-sm focus:border-brand-500 focus:ring-brand-500"></td>
        <td class="py-2 pe-3"><input type="number" step="0.01" min="0" name="lines[${idx}][credit]" data-role="credit" class="w-full rounded-lg border border-slate-200 text-end text-sm focus:border-brand-500 focus:ring-brand-500"></td>
        <td class="py-2 pe-3"><input type="text" name="lines[${idx}][memo]" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
        <td class="py-2 text-end"><button type="button" class="remove-row text-slate-400 hover:text-red-600">✕</button></td>
    `;
    tbody.appendChild(tr);

    const debitInput = tr.querySelector('[data-role="debit"]');
    const creditInput = tr.querySelector('[data-role="credit"]');
    debitInput.addEventListener('input', () => { if (parseFloat(debitInput.value) > 0) creditInput.value = ''; recalc(); });
    creditInput.addEventListener('input', () => { if (parseFloat(creditInput.value) > 0) debitInput.value = ''; recalc(); });
    tr.querySelector('.remove-row').addEventListener('click', () => { tr.remove(); recalc(); });
}

function recalc() {
    let totalDebit = 0, totalCredit = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
        totalDebit += parseFloat(tr.querySelector('[data-role="debit"]').value) || 0;
        totalCredit += parseFloat(tr.querySelector('[data-role="credit"]').value) || 0;
    });
    document.getElementById('total-debit').textContent = totalDebit.toFixed(2);
    document.getElementById('total-credit').textContent = totalCredit.toFixed(2);
    document.getElementById('balance-warning').hidden = Math.abs(totalDebit - totalCredit) < 0.01;
}

document.getElementById('add-row').addEventListener('click', () => { addRow(); recalc(); });

addRow();
addRow();
recalc();
</script>
@endsection
