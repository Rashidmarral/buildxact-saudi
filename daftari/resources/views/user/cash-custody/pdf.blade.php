{{--
    Custody Report PDF — all-time advanced/spent/returned/balance per
    custodian. Not a period statement (custody floats run open-ended
    until settled), so this uses an "as of" subtitle instead of the
    period banner, same approach as the Owner Reimbursements PDF.
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

@include('documents.print.partials.report.header', [
    'titleEn' => __('Custody Report'), 'titleAr' => 'تقرير العهدة',
    'subtitleEn' => __('As of :date', ['date' => \App\Support\PlatformFormat::date(now())]),
    'subtitleAr' => 'كما في '.now()->format('d M Y'),
    'template' => $template ?? null,
])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total advanced'), 'label_ar' => 'إجمالي السُلف', 'value' => \App\Support\Money::format($totals['advanced']), 'variant' => 'neutral'],
    ['label_en' => __('Total spent'), 'label_ar' => 'إجمالي المصروف', 'value' => \App\Support\Money::format($totals['spent']), 'variant' => 'neutral'],
    ['label_en' => __('Outstanding now'), 'label_ar' => 'المتبقي حالياً', 'value' => \App\Support\Money::format($totals['balance']), 'variant' => 'warn'],
]])

@include('documents.print.partials.report.section-title', ['en' => __('CUSTODIAN DETAIL'), 'ar' => 'تفاصيل العهدة'])
<table class="data-table">
    <thead>
        <tr>
            <th>{{ __('Held by') }}</th>
            <th class="num">{{ __('Advanced') }}</th>
            <th class="num">{{ __('Spent') }}</th>
            <th class="num">{{ __('Returned') }}</th>
            <th class="num">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['account']->personal_owner_name ?: $row['account']->name }}</td>
                <td class="num">{{ number_format($row['advanced'], 2) }}</td>
                <td class="num">{{ number_format($row['spent'], 2) }}</td>
                <td class="num">{{ number_format($row['returned'], 2) }}</td>
                <td class="num">{{ number_format($row['balance'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($totals['advanced'], 2) }}</td>
            <td class="num">{{ number_format($totals['spent'], 2) }}</td>
            <td class="num">{{ number_format($totals['returned'], 2) }}</td>
            <td class="num">{{ number_format($totals['balance'], 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('Total currently out with custodians'),
    'labelAr' => 'إجمالي المتبقي مع أصحاب العهدة',
    'value' => \App\Support\Money::format($totals['balance']),
    'variant' => 'warn',
])

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
