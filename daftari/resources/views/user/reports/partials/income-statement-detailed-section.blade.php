{{--
    One Direct/Other/Indirect section of the Income Statement (Detailed)
    ledger table. Expects: $label (e.g. "Direct income"), $typeKey
    ("revenue" or "expense", picks the color), $categories (Collection
    of categoryLabel => ['rows' => Collection, 'total' => float]).
--}}
@php
    $typeStyles = [
        'revenue' => ['dot' => 'bg-emerald-600', 'accent' => 'border-emerald-600', 'head' => 'bg-emerald-50 text-emerald-800', 'soft' => 'bg-emerald-50/50', 'text' => 'text-emerald-700'],
        'expense' => ['dot' => 'bg-rose-600', 'accent' => 'border-rose-600', 'head' => 'bg-rose-50 text-rose-800', 'soft' => 'bg-rose-50/50', 'text' => 'text-rose-700'],
    ];
    $style = $typeStyles[$typeKey];
@endphp
<tr class="{{ $style['head'] }}">
    <td colspan="3" class="px-5 py-2.5 border-s-4 {{ $style['accent'] }} font-bold tracking-wide">
        <span class="inline-flex h-2 w-2 rounded-full {{ $style['dot'] }} me-2 align-middle"></span>{{ mb_strtoupper($label) }}
    </td>
</tr>
@foreach ($categories as $categoryLabel => $category)
    <tr>
        <td colspan="3" class="px-5 pt-2.5 pb-1 text-xs font-bold uppercase tracking-wide {{ $style['text'] }}">{{ $categoryLabel }}</td>
    </tr>
    @foreach ($category['rows'] as $row)
        <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
            <td class="px-5 py-2 text-slate-400 tabular-nums">{{ $row['account']->code }}</td>
            <td class="px-5 py-2 text-slate-700">{{ $row['account']->name }}</td>
            <td class="px-5 py-2 text-end tabular-nums">{{ number_format(abs($row['amount']), 2) }} {{ $row['account']->drCrSuffix($row['amount']) }}</td>
        </tr>
    @endforeach
    <tr class="border-b border-slate-100 font-semibold text-slate-600 {{ $style['soft'] }}">
        <td colspan="2" class="px-5 py-2">{{ __('Total of :category', ['category' => $categoryLabel]) }}</td>
        <td class="px-5 py-2 text-end tabular-nums">{{ number_format(abs($category['total']), 2) }}</td>
    </tr>
@endforeach
<tr class="border-b-2 border-slate-200 font-bold {{ $style['text'] }} {{ $style['soft'] }}">
    <td colspan="2" class="px-5 py-2.5">{{ __('Total of :section', ['section' => $label]) }}</td>
    <td class="px-5 py-2.5 text-end tabular-nums">{{ number_format(abs((float) $categories->sum('total')), 2) }}</td>
</tr>
