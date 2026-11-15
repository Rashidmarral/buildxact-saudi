{{--
    One type section (Assets / Capital / Liabilities) of the plain
    Balance Sheet's ledger table. Expects: $label, $typeKey ('asset',
    'equity' or 'liability' — picks the color), $rows (flat Collection
    of ['account' => ?Account, 'balance' => float, 'label' => ?string,
    'priorAmount' => ?float]), $hasPrior (bool).
--}}
@php
    $typeStyles = [
        'asset' => ['dot' => 'bg-blue-600', 'accent' => 'border-blue-600', 'head' => 'bg-blue-50 text-blue-800', 'soft' => 'bg-blue-50/50', 'text' => 'text-blue-700'],
        'liability' => ['dot' => 'bg-amber-500', 'accent' => 'border-amber-500', 'head' => 'bg-amber-50 text-amber-800', 'soft' => 'bg-amber-50/50', 'text' => 'text-amber-700'],
        'equity' => ['dot' => 'bg-violet-600', 'accent' => 'border-violet-600', 'head' => 'bg-violet-50 text-violet-800', 'soft' => 'bg-violet-50/50', 'text' => 'text-violet-700'],
    ];
    $style = $typeStyles[$typeKey];
    $colspan = $hasPrior ? 4 : 3;
    $categories = $rows->groupBy(fn ($r) => $r['account']?->category ?? ($r['label'] ?? __('Other')));
@endphp
<tr class="{{ $style['head'] }}">
    <td colspan="{{ $colspan }}" class="px-5 py-2.5 border-s-4 {{ $style['accent'] }} font-bold tracking-wide">
        <span class="inline-flex h-2 w-2 rounded-full {{ $style['dot'] }} me-2 align-middle"></span>{{ mb_strtoupper($label) }}
    </td>
</tr>
@forelse ($categories as $categoryLabel => $categoryRows)
    <tr>
        <td colspan="{{ $colspan }}" class="px-5 pt-2.5 pb-1 text-xs font-bold uppercase tracking-wide {{ $style['text'] }}">{{ $categoryLabel }}</td>
    </tr>
    @foreach ($categoryRows as $row)
        <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
            <td class="px-5 py-2 text-slate-400 tabular-nums">{{ $row['account']?->code }}</td>
            <td class="px-5 py-2 text-slate-700">{{ $row['account']?->name ?? $row['label'] }}</td>
            <td class="px-5 py-2 text-end tabular-nums">{{ number_format(abs($row['balance']), 2) }} {{ $row['account']?->drCrSuffix($row['balance']) ?? ($row['balance'] >= 0 ? __('Cr') : __('Dr')) }}</td>
            @if ($hasPrior)
                <td class="px-5 py-2 text-end tabular-nums text-slate-400">{{ number_format(abs($row['priorAmount'] ?? 0), 2) }}</td>
            @endif
        </tr>
    @endforeach
    <tr class="border-b border-slate-100 font-semibold text-slate-600 {{ $style['soft'] }}">
        <td colspan="2" class="px-5 py-2">{{ __('Total of :category', ['category' => $categoryLabel]) }}</td>
        <td class="px-5 py-2 text-end tabular-nums">{{ number_format(abs((float) $categoryRows->sum('balance')), 2) }}</td>
        @if ($hasPrior)
            <td class="px-5 py-2 text-end tabular-nums text-slate-400">{{ number_format(abs((float) $categoryRows->sum('priorAmount')), 2) }}</td>
        @endif
    </tr>
@empty
    <tr><td colspan="{{ $colspan }}" class="px-5 py-4 text-center text-slate-400 text-xs">{{ __('No activity yet.') }}</td></tr>
@endforelse
<tr class="border-b-2 border-slate-200 font-bold {{ $style['text'] }} {{ $style['soft'] }}">
    <td colspan="2" class="px-5 py-2.5">{{ __('Total of :section', ['section' => $label]) }}</td>
    <td class="px-5 py-2.5 text-end tabular-nums">{{ number_format(abs((float) $rows->sum('balance')), 2) }}</td>
    @if ($hasPrior)
        <td class="px-5 py-2.5 text-end tabular-nums text-slate-400">{{ number_format(abs((float) $rows->sum('priorAmount')), 2) }}</td>
    @endif
</tr>
