{{--
    mPDF-compatible voucher PDF (see documents.print.pdf's header comment
    for why this is a separate, table-based template rather than reusing
    voucher-body.blade.php's Tailwind flex/grid markup).
--}}
@php
    $isReceipt = $type === 'receipt';
    $logoData = $embed($company->logo_path ?? null);
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: 10pt; }
    table { border-collapse: collapse; width: 100%; }
    .header-table td { vertical-align: top; }
    .company-name { font-size: 15pt; font-weight: bold; color: #0f172a; }
    .doc-title { font-size: 15pt; font-weight: bold; color: #0f766e; text-align: right; }
    .muted { color: #64748b; font-size: 9pt; }
    .row-table td { padding: 6px 0; border-bottom: 0.5pt solid #e2e8f0; font-size: 10pt; }
    .row-table .k { color: #64748b; width: 35%; }
    .row-table .v { font-weight: bold; color: #0f172a; }
    .sign-table td { text-align: center; font-size: 9pt; color: #94a3b8; padding-top: 40px; border-top: 0.5pt solid #cbd5e1; }
</style>
</head>
<body>

<table class="header-table" style="border-bottom: 1pt solid #0f172a; padding-bottom: 10px;">
    <tr>
        <td style="width: 65%;">
            @if ($logoData)<img src="{{ $logoData }}" style="height: 36px; margin-bottom: 6px;" alt="">@endif
            <div class="company-name">{{ $company->name }}</div>
            @if ($company->vat_number)<div class="muted">{{ __('VAT') }}: {{ $company->vat_number }}</div>@endif
        </td>
        <td style="width: 35%;">
            <div class="doc-title">{{ $isReceipt ? __('Receipt Voucher') : __('Payment Voucher') }}</div>
            <div class="muted text-end" style="text-align: right;">{{ __('No.') }}: {{ $voucher->voucher_number }}</div>
            <div class="muted text-end" style="text-align: right;">{{ __('Date') }}: {{ \App\Support\PlatformFormat::date($voucher->date) }}</div>
        </td>
    </tr>
</table>

<table class="row-table" style="margin-top: 18px;">
    <tr>
        <td class="k">{{ $isReceipt ? __('Received from') : __('Paid to') }}</td>
        <td class="v">{{ $isReceipt ? $voucher->payer_name : $voucher->payee_name }}@if ($voucher->party_type !== 'manual') <span class="muted">({{ ucfirst($voucher->party_type) }})</span>@endif</td>
    </tr>
    @if ($voucher->party_vat_number)
        <tr><td class="k">{{ __('VAT number') }}</td><td class="v">{{ $voucher->party_vat_number }}</td></tr>
    @endif
    <tr><td class="k">{{ __('Amount') }}</td><td class="v" style="font-size: 12pt;">{{ \App\Support\Money::format($voucher->amount) }}</td></tr>
    <tr><td class="k">{{ __('Account') }}</td><td class="v">{{ $voucher->bankAccount->name }}</td></tr>
    @if ($voucher->counterAccount)
        <tr><td class="k">{{ __('Counter account') }}</td><td class="v">{{ $voucher->counterAccount->label() }}</td></tr>
    @endif
    <tr><td class="k">{{ __('Payment method') }}</td><td class="v">{{ ucfirst(str_replace('_', ' ', $voucher->method)) }}</td></tr>
    @if ($isReceipt && $voucher->invoice)
        <tr><td class="k">{{ __('Applied to invoice') }}</td><td class="v">{{ $voucher->invoice->invoice_number }}</td></tr>
    @endif
    @if (! $isReceipt && $voucher->bill)
        <tr><td class="k">{{ __('Applied to bill') }}</td><td class="v">{{ $voucher->bill->bill_number }}</td></tr>
    @endif
    @if (! $isReceipt && $voucher->expense)
        <tr><td class="k">{{ __('Related expense') }}</td><td class="v">{{ $voucher->expense->vendor_name }} — {{ $voucher->expense->description }}</td></tr>
    @endif
    @if ($voucher->reference)
        <tr><td class="k">{{ __('Reference') }}</td><td class="v">{{ $voucher->reference }}</td></tr>
    @endif
    @if ($voucher->notes)
        <tr><td class="k">{{ __('Notes') }}</td><td class="v">{{ $voucher->notes }}</td></tr>
    @endif
</table>

<table class="sign-table" style="margin-top: 60px;">
    <tr>
        <td style="width: 50%;">{{ $isReceipt ? __('Received by') : __('Paid by') }}</td>
        <td style="width: 50%;">{{ __('Authorized signature') }}</td>
    </tr>
</table>

</body>
</html>
