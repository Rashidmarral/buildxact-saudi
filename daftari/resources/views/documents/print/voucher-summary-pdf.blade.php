{{--
    "there is option to get 1 single payment voucher for whole amount" —
    bundles every voucher matching a filter (one supplier/client, an
    optional date range) into one branded PDF: each voucher listed with
    its own date/reference/amount, plus a grand total. Structurally
    identical to machinery-statement-pdf.blade.php's summary-boxes-then-
    table shape (reused, not redesigned). $type is 'payment' or 'receipt'.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $footerData = $embed($template->footer_path ?? null);
    $isPayment = $type === 'payment';
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: 9.5pt; }
    table { border-collapse: collapse; width: 100%; }
    .header-table td { vertical-align: top; }
    .company-name { font-size: 15pt; font-weight: bold; color: #0f172a; }
    .doc-title { font-size: 15pt; font-weight: bold; color: #0f766e; text-align: right; }
    .muted { color: #64748b; font-size: 9pt; }
    .party-box { margin-top: 14px; padding: 10px 0; border-top: 1pt solid #e2e8f0; border-bottom: 1pt solid #e2e8f0; }
    .party-box .k { color: #64748b; font-size: 8pt; }
    .party-box .v { font-weight: bold; color: #0f172a; font-size: 11pt; }
    .summary-table { margin-top: 16px; }
    .summary-table td { padding: 10px; border: 1pt solid #e2e8f0; text-align: center; }
    .summary-table .k { color: #64748b; font-size: 8pt; }
    .summary-table .v { font-size: 13pt; font-weight: bold; color: #0f172a; margin-top: 4px; }
    .ledger-table { margin-top: 14px; }
    .ledger-table th { background: #f1f5f9; color: #475569; font-size: 8.5pt; text-align: left; padding: 6px 8px; border-bottom: 1pt solid #cbd5e1; }
    .ledger-table td { padding: 6px 8px; border-bottom: 0.5pt solid #e2e8f0; font-size: 9pt; }
    .num { text-align: right; }
    .total-row td { font-weight: bold; background: #f8fafc; border-top: 1pt solid #cbd5e1; }
</style>
</head>
<body>

<table class="header-table" style="border-bottom: 1pt solid #0f172a; padding-bottom: 10px;">
    <tr>
        <td style="width: 60%;">
            @if ($logoData)<img src="{{ $logoData }}" style="height: 36px; margin-bottom: 6px;" alt="">@endif
            <div class="company-name">{{ $company->name }}</div>
            @if ($company->vat_number)<div class="muted">{{ __('VAT') }}: {{ $company->vat_number }}</div>@endif
        </td>
        <td style="width: 40%;">
            <div class="doc-title">{{ $isPayment ? __('Payment Summary') : __('Receipt Summary') }}</div>
            <div class="muted" style="text-align: right;">{{ __('Generated') }}: {{ \App\Support\PlatformFormat::date(now()) }}</div>
            @if ($from || $to)
                <div class="muted" style="text-align: right;">{{ __('Period') }}: {{ $from ?: __('Earliest') }} — {{ $to ?: __('Today') }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="party-box">
    <tr>
        <td>
            <div class="k">{{ $isPayment ? __('Paid to') : __('Received from') }}</div>
            <div class="v">{{ $party?->name ?? '—' }}</div>
            @if ($party?->vat_number)<div class="muted">{{ __('VAT') }}: {{ $party->vat_number }}</div>@endif
            @if ($party?->cr_number)<div class="muted">{{ __('CR') }}: {{ $party->cr_number }}</div>@endif
        </td>
    </tr>
</table>

<table class="summary-table">
    <tr>
        <td><div class="k">{{ __('Number of vouchers') }}</div><div class="v">{{ $vouchers->count() }}</div></td>
        <td><div class="k">{{ $isPayment ? __('Total paid') : __('Total received') }}</div><div class="v">{{ \App\Support\Money::format($total) }}</div></td>
    </tr>
</table>

<table class="ledger-table">
    <thead>
        <tr>
            <th style="width: 16%;">{{ __('Voucher no.') }}</th>
            <th style="width: 14%;">{{ __('Date') }}</th>
            <th style="width: 20%;">{{ __('Account') }}</th>
            <th style="width: 20%;">{{ __('Method') }}</th>
            <th style="width: 15%;">{{ __('Reference') }}</th>
            <th style="width: 15%;" class="num">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($vouchers as $voucher)
            <tr>
                <td>{{ $voucher->voucher_number }}</td>
                <td>{{ \App\Support\PlatformFormat::date($voucher->date) }}</td>
                <td>{{ $voucher->bankAccount?->name ?: '—' }}</td>
                <td>{{ $voucher->method ? ucfirst(str_replace('_', ' ', $voucher->method)) : '—' }}</td>
                <td>{{ $voucher->reference ?: '—' }}</td>
                <td class="num">{{ \App\Support\Money::format($voucher->amount) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted" style="text-align: center; padding: 16px;">{{ __('No vouchers match this filter.') }}</td></tr>
        @endforelse
        <tr class="total-row">
            <td colspan="5">{{ __('TOTAL') }}</td>
            <td class="num">{{ \App\Support\Money::format($total) }}</td>
        </tr>
    </tbody>
</table>

@if ($footerData)
    <img src="{{ $footerData }}" style="width: 100%; margin-top: 20px;" alt="">
@endif

</body>
</html>
