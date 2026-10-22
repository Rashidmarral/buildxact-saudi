{{--
    Shared bilingual PDF for both Customer Report and Supplier Report —
    ReportController::customers()/suppliers() pass titleEn/titleAr and
    partyLabelEn/partyLabelAr, everything else about the row shape is
    identical. Built on the shared report-PDF design.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@include('documents.print.partials.report.styles')
</style>
</head>
<body>

@include('documents.print.partials.report.header', ['titleEn' => $titleEn, 'titleAr' => $titleAr])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total outstanding'), 'label_ar' => 'إجمالي الرصيد المستحق', 'value' => \App\Support\Money::format($summary['totalOutstanding']), 'variant' => 'warn'],
    ['label_en' => __('Collected this period'), 'label_ar' => 'المحصّل هذه الفترة', 'value' => \App\Support\Money::format($summary['totalCollected']), 'variant' => 'good'],
    ['label_en' => __('Invoiced this period'), 'label_ar' => 'المفوتر هذه الفترة', 'value' => \App\Support\Money::format($summary['totalInvoiced']), 'variant' => 'neutral'],
]])

@include('documents.print.partials.report.section-title', ['en' => $partyLabelEn.' '.__('DETAIL'), 'ar' => $partyLabelAr])
<table class="data-table">
    <thead>
        <tr>
            <th>{{ $partyLabelEn }}</th>
            <th class="num">{{ __('Docs') }}</th>
            <th class="num">{{ __('Invoiced') }}</th>
            <th class="num">{{ __('VAT') }}</th>
            <th class="num">{{ __('Received') }}</th>
            <th class="num">{{ __('Outstanding') }}</th>
            <th>{{ __('Last') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['party']->display_name ?? $row['party']->name }}</td>
                <td class="num">{{ $row['count'] }}</td>
                <td class="num">{{ number_format($row['invoiced'], 2) }}</td>
                <td class="num">{{ number_format($row['vat'], 2) }}</td>
                <td class="num">{{ number_format($row['received'], 2) }}</td>
                <td class="num">{{ number_format($row['outstanding'], 2) }}</td>
                <td>{{ $row['lastDate']?->format('Y-m-d') ?? '—' }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL') }}</td>
            <td class="num">{{ $rows->sum('count') }}</td>
            <td class="num">{{ number_format($rows->sum('invoiced'), 2) }}</td>
            <td class="num">{{ number_format($rows->sum('vat'), 2) }}</td>
            <td class="num">{{ number_format($rows->sum('received'), 2) }}</td>
            <td class="num">{{ number_format($rows->sum('outstanding'), 2) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
