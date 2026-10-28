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

@include('documents.print.partials.report.header', ['titleEn' => __('INCOME STATEMENT'), 'titleAr' => 'قائمة الدخل', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Net sales'), 'label_ar' => 'صافي المبيعات', 'value' => \App\Support\Money::format($netSales), 'variant' => 'neutral'],
    ['label_en' => __('Gross profit'), 'label_ar' => 'إجمالي الربح', 'value' => \App\Support\Money::format($grossProfit), 'variant' => 'good'],
    ['label_en' => __('Net profit'), 'label_ar' => 'صافي الربح', 'value' => \App\Support\Money::format($netProfit), 'variant' => $netProfit >= 0 ? 'good' : 'bad'],
]])

@include('documents.print.partials.report.section-title', ['en' => __('REVENUE'), 'ar' => 'الإيرادات'])
<table class="data-table">
    <tbody>
        @foreach ($revenueLines as $row)
            <tr><td>{{ $row['account']->name }}</td><td class="num">{{ number_format($row['amount'], 2) }}</td></tr>
        @endforeach
        <tr class="total-row"><td>{{ __('NET SALES') }}</td><td class="num">{{ number_format($netSales, 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('EXPENSES'), 'ar' => 'المصروفات'])
<table class="data-table">
    <tbody>
        @foreach ($expenseLines as $row)
            <tr><td>{{ $row['account']->name }}</td><td class="num">{{ number_format($row['amount'], 2) }}</td></tr>
        @endforeach
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('NET PROFIT'),
    'labelAr' => 'صافي الربح',
    'subEn' => __('Gross profit :gross — Operating profit :op', ['gross' => number_format($grossProfit, 2), 'op' => number_format($operatingProfit, 2)]),
    'value' => \App\Support\Money::format($netProfit),
    'variant' => $netProfit >= 0 ? 'good' : 'warn',
])

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
