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

@include('documents.print.partials.report.header', [
    'titleEn' => __('BUDGET VS ACTUAL'), 'titleAr' => 'الموازنة مقابل الفعلي',
    'subtitleEn' => $budget->name.' — '.(($isCustomRange ?? false) ? ($fromDate.' to '.$toDate) : (__('Fiscal year').' '.$budget->fiscal_year)),
    'template' => $template ?? null,
])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@php($totalVariance = $totalActual - $totalBudgeted)
@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total budgeted'), 'label_ar' => 'إجمالي الموازنة', 'value' => \App\Support\Money::format($totalBudgeted), 'variant' => 'neutral'],
    ['label_en' => __('Total actual'), 'label_ar' => 'إجمالي الفعلي', 'value' => \App\Support\Money::format($totalActual), 'variant' => 'neutral'],
    ['label_en' => __('Variance'), 'label_ar' => 'الفرق', 'value' => \App\Support\Money::format($totalVariance), 'variant' => $totalVariance > 0 ? 'bad' : 'good'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Code') }}</th>
            <th>{{ __('Account') }}</th>
            <th class="num">{{ __('Budgeted') }}</th>
            <th class="num">{{ __('Actual') }}</th>
            <th class="num">{{ __('Variance') }}</th>
            <th class="num">{{ __('% used') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['account']->code }}</td>
                <td>{{ $row['account']->name }}</td>
                <td class="num">{{ number_format($row['budgeted'], 2) }}</td>
                <td class="num">{{ number_format($row['actual'], 2) }}</td>
                <td class="num">{{ number_format($row['variance'], 2) }}</td>
                <td class="num">{{ $row['budgeted'] > 0 ? number_format(($row['actual'] / $row['budgeted']) * 100, 0).'%' : '—' }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($totalBudgeted, 2) }}</td>
            <td class="num">{{ number_format($totalActual, 2) }}</td>
            <td class="num">{{ number_format($totalVariance, 2) }}</td>
            <td class="num"></td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
