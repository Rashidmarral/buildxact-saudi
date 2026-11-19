{{-- Expects: $categoryLabel, $category (['rows' => Collection, 'total' => float]), $detailed (bool) --}}
@if ($detailed)
    <tr class="bg-slate-50/70">
        <td colspan="3" class="px-5 py-2 text-xs font-bold uppercase tracking-wide text-slate-600">{{ $categoryLabel }}</td>
    </tr>
    @foreach ($category['rows'] as $row)
        <tr class="border-b border-slate-50 hover:bg-slate-50/60 transition-colors">
            <td class="px-5 py-2 text-slate-400 tabular-nums">{{ $row['account']?->code }}</td>
            <td class="px-5 py-2 text-slate-600">{{ $row['account']?->name ?? $row['label'] }}</td>
            <td class="px-5 py-2 text-end tabular-nums">{{ number_format(abs($row['balance']), 2) }} {{ $row['account']?->drCrSuffix($row['balance']) ?? ($row['balance'] >= 0 ? __('Cr') : __('Dr')) }}</td>
        </tr>
    @endforeach
    <tr class="border-b border-slate-100 font-semibold text-slate-700">
        <td colspan="2" class="px-5 py-2">{{ __('Total') }}</td>
        <td class="px-5 py-2 text-end tabular-nums">{{ number_format(abs($category['total']), 2) }}</td>
    </tr>
@else
    <tr class="border-b border-slate-50">
        <td colspan="2" class="px-5 py-2 text-slate-600">{{ $categoryLabel }}</td>
        <td class="px-5 py-2 text-end tabular-nums">{{ number_format(abs($category['total']), 2) }}</td>
    </tr>
@endif
