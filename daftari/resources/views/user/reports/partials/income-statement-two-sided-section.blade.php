{{--
    One type band (e.g. "DIRECT EXPENSES") for the two-sided Income
    Statement — category totals only, no individual ledger lines.
    Expects: $label, $categories (Collection of categoryLabel => [...]).
--}}
<tr class="bg-slate-100">
    <td colspan="2" class="px-5 py-2 text-xs font-bold uppercase tracking-wide text-slate-700">{{ $label }}</td>
</tr>
@forelse ($categories as $categoryLabel => $category)
    <tr class="border-b border-slate-50">
        <td class="px-5 py-2 text-slate-600">{{ $categoryLabel }}</td>
        <td class="px-5 py-2 text-end tabular-nums">{{ number_format(abs($category['total']), 2) }}</td>
    </tr>
@empty
    <tr><td colspan="2" class="px-5 py-3 text-center text-slate-400 text-xs">{{ __('None this period.') }}</td></tr>
@endforelse
