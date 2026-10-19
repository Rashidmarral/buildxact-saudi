@extends('layouts.app')

@section('title', __('Create Delivery Note'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Create Delivery Note') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Delivering against invoice :number for :client', ['number' => $invoice->invoice_number, 'client' => $invoice->client->name]) }}</p>
    </div>
    <a href="{{ route('app.delivery-notes.select-invoice') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
</div>

@if ($errors->any())
    <div class="mb-6 rounded-lg bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('app.delivery-notes.store') }}" class="space-y-6">
    @csrf
    <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">

    <div class="bg-white rounded-xl border border-slate-100 p-6 grid sm:grid-cols-4 gap-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Delivery note number') }}</label>
            <input type="text" value="{{ $nextNumberPreview }}" disabled class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Issue date') }}</label>
            <input type="date" name="issue_date" value="{{ old('issue_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Driver (optional)') }}</label>
            <input type="text" name="driver_name" value="{{ old('driver_name') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Vehicle No. (optional)') }}</label>
            <input type="text" name="vehicle_number" value="{{ old('vehicle_number') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="font-semibold text-slate-900 mb-4">{{ __('Lines to deliver') }}</h2>
        <p class="text-xs text-slate-400 mb-4">{{ __('Quantity defaults to what\'s still owed on each line. Lower it to ship a partial delivery — the rest stays available for a later delivery note.') }}</p>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="py-2 pe-3 font-medium w-2/5">{{ __('Description') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Invoiced') }}</th>
                        <th class="py-2 pe-3 font-medium">{{ __('Already delivered') }}</th>
                        <th class="py-2 font-medium w-28">{{ __('Deliver now') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php($row = 0)
                    @foreach ($invoice->items as $item)
                        @php($remaining = $item->remainingQuantity())
                        @if ($remaining > 0.004)
                            <tr class="border-b border-slate-50">
                                <td class="py-2 pe-3">
                                    <input type="hidden" name="items[{{ $row }}][invoice_item_id]" value="{{ $item->id }}">
                                    {{ $item->description }}
                                    @if ($item->item?->sku)<span class="block text-xs text-slate-400">{{ $item->item->sku }}</span>@endif
                                </td>
                                <td class="py-2 pe-3 text-slate-500">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                                <td class="py-2 pe-3 text-slate-500">{{ rtrim(rtrim(number_format($item->deliveredQuantity(), 2), '0'), '.') }}</td>
                                <td class="py-2">
                                    <input type="number" step="0.01" min="0" max="{{ $remaining }}" name="items[{{ $row }}][quantity]" value="{{ old('items.'.$row.'.quantity', $remaining) }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                </td>
                            </tr>
                            @php($row++)
                        @endif
                    @endforeach
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
@endsection
