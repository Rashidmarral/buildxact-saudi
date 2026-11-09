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

@include('documents.print.partials.report.header', ['titleEn' => __('BANK RECONCILIATION SUMMARY'), 'titleAr' => 'ملخص التسوية البنكية', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Bank account') }}</th>
            <th>{{ __('Statement date') }}</th>
            <th class="num">{{ __('Statement balance') }}</th>
            <th class="num">{{ __('Book balance') }}</th>
            <th class="num">{{ __('Difference') }}</th>
            <th class="num">{{ __('Outstanding') }}</th>
            <th>{{ __('Status') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['bankAccount']?->name ?? '—' }}</td>
                <td>{{ $row['reconciliation']->statement_date->format('Y-m-d') }}</td>
                <td class="num">{{ number_format($row['statementBalance'], 2) }}</td>
                <td class="num">{{ number_format($row['bookBalance'], 2) }}</td>
                <td class="num">{{ number_format($row['difference'], 2) }}</td>
                <td class="num">{{ $row['outstandingCount'] }}</td>
                <td>{{ $row['reconciliation']->status === 'completed' ? __('Completed') : __('In progress') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
