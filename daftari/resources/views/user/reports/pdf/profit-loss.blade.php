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

@include('documents.print.partials.report.header', ['titleEn' => __('PROFIT & LOSS'), 'titleAr' => 'الأرباح والخسائر', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total income'), 'label_ar' => 'إجمالي الدخل', 'value' => \App\Support\Money::format($totalIncome), 'variant' => 'good'],
    ['label_en' => __('Total expenses'), 'label_ar' => 'إجمالي المصروفات', 'value' => \App\Support\Money::format($totalExpenses), 'variant' => 'warn'],
    ['label_en' => __('Net profit'), 'label_ar' => 'صافي الربح', 'value' => \App\Support\Money::format($netProfit), 'variant' => $netProfit >= 0 ? 'good' : 'warn'],
]])

@include('documents.print.partials.report.section-title', ['en' => __('INCOME'), 'ar' => 'الدخل'])
<table class="data-table">
    <tbody>
        @foreach ($revenueLines as $row)
            <tr>
                <td>{{ $row['account']->name }}</td>
                <td class="num">{{ number_format($row['amount'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL INCOME') }}</td>
            <td class="num">{{ number_format($totalIncome, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('EXPENSES'), 'ar' => 'المصروفات'])
<table class="data-table">
    <tbody>
        @foreach ($expenseLines as $row)
            <tr>
                <td>{{ $row['account']->name }}</td>
                <td class="num">{{ number_format($row['amount'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL EXPENSES') }}</td>
            <td class="num">{{ number_format($totalExpenses, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('NET PROFIT'),
    'labelAr' => 'صافي الربح',
    'value' => \App\Support\Money::format($netProfit),
    'variant' => $netProfit >= 0 ? 'good' : 'warn',
])

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
