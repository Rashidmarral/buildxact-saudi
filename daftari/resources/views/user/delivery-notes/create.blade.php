@extends('layouts.app')

@section('title', __('Create Delivery Note'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Create Delivery Note') }}</h1>
    <div class="flex items-center gap-3">
        <a href="{{ route('app.delivery-notes.select-invoice') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Generate from an invoice instead') }}</a>
        <a href="{{ route('app.delivery-notes.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</div>

@if ($errors->any())
    <div class="mb-6 rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('app.delivery-notes.store') }}" class="space-y-6" x-data="deliveryNoteForm()">
    @csrf

    <div class="bg-white rounded-xl border border-slate-100 p-6 grid sm:grid-cols-4 gap-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Delivery note number') }}</label>
            <input type="text" value="{{ $nextNumberPreview }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Client') }}</label>
            <select name="client_id" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Select a client') }}</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->display_name ?? $client->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Issue date') }}</label>
            <input type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Driver (optional)') }}</label>
            <input type="text" name="driver_name" value="{{ old('driver_name') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-900">{{ __('Lines') }}</h2>
            <button type="button" @click="addRow()" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-brand-300">{{ __('+ Add line') }}</button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="py-2 pe-3 font-medium w-1/4">{{ __('Item (optional)') }}</th>
                        <th class="py-2 pe-3 font-medium w-1/3">{{ __('Description') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Unit') }}</th>
                        <th class="py-2 pe-3 font-medium w-24">{{ __('Quantity') }}</th>
                        <th class="py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, index) in rows" :key="row.key">
                        <tr class="border-b border-slate-50">
                            <td class="py-2 pe-3">
                                <select :name="`items[${index}][item_id]`" x-model="row.item_id" @change="onItemSelect(row)" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">{{ __('—') }}</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}" data-name="{{ $item->name }}" data-unit="{{ $item->base_unit_id }}">{{ $item->name }}@if ($item->sku) ({{ $item->sku }})@endif</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2 pe-3">
                                <input type="text" :name="`items[${index}][description]`" x-model="row.description" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </td>
                            <td class="py-2 pe-3">
                                <select :name="`items[${index}][unit_id]`" x-model="row.unit_id" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">{{ __('—') }}</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="py-2 pe-3">
                                <input type="number" step="0.01" min="0.01" :name="`items[${index}][quantity]`" x-model="row.quantity" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </td>
                            <td class="py-2 text-end">
                                <button type="button" @click="removeRow(index)" x-show="rows.length > 1" class="text-slate-400 hover:text-red-600">&times;</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Received by (optional)') }}</label>
        <input type="text" name="received_by_name" value="{{ old('received_by_name') }}" placeholder="{{ __('Name of the person who received the goods') }}" class="mt-1 w-full max-w-sm rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        <label class="block text-xs font-semibold uppercase text-slate-500 mt-4">{{ __('Notes') }}</label>
        <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
    </div>

    <button type="submit" class="w-full rounded-lg bg-brand-800 px-6 py-3 font-semibold text-white hover:bg-brand-900">{{ __('Issue delivery note') }}</button>
</form>

<script>
function deliveryNoteForm() {
    return {
        rows: [{ key: 0, item_id: '', description: '', unit_id: '', quantity: 1 }],
        nextKey: 1,
        addRow() {
            this.rows.push({ key: this.nextKey++, item_id: '', description: '', unit_id: '', quantity: 1 });
        },
        removeRow(index) {
            this.rows.splice(index, 1);
        },
        onItemSelect(row) {
            if (! row.item_id) return;
            const select = event.target;
            const option = select.options[select.selectedIndex];
            row.description = option.dataset.name || '';
            row.unit_id = option.dataset.unit || '';
        },
    };
}
</script>
@endsection
