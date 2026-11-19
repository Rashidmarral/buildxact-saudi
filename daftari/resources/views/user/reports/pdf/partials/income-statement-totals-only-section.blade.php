{{-- Expects: $sectionLabel, $categories (Collection of categoryLabel => ['total' => float]) --}}
<table class="data-table">
    <tbody>
        <tr class="total-row"><td colspan="2">{{ mb_strtoupper($sectionLabel) }}</td></tr>
        @foreach ($categories as $categoryLabel => $category)
            <tr>
                <td>{{ $categoryLabel }}</td>
                <td class="num">{{ number_format(abs($category['total']), 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
