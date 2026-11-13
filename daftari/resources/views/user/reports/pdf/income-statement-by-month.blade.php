{{--
    Profit & Loss by month — one column per month plus a total column
    makes this wider than a portrait page comfortably fits, so it opens
    on a landscape sheet (mPDF's own sheet-size pagebreak tag) rather
    than squeezing 13+ numeric columns into A4 portrait.
--}}
@php($logoData = $embed($company->logo_path ?? null))
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@include('documents.print.partials.report.styles')
.pl-month-table th, .pl-month-table td { font-size: 9px; padding: 3px 4px; }
</style>
</head>
<body>
<pagebreak sheet-size="A4-L" />

@include('documents.print.partials.report.header', ['titleEn' => __('PROFIT & LOSS BY MONTH'), 'titleAr' => 'الأرباح والخسائر الشهرية', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

<table class="data-table pl-month-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Account') }}</th>
            @foreach ($months as $month)
                <th class="num">{{ $month->format('M Y') }}</th>
            @endforeach
            <th class="num">{{ __('Total') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr><td colspan="{{ $months->count() + 2 }}"><strong>{{ __('Income') }}</strong></td></tr>
        @foreach ($revenueLines as $row)
            <tr>
                <td>{{ $row['account']->name }}</td>
                @foreach ($months as $month)
                    <td class="num">{{ number_format($row['monthly'][$month->format('Y-m')], 2) }}</td>
                @endforeach
                <td class="num">{{ number_format($row['total'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL INCOME') }}</td>
            @foreach ($months as $month)
                <td class="num">{{ number_format($monthlyIncome[$month->format('Y-m')], 2) }}</td>
            @endforeach
            <td class="num">{{ number_format($totalIncome, 2) }}</td>
        </tr>

        <tr><td colspan="{{ $months->count() + 2 }}"><strong>{{ __('Expenses') }}</strong></td></tr>
        @foreach ($expenseLines as $row)
            <tr>
                <td>{{ $row['account']->name }}</td>
                @foreach ($months as $month)
                    <td class="num">{{ number_format($row['monthly'][$month->format('Y-m')], 2) }}</td>
                @endforeach
                <td class="num">{{ number_format($row['total'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL EXPENSES') }}</td>
            @foreach ($months as $month)
                <td class="num">{{ number_format($monthlyExpenses[$month->format('Y-m')], 2) }}</td>
            @endforeach
            <td class="num">{{ number_format($totalExpenses, 2) }}</td>
        </tr>
        <tr class="total-row">
            <td>{{ __('NET PROFIT') }}</td>
            @foreach ($months as $month)
                <td class="num">{{ number_format($monthlyNetProfit[$month->format('Y-m')], 2) }}</td>
            @endforeach
            <td class="num">{{ number_format($netProfit, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
