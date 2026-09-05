@extends('layouts.app')

@section('title', __('Reorder suggestions'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Reorder Suggestions') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('Items that have dropped to or below their reorder point, ready to turn into a purchase order.') }}</p>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($suggestions->isEmpty())
        <p class="px-6 py-16 text-center text-sm text-slate-500">{{ __('Nothing needs reordering right now.') }}</p>
    @else
        <form method="POST" action="{{ route('app.reorder-suggestions.store') }}" class="p-6 space-y-4">
            @csrf

            @error('supplier_id')
                <p class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ $message }}</p>
            @enderror
            @error('items')
                <p class="text-xs text-red-600 bg-red-50 rounded-lg px-3 py-2">{{ $message }}</p>
            @enderror

            <div class="max-w-xs">
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Supplier') }}</label>
                <select name="supplier_id" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('Select a supplier') }}</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-3 pe-3 font-medium">
                                <input type="checkbox" id="select-all" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            </th>
                            <th class="py-3 pe-3 font-medium">{{ __('Item') }}</th>
                            <th class="py-3 pe-3 font-medium">{{ __('Warehouse') }}</th>
                            <th class="py-3 pe-3 font-medium">{{ __('On hand') }}</th>
                            <th class="py-3 pe-3 font-medium">{{ __('Reorder point') }}</th>
                            <th class="py-3 pe-3 font-medium">{{ __('Suggested quantity') }}</th>
                            <th class="py-3 font-medium">{{ __('Unit cost') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($suggestions as $i => $row)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="py-3 pe-3">
                                    <input type="checkbox" class="suggestion-check rounded border-slate-300 text-brand-600 focus:ring-brand-500" data-index="{{ $i }}" checked>
                                    <input type="hidden" name="items[{{ $i }}][item_id]" value="{{ $row['stock']->item_id }}">
                                </td>
                                <td class="py-3 pe-3 font-medium text-slate-800">{{ $row['stock']->item->name }}</td>
                                <td class="py-3 pe-3">{{ $row['stock']->warehouse->name }}</td>
                                <td class="py-3 pe-3">{{ rtrim(rtrim(number_format($row['stock']->quantity, 2), '0'), '.') }}</td>
                                <td class="py-3 pe-3">{{ rtrim(rtrim(number_format($row['stock']->item->reorder_point, 2), '0'), '.') }}</td>
                                <td class="py-3 pe-3">
                                    <input type="number" step="0.01" min="0.01" name="items[{{ $i }}][quantity]" value="{{ $row['suggested_quantity'] }}" class="w-24 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                </td>
                                <td class="py-3">
                                    <input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_price]" value="{{ $row['stock']->item->purchase_price ?? 0 }}" class="w-24 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="submit" class="rounded-lg bg-brand-800 px-5 py-2.5 font-semibold text-white hover:bg-brand-900">{{ __('Create purchase order from selected') }}</button>
        </form>
    @endif
</div>

<script>
(function () {
    const selectAll = document.getElementById('select-all');
    const checks = document.querySelectorAll('.suggestion-check');

    selectAll?.addEventListener('change', () => {
        checks.forEach(c => {
            c.checked = selectAll.checked;
            toggleRow(c);
        });
    });

    function toggleRow(checkbox) {
        const row = checkbox.closest('tr');
        row.querySelectorAll('input[type="number"], input[type="hidden"]').forEach(input => {
            input.disabled = ! checkbox.checked;
        });
    }

    checks.forEach(c => c.addEventListener('change', () => toggleRow(c)));
})();
</script>
@endsection
