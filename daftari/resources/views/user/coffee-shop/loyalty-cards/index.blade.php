@extends('layouts.app')

@section('title', __('Loyalty Cards'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Loyalty Cards') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Prepaid card balances customers can top up and spend at checkout — the Coffee Shop module\'s take on a loyalty program.') }}</p>
    </div>
    <button type="button" onclick="document.getElementById('new-card-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New card') }}</button>
</div>

@if (session('status'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

@if ($cards->isEmpty())
    <p class="bg-white rounded-xl border border-slate-100 px-6 py-8 text-sm text-slate-500">{{ __('No loyalty cards yet.') }}</p>
@else
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-4 py-3 font-medium">{{ __('Card number') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Client') }}</th>
                    <th class="px-4 py-3 font-medium text-end">{{ __('Balance') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cards as $card)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-4 py-2.5 font-mono text-slate-800">{{ $card->card_number }}</td>
                        <td class="px-4 py-2.5">{{ $card->client?->name ?? __('Walk-in') }}</td>
                        <td class="px-4 py-2.5 text-end tabular-nums font-medium">{{ number_format($card->balance, 2) }}</td>
                        <td class="px-4 py-2.5">
                            @if ($card->is_active)
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('Available') }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500">{{ __('Reserved') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-end">
                            <button type="button" class="text-xs font-semibold text-brand-700 hover:underline"
                                data-top-up
                                data-url="{{ route('app.coffee-shop.loyalty-cards.top-up', $card) }}"
                                data-number="{{ $card->card_number }}"
                            >{{ __('Top up') }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<dialog id="new-card-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.coffee-shop.loyalty-cards.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('New card') }}</h3>
            <button type="button" onclick="document.getElementById('new-card-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Client (optional — leave blank for a walk-in)') }}</label>
            <select name="client_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('Walk-in') }}</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Initial top-up (optional)') }}</label>
            <input type="number" name="initial_top_up" min="0" step="0.01" value="0" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('new-card-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<dialog id="top-up-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-sm backdrop:bg-slate-900/40">
    <form method="POST" id="top-up-form" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Top up') }} <span id="top-up-card-number" class="font-mono text-slate-500"></span></h3>
            <button type="button" onclick="document.getElementById('top-up-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Amount') }}</label>
            <input type="number" name="amount" min="0.01" step="0.01" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('top-up-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('[data-top-up]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('top-up-form').action = btn.dataset.url;
        document.getElementById('top-up-card-number').textContent = btn.dataset.number;
        document.getElementById('top-up-modal').showModal();
    });
});
</script>
@endsection
