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

@include('documents.print.partials.report.header', ['titleEn' => __('PROFIT & LOSS BY LOCATION'), 'titleAr' => 'الأرباح والخسائر حسب الموقع', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Location') }}</th>
            <th class="num">{{ __('Income') }}</th>
            <th class="num">{{ __('Expenses') }}</th>
            <th class="num">{{ __('Net') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['branch']?->name ?? __('Unassigned') }}</td>
                <td class="num">{{ number_format($row['income'], 2) }}</td>
                <td class="num">{{ number_format($row['expenses'], 2) }}</td>
                <td class="num">{{ number_format($row['net'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($totalIncome, 2) }}</td>
            <td class="num">{{ number_format($totalExpenses, 2) }}</td>
            <td class="num">{{ number_format($totalIncome - $totalExpenses, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
