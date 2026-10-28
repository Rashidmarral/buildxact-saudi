{{--
    Aged Receivables/Payables PDF — always a live "as of today" snapshot
    (no period range), rebuilt on the shared report-PDF design.
--}}
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
    'titleEn' => $type === 'receivables' ? __('AGED RECEIVABLES') : __('AGED PAYABLES'),
    'titleAr' => $type === 'receivables' ? 'أعمار الذمم المدينة' : 'أعمار الذمم الدائنة',
    'subtitleEn' => __('As of :date', ['date' => now()->format('d M Y')]),
    'template' => $template ?? null,
])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Current'), 'label_ar' => 'جارٍ', 'value' => \App\Support\Money::format($totals['current']), 'variant' => 'good'],
    ['label_en' => __('1-90 days overdue'), 'label_ar' => 'متأخر 1-90 يوماً', 'value' => \App\Support\Money::format($totals['days_1_30'] + $totals['days_31_60'] + $totals['days_61_90']), 'variant' => 'warn'],
    ['label_en' => __('90+ days overdue'), 'label_ar' => 'متأخر أكثر من 90 يوماً', 'value' => \App\Support\Money::format($totals['days_over_90']), 'variant' => 'bad'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ $type === 'receivables' ? __('Customer') : __('Supplier') }}</th>
            <th class="num">{{ __('Current') }}</th>
            <th class="num">{{ __('1-30 days') }}</th>
            <th class="num">{{ __('31-60 days') }}</th>
            <th class="num">{{ __('61-90 days') }}</th>
            <th class="num">{{ __('90+ days') }}</th>
            <th class="num">{{ __('Total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['party']->name }}</td>
                <td class="num">{{ number_format($row['buckets']['current'], 2) }}</td>
                <td class="num">{{ number_format($row['buckets']['days_1_30'], 2) }}</td>
                <td class="num">{{ number_format($row['buckets']['days_31_60'], 2) }}</td>
                <td class="num">{{ number_format($row['buckets']['days_61_90'], 2) }}</td>
                <td class="num">{{ number_format($row['buckets']['days_over_90'], 2) }}</td>
                <td class="num">{{ number_format($row['total'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($totals['current'], 2) }}</td>
            <td class="num">{{ number_format($totals['days_1_30'], 2) }}</td>
            <td class="num">{{ number_format($totals['days_31_60'], 2) }}</td>
            <td class="num">{{ number_format($totals['days_61_90'], 2) }}</td>
            <td class="num">{{ number_format($totals['days_over_90'], 2) }}</td>
            <td class="num">{{ number_format($totals['total'], 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
