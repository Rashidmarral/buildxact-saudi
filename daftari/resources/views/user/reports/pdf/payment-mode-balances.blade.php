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

@include('documents.print.partials.report.header', ['titleEn' => __('PAYMENT MODE BALANCES'), 'titleAr' => 'أرصدة طرق الدفع', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Payment mode') }}</th>
            <th class="num">{{ __('Collected this period') }}</th>
            <th class="num">{{ __('Account balance') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['mode']->name }}</td>
                <td class="num">{{ number_format($row['periodTotal'], 2) }}</td>
                <td class="num">{{ $row['accountBalance'] === null ? '-' : number_format($row['accountBalance'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
