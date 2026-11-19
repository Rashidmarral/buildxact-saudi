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

@include('documents.print.partials.report.header', ['titleEn' => __('CASH FLOW STATEMENT'), 'titleAr' => 'قائمة التدفقات النقدية', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Net change in cash'), 'label_ar' => 'صافي التغير في النقدية', 'value' => \App\Support\Money::format($netChange), 'variant' => $netChange >= 0 ? 'good' : 'bad'],
    ['label_en' => __('Opening cash & bank'), 'label_ar' => 'النقدية الافتتاحية', 'value' => \App\Support\Money::format($openingCash), 'variant' => 'neutral'],
    ['label_en' => __('Closing cash & bank'), 'label_ar' => 'النقدية الختامية', 'value' => \App\Support\Money::format($closingCash), 'variant' => 'neutral'],
]])

@include('documents.print.partials.report.section-title', ['en' => __('OPERATING ACTIVITIES'), 'ar' => 'الأنشطة التشغيلية'])
<table class="data-table">
    <tbody>
        <tr><td>{{ __('Net income') }}</td><td class="num">{{ number_format($netIncome, 2) }}</td></tr>
        <tr><td>{{ __('Depreciation') }}</td><td class="num">{{ number_format($depreciation, 2) }}</td></tr>
        @foreach ($workingCapitalLines as $row)
            <tr><td>{{ $row['account']->name }}</td><td class="num">{{ number_format($row['cashEffect'], 2) }}</td></tr>
        @endforeach
        <tr class="total-row"><td>{{ __('NET CASH FROM OPERATING ACTIVITIES') }}</td><td class="num">{{ number_format($operatingTotal, 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('INVESTING ACTIVITIES'), 'ar' => 'الأنشطة الاستثمارية'])
<table class="data-table">
    <tbody>
        <tr><td>{{ __('Fixed asset acquisitions') }}</td><td class="num">{{ number_format(-$acquisitions, 2) }}</td></tr>
        <tr><td>{{ __('Fixed asset disposal proceeds') }}</td><td class="num">{{ number_format($disposalProceeds, 2) }}</td></tr>
        <tr class="total-row"><td>{{ __('NET CASH FROM INVESTING ACTIVITIES') }}</td><td class="num">{{ number_format($investingTotal, 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('FINANCING ACTIVITIES'), 'ar' => 'الأنشطة التمويلية'])
<table class="data-table">
    <tbody>
        @foreach ($equityLines as $row)
            <tr><td>{{ $row['account']->name }}</td><td class="num">{{ number_format($row['change'], 2) }}</td></tr>
        @endforeach
        <tr class="total-row"><td>{{ __('NET CASH FROM FINANCING ACTIVITIES') }}</td><td class="num">{{ number_format($financingTotal, 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('CLOSING CASH & BANK'),
    'labelAr' => 'النقدية الختامية',
    'subEn' => __('Opening :opening + net change :change', ['opening' => number_format($openingCash, 2), 'change' => number_format($netChange, 2)]),
    'value' => \App\Support\Money::format($closingCash),
    'variant' => 'good',
])

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
