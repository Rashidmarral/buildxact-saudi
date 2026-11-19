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
    'titleEn' => $type === 'receivables' ? __('AGED RECEIVABLES (DETAILS)') : __('AGED PAYABLES (DETAILS)'),
    'titleAr' => $type === 'receivables' ? 'أعمار الذمم المدينة (تفصيلي)' : 'أعمار الذمم الدائنة (تفصيلي)',
    'subtitleEn' => __('As of :date', ['date' => now()->format('d M Y')]),
    'template' => $template ?? null,
])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Transaction Type') }}</th>
            <th>{{ $type === 'receivables' ? __('Invoice No.') : __('Reference No') }}</th>
            <th>{{ $type === 'receivables' ? __('Customer') : __('Supplier') }}</th>
            <th>{{ __('Due Date') }}</th>
            <th class="num">{{ __('Due') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['date']->format('Y-m-d') }}</td>
                <td>{{ $row['transactionType'] }}</td>
                <td>{{ $row['reference'] }}</td>
                <td>{{ $row['party'] }}</td>
                <td>{{ $row['dueDate']->format('Y-m-d') }}</td>
                <td class="num">{{ number_format($row['due'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="5">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($totalDue, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
