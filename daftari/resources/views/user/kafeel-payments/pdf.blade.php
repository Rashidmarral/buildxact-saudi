{{--
    Kafeel Payments statement — a private per-company record, not a
    financial statement, so it deliberately skips any "balance"/GL
    language. Built on the shared report-PDF design.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $topCategories = $byCategory->take(3);
    $cards = [
        ['label_en' => __('Total this period'), 'label_ar' => 'الإجمالي لهذه الفترة', 'value' => \App\Support\Money::format($total), 'variant' => 'neutral'],
    ];
    foreach ($topCategories as $category => $amount) {
        $cards[] = [
            'label_en' => __(\App\Models\KafeelPayment::CATEGORIES[$category] ?? $category),
            'value' => \App\Support\Money::format($amount),
            'variant' => 'warn',
        ];
    }
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

@include('documents.print.partials.report.header', ['titleEn' => __('Kafeel Payments Statement'), 'titleAr' => 'كشف دفعات الكفيل', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => $cards])

@include('documents.print.partials.report.section-title', ['en' => __('PAYMENT DETAIL'), 'ar' => 'تفاصيل الدفعات'])
<table class="data-table">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Paid to') }}</th>
            <th>{{ __('Category') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="num">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($payments as $payment)
            <tr>
                <td>{{ $payment->payment_date->format('Y-m-d') }}</td>
                <td>{{ $payment->paid_to ?: '—' }}</td>
                <td>{{ $payment->categoryLabel() }}</td>
                <td>{{ $payment->description }}</td>
                <td class="num">{{ number_format((float) $payment->amount, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="4">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($payments->sum('amount'), 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('Total paid to Kafeel this period'),
    'labelAr' => 'إجمالي المدفوع للكفيل لهذه الفترة',
    'value' => \App\Support\Money::format($total),
    'variant' => 'warn',
])

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
