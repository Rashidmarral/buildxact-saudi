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

@include('documents.print.partials.report.header', ['titleEn' => __('INCOME STATEMENT (DETAILED)'), 'titleAr' => 'قائمة الدخل التفصيلية', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.section-title', ['en' => __('DIRECT INCOME'), 'ar' => 'الإيراد المباشر'])
@include('user.reports.pdf.partials.income-statement-category-section', ['sectionLabel' => __('Direct income'), 'categories' => $directIncome])

@include('documents.print.partials.report.section-title', ['en' => __('DIRECT EXPENSES'), 'ar' => 'المصاريف المباشرة'])
@include('user.reports.pdf.partials.income-statement-category-section', ['sectionLabel' => __('Direct expenses'), 'categories' => $directExpenses])

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => $grossProfit >= 0 ? __('GROSS PROFIT') : __('GROSS LOSS'),
    'labelAr' => 'إجمالي الربح',
    'value' => \App\Support\Money::format(abs($grossProfit)),
    'variant' => $grossProfit >= 0 ? 'good' : 'warn',
])

@include('documents.print.partials.report.section-title', ['en' => __('OTHER INCOME'), 'ar' => 'إيرادات أخرى'])
@include('user.reports.pdf.partials.income-statement-category-section', ['sectionLabel' => __('Other income'), 'categories' => $otherIncome])

@include('documents.print.partials.report.section-title', ['en' => __('INDIRECT EXPENSES'), 'ar' => 'المصاريف غير المباشرة'])
@include('user.reports.pdf.partials.income-statement-category-section', ['sectionLabel' => __('Indirect expenses'), 'categories' => $indirectExpenses])

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => $netProfit >= 0 ? __('NET PROFIT') : __('NET LOSS'),
    'labelAr' => 'صافي الربح',
    'value' => \App\Support\Money::format(abs($netProfit)),
    'variant' => $netProfit >= 0 ? 'good' : 'warn',
])

@if (($priorPeriodAdjustments ?? collect())->isNotEmpty())
    @include('documents.print.partials.report.section-title', ['en' => __('PRIOR-PERIOD ADJUSTMENTS INCLUDED ABOVE'), 'ar' => 'تعديلات من فترة سابقة مدرجة أعلاه'])
    <table class="data-table">
        <tbody>
            @foreach ($priorPeriodAdjustments as $row)
                <tr>
                    <td>{{ $row['label'] }} ({{ __('originally posted :date', ['date' => $row['originalDate']->format('d M Y')]) }})</td>
                    <td class="num">{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
