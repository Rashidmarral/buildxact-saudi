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

@include('documents.print.partials.report.header', ['titleEn' => __('SALES REPORT'), 'titleAr' => 'تقرير المبيعات', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Invoices'), 'label_ar' => 'الفواتير', 'value' => $invoiceCount, 'variant' => 'neutral'],
    ['label_en' => __('Tax total'), 'label_ar' => 'إجمالي الضريبة', 'value' => \App\Support\Money::format($taxTotal), 'variant' => 'neutral'],
    ['label_en' => __('Total sales'), 'label_ar' => 'إجمالي المبيعات', 'value' => \App\Support\Money::format($totalSales), 'variant' => 'good'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Invoice') }}</th>
            <th>{{ __('Customer') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="num">{{ __('Total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($items as $row)
            <tr>
                <td>{{ $row->invoice->issue_date->format('Y-m-d') }}</td>
                <td>{{ $row->invoice->invoice_number }}</td>
                <td>{{ $row->invoice->client->name ?? '' }}</td>
                <td>{{ $row->line->description }}</td>
                <td class="num">{{ number_format($row->line->line_total, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="4">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($totalSales, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
