@extends('layouts.app')

@section('title', __('Zakat Estimate'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('Zakat Estimate') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Period ending') }} {{ $calculation->period_end_date->format('Y-m-d') }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('app.zakat.pdf', $calculation) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download PDF') }}</a>
        <form method="POST" action="{{ route('app.zakat.destroy', $calculation) }}" onsubmit="return confirm('{{ __('Delete this Zakat estimate?') }}')">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">{{ __('Delete') }}</button>
        </form>
    </div>
</div>

<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-6 max-w-2xl">
    {{ __('This is an estimate for internal planning only. Your official Zakat return must be filed and verified through ZATCA\'s own Zakat, Tax and Customs Authority portal — consult a qualified accountant before relying on this figure.') }}
</div>

<div class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6">
    <dl class="grid grid-cols-2 gap-y-3 text-sm">
        <dt class="text-slate-500">{{ __('Total equity') }}</dt>
        <dd class="text-right font-medium text-slate-900">SAR {{ number_format($calculation->equity_amount, 2) }}</dd>

        <dt class="text-slate-500">{{ __('Long-term liabilities') }}</dt>
        <dd class="text-right font-medium text-slate-900">+ SAR {{ number_format($calculation->long_term_liabilities, 2) }}</dd>

        <dt class="text-slate-500">{{ __('Net fixed assets') }}</dt>
        <dd class="text-right font-medium text-slate-900">- SAR {{ number_format($calculation->net_fixed_assets, 2) }}</dd>

        <dt class="text-slate-500">{{ __('Other deductions') }}</dt>
        <dd class="text-right font-medium text-slate-900">- SAR {{ number_format($calculation->other_deductions, 2) }}</dd>

        <dt class="font-semibold text-slate-900 pt-3 border-t border-slate-100 mt-1">{{ __('Zakat base') }}</dt>
        <dd class="text-right font-semibold text-slate-900 pt-3 border-t border-slate-100 mt-1">SAR {{ number_format($calculation->zakat_base, 2) }}</dd>

        <dt class="text-slate-500">{{ __('Rate') }}</dt>
        <dd class="text-right font-medium text-slate-900">{{ $calculation->rate_type === 'gregorian' ? __('2.5775% (Gregorian)') : __('2.5% (Hijri)') }}</dd>

        <dt class="font-bold text-brand-700 pt-3 border-t border-slate-100 mt-1">{{ __('Zakat due') }}</dt>
        <dd class="text-right font-bold text-brand-700 text-lg pt-3 border-t border-slate-100 mt-1">SAR {{ number_format($calculation->zakat_due, 2) }}</dd>
    </dl>

    @if ($calculation->notes)
        <div class="mt-5 pt-5 border-t border-slate-100">
            <p class="text-xs font-medium text-slate-500 mb-1">{{ __('Notes') }}</p>
            <p class="text-sm text-slate-700 whitespace-pre-line">{{ $calculation->notes }}</p>
        </div>
    @endif
</div>

<a href="{{ route('app.zakat.index') }}" class="mt-6 inline-block text-sm font-semibold text-brand-700 hover:underline">{{ __('Back to all estimates') }}</a>
@endsection
