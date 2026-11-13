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

@include('documents.print.partials.report.header', ['titleEn' => __('CARD/POS SETTLEMENT'), 'titleAr' => 'تسوية نقاط البيع / البطاقات', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

<table class="data-table" style="margin-top: 16px;">
    <tbody>
        <tr><td>{{ __('Settlement date') }}</td><td class="num">{{ $settlement->settlement_date->format('Y-m-d') }}</td></tr>
        <tr><td>{{ __('Payment mode') }}</td><td class="num">{{ $settlement->paymentMode?->name ?? '—' }}</td></tr>
        <tr><td>{{ __('Deposited to') }}</td><td class="num">{{ $settlement->bankAccount->name }}</td></tr>
        <tr><td>{{ __('Gross amount') }}</td><td class="num">{{ number_format($settlement->gross_amount, 2) }}</td></tr>
        <tr><td>{{ __('Processing fee (MDR)') }}</td><td class="num">{{ number_format($settlement->fee_amount, 2) }}</td></tr>
        <tr><td>{{ __('VAT on fee') }}</td><td class="num">{{ number_format($settlement->fee_vat_amount, 2) }}</td></tr>
        <tr class="total-row"><td>{{ __('Net deposited') }}</td><td class="num">{{ number_format($settlement->net_amount, 2) }}</td></tr>
        @if ($settlement->reference)
            <tr><td>{{ __('Reference') }}</td><td class="num">{{ $settlement->reference }}</td></tr>
        @endif
        <tr><td>{{ __('Status') }}</td><td class="num">{{ ucfirst($settlement->status) }}</td></tr>
    </tbody>
</table>

@if ($settlement->notes)
    <p style="margin-top: 16px; font-size: 9pt; color: #64748b;">{{ $settlement->notes }}</p>
@endif

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
