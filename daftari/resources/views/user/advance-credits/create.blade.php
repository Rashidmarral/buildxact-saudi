@extends('layouts.app')

@section('title', __('New Advance Credit'))

@section('content')
<div class="mb-6">
    <a href="{{ route('app.advance-credits.index') }}" class="text-xs text-slate-400 hover:text-slate-600">&larr; {{ __('Advance Credits') }}</a>
    <h2 class="text-lg font-semibold text-slate-900 mt-1">{{ __('New advance credit') }}</h2>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6 max-w-3xl mb-5">
    <form method="GET" action="{{ route('app.advance-credits.create') }}">
        <label class="block text-xs font-medium text-slate-500">{{ __('Client') }}</label>
        <select name="client_id" onchange="this.form.submit()" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <option value="">{{ __('— Select a client —') }}</option>
            @foreach ($clients as $client)
                <option value="{{ $client->id }}" @selected($selectedClientId == $client->id)>{{ $client->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-[11px] text-slate-400">{{ __('Choosing a client reloads the page to list their invoices below.') }}</p>
    </form>
</div>

@if ($selectedClientId)
    <form method="POST" action="{{ route('app.advance-credits.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-5 max-w-3xl">
        @csrf
        <input type="hidden" name="client_id" value="{{ $selectedClientId }}">

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
                <input type="text" name="name" required placeholder="{{ __('e.g. INV-00001 advance payment') }}" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Project (optional)') }}</label>
                <select name="project_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('— None —') }}</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Source invoice (optional)') }}</label>
            <select name="source_invoice_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('— None / not in the system —') }}</option>
                @foreach ($invoices as $invoice)
                    <option value="{{ $invoice->id }}" @selected(old('source_invoice_id') == $invoice->id)>{{ $invoice->invoice_number }} — {{ \App\Support\Money::format($invoice->total) }} ({{ $invoice->issue_date->format('Y-m-d') }})</option>
                @endforeach
            </select>
            <p class="mt-1 text-[11px] text-slate-400">{{ __('The invoice this advance was actually paid under, if it\'s in the system — printed on future invoices as "Less: ... — [this number]".') }}</p>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Notes (optional)') }}</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-2">{{ __('Portions') }}</label>
            <p class="text-[11px] text-slate-400 mb-2">{{ __('Split the advance into the scopes of work it covers — e.g. "Subgrade advance", "Aggregate base advance", "Asphalt/MC1 advance" — each can be credited against a different future invoice.') }}</p>
            <table class="w-full text-sm" id="portions-table">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="py-1.5 font-medium">{{ __('Description') }}</th>
                        <th class="py-1.5 font-medium w-32 text-end">{{ __('Amount excl. VAT') }}</th>
                        <th class="py-1.5 font-medium w-24 text-end">{{ __('VAT %') }}</th>
                        <th class="py-1.5"></th>
                    </tr>
                </thead>
                <tbody id="portions-body">
                    <tr>
                        <td class="py-1 pe-2"><input type="text" name="portions[0][description]" required placeholder="{{ __('e.g. Subgrade advance') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
                        <td class="py-1 pe-2"><input type="number" step="0.01" min="0.01" name="portions[0][amount]" required class="w-full rounded-lg border border-slate-200 text-sm text-end focus:border-brand-500 focus:ring-brand-500"></td>
                        <td class="py-1 pe-2"><input type="number" step="0.01" min="0" max="100" name="portions[0][vat_rate]" value="15" required class="w-full rounded-lg border border-slate-200 text-sm text-end focus:border-brand-500 focus:ring-brand-500"></td>
                        <td class="py-1 text-end"><button type="button" onclick="removePortionRow(this)" class="text-xs text-slate-400 hover:text-red-600">{{ __('Remove') }}</button></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" onclick="addPortionRow()" class="mt-2 text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Add portion') }}</button>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Create advance credit') }}</button>
    </form>

    <script>
        let portionRowIndex = 1;

        function addPortionRow() {
            const tbody = document.getElementById('portions-body');
            const row = document.createElement('tr');
            const i = portionRowIndex++;
            row.innerHTML = `
                <td class="py-1 pe-2"><input type="text" name="portions[${i}][description]" required placeholder="{{ __('e.g. Asphalt/MC1 advance') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></td>
                <td class="py-1 pe-2"><input type="number" step="0.01" min="0.01" name="portions[${i}][amount]" required class="w-full rounded-lg border border-slate-200 text-sm text-end focus:border-brand-500 focus:ring-brand-500"></td>
                <td class="py-1 pe-2"><input type="number" step="0.01" min="0" max="100" name="portions[${i}][vat_rate]" value="15" required class="w-full rounded-lg border border-slate-200 text-sm text-end focus:border-brand-500 focus:ring-brand-500"></td>
                <td class="py-1 text-end"><button type="button" onclick="removePortionRow(this)" class="text-xs text-slate-400 hover:text-red-600">{{ __('Remove') }}</button></td>
            `;
            tbody.appendChild(row);
        }

        function removePortionRow(btn) {
            const tbody = document.getElementById('portions-body');
            if (tbody.querySelectorAll('tr').length > 1) {
                btn.closest('tr').remove();
            }
        }
    </script>
@else
    <div class="bg-white rounded-xl border border-dashed border-slate-200 p-10 text-center max-w-3xl">
        <p class="text-sm text-slate-400">{{ __('Select a client above to continue.') }}</p>
    </div>
@endif
@endsection
