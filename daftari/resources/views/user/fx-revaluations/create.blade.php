@extends('layouts.app')

@section('title', __('New FX Revaluation'))

@section('content')
<div class="max-w-4xl">
    <h1 class="text-xl font-bold text-slate-900 mb-1">{{ __('New FX Revaluation') }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ __('Revalues every open foreign-currency invoice and bill against a current rate you enter, and posts the unrealized gain or loss to your ledger. Posting a new revaluation automatically reverses the previous one — only one ever stands at a time.') }}</p>

    <form method="GET" action="{{ route('app.fx-revaluations.create') }}" class="flex items-end gap-3 mb-6">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Revalue open balances as of') }}</label>
            <input type="date" name="as_of" value="{{ $asOf->format('Y-m-d') }}" class="rounded-lg border border-slate-200 text-sm">
        </div>
        <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Refresh') }}</button>
    </form>

    @if (empty($groups))
        <div class="bg-white rounded-xl border border-slate-100 px-6 py-10 text-center text-sm text-slate-400">
            {{ __('Nothing to revalue — every open invoice and bill is in :currency.', ['currency' => $company->currency]) }}
        </div>
    @else
        <form method="POST" action="{{ route('app.fx-revaluations.store') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="as_of_date" value="{{ $asOf->format('Y-m-d') }}">

            @foreach ($groups as $currency => $group)
                @php
                    $docs = array_merge($group['invoices'], $group['bills']);
                    $defaultRate = $docs[0]['booked_rate'] ?? 1;
                @endphp
                <div class="bg-white rounded-xl border border-slate-100 overflow-hidden" data-fx-currency="{{ $currency }}">
                    <div class="flex items-center justify-between gap-4 px-6 py-4 border-b border-slate-100">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">{{ $currency }}</h2>
                            <p class="text-xs text-slate-400">{{ __(':count open document(s)', ['count' => count($docs)]) }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Revaluation rate (to :currency)', ['currency' => $company->currency]) }}</label>
                            <input type="number" step="0.000001" min="0.000001" name="rates[{{ $currency }}]" value="{{ old('rates.'.$currency, $defaultRate) }}" class="fx-rate-input w-40 rounded-lg border border-slate-200 text-sm" data-currency="{{ $currency }}">
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-slate-500 border-b border-slate-100">
                                    <th class="px-6 py-2 font-medium">{{ __('Document') }}</th>
                                    <th class="px-6 py-2 font-medium">{{ __('Party') }}</th>
                                    <th class="px-6 py-2 font-medium text-end">{{ __('Balance') }} ({{ $currency }})</th>
                                    <th class="px-6 py-2 font-medium text-end">{{ __('Booked rate') }}</th>
                                    <th class="px-6 py-2 font-medium text-end">{{ __('Booked value') }} ({{ $company->currency }})</th>
                                    <th class="px-6 py-2 font-medium text-end">{{ __('Revalued value') }} ({{ $company->currency }})</th>
                                    <th class="px-6 py-2 font-medium text-end">{{ __('Unrealized') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($docs as $doc)
                                    @php $bookedBase = round($doc['balance'] * $doc['booked_rate'], 2); @endphp
                                    <tr class="fx-doc-row border-b border-slate-50 last:border-0" data-balance="{{ $doc['balance'] }}" data-booked="{{ $bookedBase }}" data-type="{{ $doc['type'] }}">
                                        <td class="px-6 py-2 text-slate-800">
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500 me-1">{{ $doc['type'] === 'invoice' ? __('AR') : __('AP') }}</span>
                                            {{ $doc['number'] }}
                                        </td>
                                        <td class="px-6 py-2 text-slate-600">{{ $doc['party'] }}</td>
                                        <td class="px-6 py-2 text-end">{{ number_format($doc['balance'], 2) }}</td>
                                        <td class="px-6 py-2 text-end">{{ number_format($doc['booked_rate'], 6) }}</td>
                                        <td class="px-6 py-2 text-end">{{ number_format($bookedBase, 2) }}</td>
                                        <td class="px-6 py-2 text-end fx-revalued">{{ number_format($bookedBase, 2) }}</td>
                                        <td class="px-6 py-2 text-end fx-unrealized font-medium">0.00</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Notes (optional)') }}</label>
                    <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-200 text-sm">{{ old('notes') }}</textarea>
                </div>
                <div class="flex items-center justify-between">
                    <p class="text-sm text-slate-600">{{ __('Total unrealized gain/loss') }}: <span id="fx-total-unrealized" class="font-bold text-slate-900">0.00</span> {{ $company->currency }}</p>
                    <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Post revaluation') }}</button>
                </div>
            </div>
        </form>
    @endif
</div>

<script>
function fxRecalculate() {
    let grandTotal = 0;

    document.querySelectorAll('[data-fx-currency]').forEach((card) => {
        const rateInput = card.querySelector('.fx-rate-input');
        const rate = parseFloat(rateInput.value) || 0;

        card.querySelectorAll('.fx-doc-row').forEach((row) => {
            const balance = parseFloat(row.dataset.balance) || 0;
            const booked = parseFloat(row.dataset.booked) || 0;
            const revalued = Math.round(balance * rate * 100) / 100;
            // An AP balance that grows in base-currency terms is a loss to
            // the company (we now owe more), the mirror image of AR — flip
            // the sign here so this column always reads as the company's
            // own gain/loss, matching what actually posts to the ledger.
            const rawDelta = Math.round((revalued - booked) * 100) / 100;
            const unrealized = row.dataset.type === 'bill' ? -rawDelta : rawDelta;

            row.querySelector('.fx-revalued').textContent = revalued.toFixed(2);
            const unrealizedCell = row.querySelector('.fx-unrealized');
            unrealizedCell.textContent = unrealized.toFixed(2);
            unrealizedCell.classList.toggle('text-emerald-700', unrealized > 0);
            unrealizedCell.classList.toggle('text-red-600', unrealized < 0);

            grandTotal += unrealized;
        });
    });

    const totalEl = document.getElementById('fx-total-unrealized');
    if (totalEl) {
        totalEl.textContent = grandTotal.toFixed(2);
        totalEl.classList.toggle('text-emerald-700', grandTotal > 0);
        totalEl.classList.toggle('text-red-600', grandTotal < 0);
    }
}

document.querySelectorAll('.fx-rate-input').forEach((input) => input.addEventListener('input', fxRecalculate));
fxRecalculate();
</script>
@endsection
