@extends('layouts.app')

@section('title', __('New Zakat Estimate'))

@section('content')
<div class="max-w-2xl">
    <h1 class="text-xl font-bold text-slate-900 mb-1">{{ __('New Zakat Estimate') }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ __('Uses the standard net-invested-capital method (equity + long-term financing, minus net fixed assets and other deductions) x 2.5% / 2.5775% — the same simplified formula as the free Zakat calculator, but starting from your real posted equity instead of a manual figure.') }}</p>

    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-6">
        {{ __('This is an estimate for internal planning only. Your official Zakat return must be filed and verified through ZATCA\'s own Zakat, Tax and Customs Authority portal — consult a qualified accountant before relying on this figure.') }}
    </div>

    <form method="GET" action="{{ route('app.zakat.create') }}" class="flex items-end gap-3 mb-6">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Recalculate equity as of') }}</label>
            <input type="date" name="as_of" value="{{ $asOf->format('Y-m-d') }}" class="rounded-lg border border-slate-200 text-sm">
        </div>
        <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Refresh') }}</button>
    </form>

    <form method="POST" action="{{ route('app.zakat.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        @csrf
        <input type="hidden" name="period_end_date" value="{{ $asOf->format('Y-m-d') }}">

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Period ending') }}</label>
            <p class="text-sm font-semibold text-slate-900">{{ $asOf->format('Y-m-d') }}</p>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Total equity (from your chart of accounts)') }}</label>
            <p class="text-sm font-semibold text-slate-900">{{ number_format($equity, 2) }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ __('Locked to your posted ledger as of the date above — it cannot be typed over. Change the date and refresh to recalculate.') }}</p>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Long-term liabilities not financing fixed assets') }}</label>
            <input type="number" step="0.01" name="long_term_liabilities" value="{{ old('long_term_liabilities', 0) }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Net fixed assets (book value)') }}</label>
            <input type="number" step="0.01" name="net_fixed_assets" value="{{ old('net_fixed_assets', 0) }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Other deductions (investments in subsidiaries, Zakat already paid, ...)') }}</label>
            <input type="number" step="0.01" name="other_deductions" value="{{ old('other_deductions', 0) }}" class="w-full rounded-lg border border-slate-200 text-sm">
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Calendar basis') }}</label>
            <select name="rate_type" class="w-full rounded-lg border border-slate-200 text-sm">
                <option value="hijri">{{ __('Hijri (2.5%)') }}</option>
                <option value="gregorian">{{ __('Gregorian (2.5775%)') }}</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Notes (optional)') }}</label>
            <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-200 text-sm">{{ old('notes') }}</textarea>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Calculate') }}</button>
    </form>
</div>
@endsection
