{{--
    Bilingual, branded "Statement of Account" / كشف حساب العميل for one
    client — modeled directly on a real statement the company already
    hands to clients (navy header band, three colored summary cards, a
    period banner, Invoice Details + Payments Received + running-balance
    Account Ledger tables, and a closing-balance banner). Structurally
    similar to project-cash-flow-pdf.blade.php/machinery-statement-pdf.
    blade.php (reused conventions, not a new pattern), but natively
    bilingual per line rather than a separate EN/AR export.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $footerData = $embed($template->footer_path ?? null);
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: 9.5pt; }
    table { border-collapse: collapse; width: 100%; }
    .ar { direction: rtl; text-align: right; font-family: cairo, sans-serif; }
    .muted { color: #94a3b8; font-size: 8pt; }
    .header-band { background: #0f172a; color: #ffffff; padding: 16px 18px; }
    .header-band td { vertical-align: top; }
    .header-title { font-size: 18pt; font-weight: bold; }
    .header-sub { color: #cbd5e1; font-size: 8.5pt; margin-top: 2px; }
    .header-company { font-size: 10pt; color: #e2e8f0; margin-top: 6px; }
    .meta-table { margin-top: 14px; }
    .meta-table td { padding: 0 14px 0 0; }
    .meta-k { color: #94a3b8; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5px; }
    .meta-v { font-weight: bold; color: #0f172a; font-size: 9.5pt; margin-top: 2px; }
    .customer-box { margin-top: 14px; padding: 10px 0; border-top: 1pt solid #e2e8f0; border-bottom: 1pt solid #e2e8f0; }
    .customer-box .k { color: #64748b; font-size: 8pt; }
    .customer-box .v { font-weight: bold; color: #0f172a; font-size: 10pt; }
    .summary-table { margin-top: 14px; }
    .summary-table td { width: 33.33%; padding: 12px; border: 1pt solid #e2e8f0; text-align: center; background: #f0fdfa; }
    .summary-table .k { color: #64748b; font-size: 7.5pt; text-transform: uppercase; }
    .summary-table .v { font-size: 13pt; font-weight: bold; margin-top: 4px; }
    .v-invoiced { color: #0f172a; }
    .v-received { color: #0f766e; }
    .v-due { color: #b45309; }
    .period-banner { background: #0f172a; color: #ffffff; padding: 7px 12px; margin-top: 14px; font-size: 8.5pt; font-weight: bold; }
    .section-title { margin-top: 18px; padding-bottom: 4px; border-bottom: 1.5pt solid #0f172a; }
    .section-title .en { font-size: 11pt; font-weight: bold; color: #0f172a; }
    .section-title .ar { font-size: 10.5pt; font-weight: bold; color: #0f172a; }
    .data-table { margin-top: 8px; }
    .data-table th { background: #f1f5f9; color: #475569; font-size: 8pt; text-align: left; padding: 6px 8px; border-bottom: 1pt solid #cbd5e1; text-transform: uppercase; }
    .data-table td { padding: 6px 8px; border-bottom: 0.5pt solid #e2e8f0; font-size: 9pt; }
    .num { text-align: right; }
    .total-row td { font-weight: bold; background: #f8fafc; border-top: 1pt solid #cbd5e1; }
    .status-paid { color: #0f766e; font-weight: bold; }
    .status-unpaid { color: #b45309; font-weight: bold; }
    .status-overdue { color: #b91c1c; font-weight: bold; }
    .closing-banner { margin-top: 16px; padding: 12px 16px; background: #fff7ed; border: 1pt solid #fed7aa; }
    .closing-banner .k { color: #9a3412; font-size: 9pt; font-weight: bold; }
    .closing-banner .sub { color: #c2703d; font-size: 7.5pt; margin-top: 2px; }
    .closing-banner .v { color: #9a3412; font-size: 15pt; font-weight: bold; }
    .footer-note { margin-top: 16px; color: #94a3b8; font-size: 7.5pt; border-top: 0.5pt solid #e2e8f0; padding-top: 8px; }
</style>
</head>
<body>

<table class="header-band">
    <tr>
        <td style="width: 60%;">
            @if ($logoData)<img src="{{ $logoData }}" style="height: 30px; margin-bottom: 8px;" alt="">@endif
            <div class="header-title">{{ __('STATEMENT OF ACCOUNT') }}</div>
            @if ($company->industry)<div class="header-sub">{{ strtoupper($company->industry) }} @if($company->city) | {{ strtoupper($company->city) }} @endif</div>@endif
        </td>
        <td style="width: 40%;" class="ar">
            <div class="header-title">كشف حساب العميل</div>
            @if ($company->name_ar)<div class="header-company">{{ $company->name_ar }}</div>@endif
        </td>
    </tr>
</table>

<table class="meta-table">
    <tr>
        <td style="width: 34%;">
            <div class="meta-k">{{ __('Statement date') }}</div>
            <div class="meta-v">{{ \App\Support\PlatformFormat::date(now()) }}</div>
        </td>
        @if ($project)
            <td style="width: 33%;">
                <div class="meta-k">{{ __('Project') }}</div>
                <div class="meta-v">{{ $project->code }} {{ $project->name }}</div>
            </td>
        @endif
        @if ($poReference)
            <td style="width: 33%;">
                <div class="meta-k">{{ __('Purchase order') }}</div>
                <div class="meta-v">{{ $poReference }}</div>
            </td>
        @endif
    </tr>
</table>

<table class="customer-box">
    <tr>
        <td style="width: 60%;">
            <div class="k">{{ __('Customer / Bill to') }}</div>
            <div class="v">{{ $client->display_name }}</div>
            @if ($client->vat_number)<div class="muted">{{ __('Customer VAT No.') }} {{ $client->vat_number }}</div>@endif
            @if ($company->vat_number)<div class="muted">{{ __('Supplier VAT No.') }} {{ $company->vat_number }}</div>@endif
        </td>
        <td style="width: 40%;" class="ar">
            <div class="k">العميل</div>
            @if ($client->name_ar)<div class="v">{{ $client->name_ar }}</div>@endif
        </td>
    </tr>
</table>

<table class="summary-table">
    <tr>
        <td><div class="k">{{ __('Total invoiced') }}</div><div class="v v-invoiced">{{ \App\Support\Money::format($totalInvoiced) }}</div></td>
        <td><div class="k">{{ __('Total received') }}</div><div class="v v-received">{{ \App\Support\Money::format($totalReceived) }}</div></td>
        <td>
            <div class="k">{{ __('Balance due') }}</div>
            <div class="v v-due">{{ \App\Support\Money::format($closingBalance) }}</div>
            <div class="muted ar" style="margin-top: 2px;">الرصيد المتبقي المستحق</div>
        </td>
    </tr>
</table>

<table class="period-banner">
    <tr>
        <td style="width: 60%;">{{ __('STATEMENT PERIOD: FROM :from TO :to', ['from' => strtoupper($period['from']->format('F Y')), 'to' => strtoupper($period['to']->format('F Y'))]) }}</td>
        <td style="width: 40%;" class="ar">فترة الكشف: من {{ $period['from']->format('F Y') }} إلى {{ $period['to']->format('F Y') }}</td>
    </tr>
</table>

<table class="section-title">
    <tr>
        <td style="width: 60%;" class="en">{{ __('INVOICE DETAILS') }}</td>
        <td style="width: 40%;" class="ar">تفاصيل الفواتير</td>
    </tr>
</table>
<table class="data-table">
    <thead>
        <tr>
            <th style="width: 18%;">{{ __('Invoice no.') }}</th>
            <th style="width: 14%;">{{ __('Date') }}</th>
            <th style="width: 38%;">{{ __('Description') }}</th>
            <th style="width: 15%;">{{ __('Status') }}</th>
            <th style="width: 15%;" class="num">{{ __('Total') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($invoiceRows as $row)
            <tr>
                <td>{{ $row['number'] }}</td>
                <td>{{ $row['date']->format('d M Y') }}</td>
                <td>{{ $row['description'] ?: '—' }}</td>
                <td class="{{ $row['status_label'] === __('Paid') ? 'status-paid' : ($row['status_label'] === __('Overdue') ? 'status-overdue' : 'status-unpaid') }}">{{ strtoupper($row['status_label']) }}</td>
                <td class="num">{{ \App\Support\Money::format($row['total']) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted" style="text-align: center; padding: 14px;">{{ __('No invoices in this period.') }}</td></tr>
        @endforelse
        <tr class="total-row">
            <td colspan="4">{{ __('TOTAL INVOICED') }}</td>
            <td class="num">{{ \App\Support\Money::format($totalInvoiced) }}</td>
        </tr>
    </tbody>
</table>

<table class="section-title">
    <tr>
        <td style="width: 60%;" class="en">{{ __('PAYMENTS RECEIVED') }}</td>
        <td style="width: 40%;" class="ar">المدفوعات المستلمة</td>
    </tr>
</table>
<table class="data-table">
    <thead>
        <tr>
            <th style="width: 25%;">{{ __('Reference') }}</th>
            <th style="width: 18%;">{{ __('Payment date') }}</th>
            <th style="width: 22%;">{{ __('Invoice match') }}</th>
            <th style="width: 15%;">{{ __('Method') }}</th>
            <th style="width: 20%;" class="num">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($paymentRows as $row)
            <tr>
                <td>{{ $row['reference'] ?: '—' }}</td>
                <td>{{ $row['date']->format('d M Y') }}</td>
                <td>{{ $row['invoice_number'] }}</td>
                <td>{{ $row['method'] ? ucfirst(str_replace('_', ' ', $row['method'])) : '—' }}</td>
                <td class="num">{{ \App\Support\Money::format($row['amount']) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted" style="text-align: center; padding: 14px;">{{ __('No payments received in this period.') }}</td></tr>
        @endforelse
        <tr class="total-row">
            <td colspan="4">{{ __('TOTAL RECEIVED') }}</td>
            <td class="num">{{ \App\Support\Money::format($totalReceived) }}</td>
        </tr>
    </tbody>
</table>

<table class="section-title">
    <tr>
        <td style="width: 60%;" class="en">{{ __('ACCOUNT LEDGER - RUNNING BALANCE') }}</td>
        <td style="width: 40%;" class="ar">سجل الحساب والرصيد</td>
    </tr>
</table>
<table class="data-table">
    <thead>
        <tr>
            <th style="width: 16%;">{{ __('Date') }}</th>
            <th style="width: 20%;">{{ __('Reference') }}</th>
            <th style="width: 28%;">{{ __('Description') }}</th>
            <th style="width: 12%;" class="num">{{ __('Invoiced') }}</th>
            <th style="width: 12%;" class="num">{{ __('Paid') }}</th>
            <th style="width: 12%;" class="num">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="5" class="muted" style="font-style: italic;">{{ __('Opening balance') }}</td>
            <td class="num" style="font-weight: bold;">{{ \App\Support\Money::format($openingBalance) }}</td>
        </tr>
        @foreach ($ledgerRows as $row)
            <tr>
                <td>{{ $row['date']->format('d M Y') }}</td>
                <td>{{ $row['reference'] }}</td>
                <td>{{ $row['description'] }}</td>
                <td class="num">{{ $row['invoiced'] > 0 ? \App\Support\Money::format($row['invoiced']) : '' }}</td>
                <td class="num" style="color: #0f766e;">{{ $row['paid'] > 0 ? \App\Support\Money::format($row['paid']) : '' }}</td>
                <td class="num" style="font-weight: bold;">{{ \App\Support\Money::format($row['balance']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="closing-banner">
    <tr>
        <td style="width: 60%;">
            <div class="k">{{ __('CLOSING BALANCE DUE AS OF :date', ['date' => strtoupper(now()->format('d M Y'))]) }}</div>
            <div class="sub">{{ __('Invoiced :invoiced - Received :received', ['invoiced' => number_format($totalInvoiced, 2), 'received' => number_format($totalReceived, 2)]) }}</div>
        </td>
        <td style="width: 40%; text-align: right;">
            <div class="v">{{ \App\Support\Money::format($closingBalance) }}</div>
        </td>
    </tr>
</table>

<div class="footer-note">
    @if ($company->phone){{ __('Phone') }}: {{ $company->phone }}@endif
    @if ($requisitionReference) | {{ __('Requisition') }}: {{ $requisitionReference }}@endif
    &nbsp;&nbsp;{{ __('Statement period') }}: {{ $period['from']->format('F Y') }} {{ __('to') }} {{ $period['to']->format('F Y') }}
</div>

@if ($footerData)
    <img src="{{ $footerData }}" style="width: 100%; margin-top: 16px;" alt="">
@endif

</body>
</html>
