@php($logoData = $embed($company->logo_path ?? null))
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@include('documents.print.partials.report.styles')
</style>
</head>
<body>

@include('documents.print.partials.report.header', ['titleEn' => __('TRIAL BALANCE'), 'titleAr' => 'ميزان المراجعة', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total debit'), 'label_ar' => 'إجمالي المدين', 'value' => \App\Support\Money::format($grand['closingDebit']), 'variant' => 'neutral'],
    ['label_en' => __('Total credit'), 'label_ar' => 'إجمالي الدائن', 'value' => \App\Support\Money::format($grand['closingCredit']), 'variant' => 'neutral'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Category') }}</th>
            <th class="num">{{ __('Opening Dr') }}</th>
            <th class="num">{{ __('Opening Cr') }}</th>
            <th class="num">{{ __('Dr') }}</th>
            <th class="num">{{ __('Cr') }}</th>
            <th class="num">{{ __('Closing Dr') }}</th>
            <th class="num">{{ __('Closing Cr') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($types as $type)
            <tr class="total-row">
                <td colspan="7">{{ $type['label'] }}</td>
            </tr>
            @foreach ($type['categories'] as $categoryLabel => $category)
                <tr>
                    <td>{{ $categoryLabel }} ({{ $category['rows']->count() }})</td>
                    <td class="num">{{ $category['openingDebit'] > 0 ? number_format($category['openingDebit'], 2) : '' }}</td>
                    <td class="num">{{ $category['openingCredit'] > 0 ? number_format($category['openingCredit'], 2) : '' }}</td>
                    <td class="num">{{ $category['periodDebit'] > 0 ? number_format($category['periodDebit'], 2) : '' }}</td>
                    <td class="num">{{ $category['periodCredit'] > 0 ? number_format($category['periodCredit'], 2) : '' }}</td>
                    <td class="num">{{ $category['closingDebit'] > 0 ? number_format($category['closingDebit'], 2) : '' }}</td>
                    <td class="num">{{ $category['closingCredit'] > 0 ? number_format($category['closingCredit'], 2) : '' }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>{{ __('Total of :type', ['type' => $type['label']]) }}</td>
                <td class="num">{{ number_format($type['openingDebit'], 2) }}</td>
                <td class="num">{{ number_format($type['openingCredit'], 2) }}</td>
                <td class="num">{{ number_format($type['periodDebit'], 2) }}</td>
                <td class="num">{{ number_format($type['periodCredit'], 2) }}</td>
                <td class="num">{{ number_format($type['closingDebit'], 2) }}</td>
                <td class="num">{{ number_format($type['closingCredit'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('GRAND TOTAL') }}</td>
            <td class="num">{{ number_format($grand['openingDebit'], 2) }}</td>
            <td class="num">{{ number_format($grand['openingCredit'], 2) }}</td>
            <td class="num">{{ number_format($grand['periodDebit'], 2) }}</td>
            <td class="num">{{ number_format($grand['periodCredit'], 2) }}</td>
            <td class="num">{{ number_format($grand['closingDebit'], 2) }}</td>
            <td class="num">{{ number_format($grand['closingCredit'], 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
