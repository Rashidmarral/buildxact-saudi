{{--
    One-page bilingual "VAT Return Filing Summary" for a recorded
    VatReturnPeriod — a clean reference to hand an accountant or keep
    alongside the actual return filed on ZATCA's own portal. Built on
    the shared report-PDF design (documents.print.partials.report.*).
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $periodRange = ['from' => $period->period_start, 'to' => $period->period_end];
    $netBeforeCarryForward = (float) $period->output_tax - (float) $period->net_recoverable_input_tax;
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
    'titleEn' => __('VAT RETURN FILING SUMMARY'),
    'titleAr' => 'ملخص إقرار ضريبة القيمة المضافة',
    'template' => $template ?? null,
])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', [
    'period' => $periodRange,
    'labelEn' => __('TAX PERIOD'),
    'labelAr' => 'الفترة الضريبية',
])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Output tax (sales)'), 'label_ar' => 'ضريبة المخرجات', 'value' => \App\Support\Money::format($period->output_tax), 'variant' => 'neutral'],
    ['label_en' => __('Net recoverable input tax'), 'label_ar' => 'ضريبة المدخلات القابلة للاسترداد', 'value' => \App\Support\Money::format($period->net_recoverable_input_tax), 'variant' => 'good'],
    ['label_en' => __('Credit brought forward'), 'label_ar' => 'رصيد مرحّل من الفترة السابقة', 'value' => \App\Support\Money::format($period->credit_brought_forward), 'variant' => 'neutral'],
]])

@include('documents.print.partials.report.section-title', ['en' => __('INPUT TAX BREAKDOWN'), 'ar' => 'تفاصيل ضريبة المدخلات'])
<table class="data-table">
    <thead>
        <tr>
            <th>{{ __('Line') }}</th>
            <th class="num">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>{{ __('Input tax — purchases (Bills)') }}</td><td class="num">{{ \App\Support\Money::format($period->input_tax_purchases) }}</td></tr>
        <tr><td>{{ __('Input tax — expenses') }}</td><td class="num">{{ \App\Support\Money::format($period->expense_tax) }}</td></tr>
        <tr class="total-row"><td>{{ __('Net recoverable input tax') }}</td><td class="num">{{ \App\Support\Money::format($period->net_recoverable_input_tax) }}</td></tr>
        <tr><td>{{ __('Net tax position before carry-forward (output − net recoverable input)') }}</td><td class="num">{{ \App\Support\Money::format($netBeforeCarryForward) }}</td></tr>
        <tr><td>{{ __('Less: credit brought forward') }}</td><td class="num">({{ \App\Support\Money::format($period->credit_brought_forward) }})</td></tr>
    </tbody>
</table>

@if ($period->amount_payable > 0)
    @include('documents.print.partials.report.closing-banner', [
        'labelEn' => __('AMOUNT PAYABLE TO ZATCA'),
        'labelAr' => 'المبلغ المستحق لهيئة الزكاة والضريبة والجمارك',
        'value' => \App\Support\Money::format($period->amount_payable),
        'variant' => 'warn',
    ])
@else
    @include('documents.print.partials.report.closing-banner', [
        'labelEn' => __('CREDIT CARRIED FORWARD TO NEXT PERIOD'),
        'labelAr' => 'رصيد مرحّل إلى الفترة التالية',
        'value' => \App\Support\Money::format($period->credit_carried_forward),
        'variant' => 'good',
    ])
@endif

@if ($period->notes)
    @include('documents.print.partials.report.section-title', ['en' => __('NOTES'), 'ar' => 'ملاحظات'])
    <p style="font-size: 9pt; white-space: pre-line;">{{ $period->notes }}</p>
@endif

<table style="margin-top: 8px;">
    <tr><td class="footer-note">
        {{ __('This is a bookkeeping summary, not the filed return itself — the actual VAT return is submitted through ZATCA\'s own portal.') }}
        {{ __('Recorded by') }} {{ $period->creator?->name ?: '—' }} {{ __('on') }} {{ $period->created_at->format('d M Y') }}.
    </td></tr>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
