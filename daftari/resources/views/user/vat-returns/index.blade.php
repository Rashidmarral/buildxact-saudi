@extends('layouts.app')

@section('title', __('VAT Return Filings'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-900">{{ __('VAT Return Filings') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('One record per period filed with ZATCA, chained so a credit carried forward from one period becomes the next period\'s starting point automatically.') }}</p>
    </div>
    <a href="{{ route('app.reports.vat-returns.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Record a period') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 mb-6">
    <div class="px-6 py-3 border-b border-slate-100 flex items-center justify-between">
        <p class="text-sm font-semibold text-slate-900">{{ __('Filing status') }}</p>
        <p class="text-xs text-slate-400">{{ $frequency === 'monthly' ? __('Monthly filer') : __('Quarterly filer') }}</p>
    </div>
    <div class="divide-y divide-slate-50">
        @foreach ($statusRows as $row)
            @php($badge = match ($row['status']) {
                'filed' => ['label' => __('Filed'), 'class' => 'bg-emerald-50 text-emerald-700'],
                'overdue' => ['label' => __('Overdue'), 'class' => 'bg-red-50 text-red-700'],
                'due_soon' => ['label' => __('Due soon'), 'class' => 'bg-amber-50 text-amber-700'],
                default => ['label' => __('Upcoming'), 'class' => 'bg-slate-100 text-slate-500'],
            })
            <div class="px-6 py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-900">{{ $row['label_en'] }} <span class="text-slate-400">({{ $row['label_ar'] }})</span></p>
                    <p class="text-xs text-slate-400">{{ __('Due') }} {{ $row['due_date']->format('d M Y') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                    @if ($row['status'] === 'filed')
                        <a href="{{ route('app.reports.vat-returns.show', $row['recorded']) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('View') }}</a>
                    @else
                        <a href="{{ route('app.reports.vat-returns.create', ['year' => $row['start']->year, 'index' => $row['index']]) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Record this period') }}</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($periods->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No VAT return periods recorded yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Period') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Output tax') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Net recoverable input tax') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Credit brought forward') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Amount payable') }}</th>
                    <th class="px-6 py-3 font-medium text-right">{{ __('Credit carried forward') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($periods as $period)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('app.reports.vat-returns.show', $period) }}'">
                        <td class="px-6 py-3 font-medium text-brand-700">{{ $period->period_start->format('Y-m-d') }} — {{ $period->period_end->format('Y-m-d') }}</td>
                        <td class="px-6 py-3 text-right tabular-nums">{{ \App\Support\Money::format($period->output_tax) }}</td>
                        <td class="px-6 py-3 text-right tabular-nums">{{ \App\Support\Money::format($period->net_recoverable_input_tax) }}</td>
                        <td class="px-6 py-3 text-right tabular-nums">{{ \App\Support\Money::format($period->credit_brought_forward) }}</td>
                        <td class="px-6 py-3 text-right tabular-nums font-semibold {{ $period->amount_payable > 0 ? 'text-red-600' : 'text-slate-400' }}">{{ \App\Support\Money::format($period->amount_payable) }}</td>
                        <td class="px-6 py-3 text-right tabular-nums font-semibold {{ $period->credit_carried_forward > 0 ? 'text-emerald-600' : 'text-slate-400' }}">{{ \App\Support\Money::format($period->credit_carried_forward) }}</td>
                        <td class="px-6 py-3 text-right text-xs text-slate-400">{{ __('By') }} {{ $period->creator?->name ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">@include('partials.pagination', ['paginator' => $periods])</div>
@endsection
