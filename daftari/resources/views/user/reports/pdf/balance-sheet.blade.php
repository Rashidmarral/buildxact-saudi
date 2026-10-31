{{--
    Balance Sheet PDF — rebuilt on the shared, bilingual, branded
    report-PDF design and rendered via MpdfRenderer for correct Arabic
    shaping.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $balances = $totalAssets - ($totalLiabilities + $totalEquity);
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@include('documents.print.partials.report.styles')
</style>
</head>
<body>

@include('documents.print.partials.report.header', ['titleEn' => __('BALANCE SHEET'), 'titleAr' => 'قائمة المركز المالي', 'subtitleEn' => __('As of :date', ['date' => $asOf->format('d M Y')]), 'subtitleAr' => 'كما في '.$asOf->format('d M Y'), 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total assets'), 'label_ar' => 'إجمالي الأصول', 'value' => \App\Support\Money::format($totalAssets), 'variant' => 'neutral'],
    ['label_en' => __('Total liabilities'), 'label_ar' => 'إجمالي الخصوم', 'value' => \App\Support\Money::format($totalLiabilities), 'variant' => 'warn'],
    ['label_en' => __('Total equity'), 'label_ar' => 'إجمالي حقوق الملكية', 'value' => \App\Support\Money::format($totalEquity), 'variant' => 'good'],
]])

@include('documents.print.partials.report.section-title', ['en' => __('ASSETS'), 'ar' => 'الأصول'])
<table class="data-table">
    <tbody>
        @foreach ($assets as $row)
            <tr><td>{{ $row['account']->label() }}</td><td class="num">{{ number_format($row['balance'], 2) }}</td></tr>
        @endforeach
        <tr class="total-row"><td>{{ __('TOTAL ASSETS') }}</td><td class="num">{{ number_format($totalAssets, 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('LIABILITIES'), 'ar' => 'الخصوم'])
<table class="data-table">
    <tbody>
        @foreach ($liabilities as $row)
            <tr><td>{{ $row['account']->label() }}</td><td class="num">{{ number_format($row['balance'], 2) }}</td></tr>
        @endforeach
        <tr class="total-row"><td>{{ __('TOTAL LIABILITIES') }}</td><td class="num">{{ number_format($totalLiabilities, 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('EQUITY'), 'ar' => 'حقوق الملكية'])
<table class="data-table">
    <tbody>
        @foreach ($equity as $row)
            <tr><td>{{ $row['account']?->label() ?? $row['label'] }}</td><td class="num">{{ number_format($row['balance'], 2) }}</td></tr>
        @endforeach
        <tr class="total-row"><td>{{ __('TOTAL EQUITY') }}</td><td class="num">{{ number_format($totalEquity, 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('TOTAL LIABILITIES + EQUITY'),
    'labelAr' => 'إجمالي الخصوم وحقوق الملكية',
    'value' => \App\Support\Money::format($totalLiabilities + $totalEquity),
    'subEn' => abs($balances) < 0.01 ? __('Balances with total assets.') : __('Does not balance with total assets — review postings.'),
    'variant' => abs($balances) < 0.01 ? 'good' : 'warn',
])

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
