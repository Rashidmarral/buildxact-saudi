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

@include('documents.print.partials.report.header', ['titleEn' => __('BALANCE SHEET (DETAILED)'), 'titleAr' => 'قائمة المركز المالي التفصيلية', 'subtitleEn' => __('As of :date', ['date' => $asOf->format('d M Y')]), 'subtitleAr' => 'كما في '.$asOf->format('d M Y'), 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total assets'), 'label_ar' => 'إجمالي الأصول', 'value' => \App\Support\Money::format($totalAssets), 'variant' => 'neutral'],
    ['label_en' => __('Total liabilities + equity'), 'label_ar' => 'إجمالي الخصوم وحقوق الملكية', 'value' => \App\Support\Money::format($totalLiabilities + $totalEquity), 'variant' => 'neutral'],
]])

@include('documents.print.partials.report.section-title', ['en' => __('ASSETS'), 'ar' => 'الأصول'])
<table class="data-table">
    <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Account') }}</th><th class="num">{{ __('Amount') }}</th></tr></thead>
    <tbody>
        @foreach ($assetCategories as $catLabel => $category)
            <tr class="total-row"><td colspan="3">{{ $catLabel }}</td></tr>
            @foreach ($category['rows'] as $row)
                <tr>
                    <td>{{ $row['account']?->code }}</td>
                    <td>{{ $row['account']?->name ?? $row['label'] }}</td>
                    <td class="num">{{ number_format(abs($row['balance']), 2) }} {{ $row['account']?->drCrSuffix($row['balance']) ?? ($row['balance'] >= 0 ? __('Cr') : __('Dr')) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">{{ __('Subtotal') }}</td>
                <td class="num">{{ number_format(abs($category['total']), 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">{{ __('TOTAL ASSETS') }}</td>
            <td class="num">{{ number_format($totalAssets, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('LIABILITIES'), 'ar' => 'الخصوم'])
<table class="data-table">
    <tbody>
        @foreach ($liabilityCategories as $catLabel => $category)
            <tr class="total-row"><td colspan="3">{{ $catLabel }}</td></tr>
            @foreach ($category['rows'] as $row)
                <tr>
                    <td>{{ $row['account']?->code }}</td>
                    <td>{{ $row['account']?->name }}</td>
                    <td class="num">{{ number_format(abs($row['balance']), 2) }} {{ $row['account']?->drCrSuffix($row['balance']) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">{{ __('Subtotal') }}</td>
                <td class="num">{{ number_format(abs($category['total']), 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">{{ __('TOTAL LIABILITIES') }}</td>
            <td class="num">{{ number_format($totalLiabilities, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('EQUITY'), 'ar' => 'حقوق الملكية'])
<table class="data-table">
    <tbody>
        @foreach ($equityCategories as $catLabel => $category)
            <tr class="total-row"><td colspan="3">{{ $catLabel }}</td></tr>
            @foreach ($category['rows'] as $row)
                <tr>
                    <td>{{ $row['account']?->code }}</td>
                    <td>{{ $row['account']?->name ?? $row['label'] }}</td>
                    <td class="num">{{ number_format(abs($row['balance']), 2) }} {{ $row['account']?->drCrSuffix($row['balance']) ?? ($row['balance'] >= 0 ? __('Cr') : __('Dr')) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">{{ __('Subtotal') }}</td>
                <td class="num">{{ number_format(abs($category['total']), 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">{{ __('TOTAL EQUITY') }}</td>
            <td class="num">{{ number_format($totalEquity, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('TOTAL LIABILITIES + EQUITY'),
    'labelAr' => 'إجمالي الخصوم وحقوق الملكية',
    'value' => \App\Support\Money::format($totalLiabilities + $totalEquity),
    'subEn' => $balanced ? __('Assets equal liabilities plus equity.') : __('Does not balance with total assets — review postings.'),
    'variant' => $balanced ? 'good' : 'warn',
])

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
