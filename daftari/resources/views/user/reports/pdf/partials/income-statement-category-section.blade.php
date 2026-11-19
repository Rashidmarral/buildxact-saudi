{{-- Expects: $sectionLabel, $categories (Collection of categoryLabel => ['rows' => Collection, 'total' => float]) --}}
<table class="data-table">
    <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Account') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
    <tbody>
        @foreach ($categories as $categoryLabel => $category)
            <tr class="total-row"><td colspan="3">{{ $categoryLabel }}</td></tr>
            @foreach ($category['rows'] as $row)
                <tr>
                    <td>{{ $row['account']->code }}</td>
                    <td>{{ $row['account']->name }}</td>
                    <td class="num">{{ number_format(abs($row['amount']), 2) }} {{ $row['account']->drCrSuffix($row['amount']) }}</td>
                </tr>
            @endforeach
            <tr class="total-row"><td colspan="2">{{ __('Subtotal') }}</td><td class="num">{{ number_format(abs($category['total']), 2) }}</td></tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">{{ mb_strtoupper(__('Total of :section', ['section' => $sectionLabel])) }}</td>
            <td class="num">{{ number_format(abs((float) $categories->sum('total')), 2) }}</td>
        </tr>
    </tbody>
</table>
