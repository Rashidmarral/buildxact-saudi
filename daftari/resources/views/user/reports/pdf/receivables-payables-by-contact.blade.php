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
    'titleEn' => __('RECEIVABLES/PAYABLES BY CONTACT'), 'titleAr' => 'الذمم حسب جهة الاتصال',
    'subtitleEn' => $type === 'receivables' ? __('Accounts Receivable (A/R)') : __('Accounts Payable (A/P)'),
    'template' => $template ?? null,
])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ $type === 'receivables' ? __('Customer') : __('Supplier') }}</th>
            <th class="num">{{ __('Opening') }}</th>
            <th class="num">{{ __('Debit') }}</th>
            <th class="num">{{ __('Credit') }}</th>
            <th class="num">{{ __('Closing') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['party']->name }}</td>
                <td class="num">{{ number_format($row['opening'], 2) }}</td>
                <td class="num">{{ number_format($row['debit'], 2) }}</td>
                <td class="num">{{ number_format($row['credit'], 2) }}</td>
                <td class="num">{{ number_format($row['closing'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($totals['opening'], 2) }}</td>
            <td class="num">{{ number_format($totals['debit'], 2) }}</td>
            <td class="num">{{ number_format($totals['credit'], 2) }}</td>
            <td class="num">{{ number_format($totals['closing'], 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
