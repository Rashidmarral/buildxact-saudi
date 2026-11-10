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

@include('documents.print.partials.report.header', ['titleEn' => __('BALANCE SHEET (SUMMARY)'), 'titleAr' => 'قائمة المركز المالي الموجزة', 'subtitleEn' => __('As of :date', ['date' => $asOf->format('d M Y')]), 'subtitleAr' => 'كما في '.$asOf->format('d M Y'), 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total assets'), 'label_ar' => 'إجمالي الأصول', 'value' => \App\Support\Money::format($totalAssets), 'variant' => 'neutral'],
    ['label_en' => __('Total liabilities + equity'), 'label_ar' => 'إجمالي الخصوم وحقوق الملكية', 'value' => \App\Support\Money::format($totalLiabilities + $totalEquity), 'variant' => 'neutral'],
]])

@include('documents.print.partials.report.section-title', ['en' => __('ASSETS'), 'ar' => 'الأصول'])
<table class="data-table">
    <tbody>
        @foreach ($assetCategories as $catLabel => $category)
            <tr>
                <td>{{ $catLabel }}</td>
                <td class="num">{{ number_format(abs($category['total']), 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL ASSETS') }}</td>
            <td class="num">{{ number_format($totalAssets, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('LIABILITIES + EQUITY'), 'ar' => 'الخصوم وحقوق الملكية'])
<table class="data-table">
    <tbody>
        @foreach ($liabilityCategories as $catLabel => $category)
            <tr>
                <td>{{ $catLabel }}</td>
                <td class="num">{{ number_format(abs($category['total']), 2) }}</td>
            </tr>
        @endforeach
        @foreach ($equityCategories as $catLabel => $category)
            <tr>
                <td>{{ $catLabel }}</td>
                <td class="num">{{ number_format(abs($category['total']), 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL LIABILITIES + EQUITY') }}</td>
            <td class="num">{{ number_format($totalLiabilities + $totalEquity, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('BALANCE CHECK'),
    'labelAr' => 'فحص التوازن',
    'value' => $balanced ? __('Balanced') : __('Not balanced'),
    'subEn' => $balanced ? __('Assets equal liabilities plus equity.') : __('Does not balance with total assets — review postings.'),
    'variant' => $balanced ? 'good' : 'warn',
])

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
