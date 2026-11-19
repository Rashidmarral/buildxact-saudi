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

@include('documents.print.partials.report.header', ['titleEn' => __('PAYMENT MODE STATEMENT'), 'titleAr' => 'كشف حساب طرق الدفع', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Sale') }}</th>
            <th>{{ __('Payment mode') }}</th>
            <th class="num">{{ __('Amount') }}</th>
            <th>{{ __('Reference') }}</th>
            <th class="num">{{ __('Running total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['date']->format('Y-m-d H:i') }}</td>
                <td>{{ $row['saleNumber'] }}</td>
                <td>{{ $row['mode'] }}</td>
                <td class="num">{{ number_format($row['amount'], 2) }}</td>
                <td>{{ $row['reference'] }}</td>
                <td class="num">{{ number_format($row['runningTotal'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
