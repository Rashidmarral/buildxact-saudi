{{--
    Account Statement PDF (customer or supplier) — a dedicated, bilingual,
    branded statement design (navy header band, summary cards, an aging
    summary, an "outstanding documents" open-item table, then the full
    running-balance transaction ledger, and a closing-balance banner),
    modeled on documents/print/client-statement-pdf.blade.php's own proven
    layout but shared across both customers and suppliers rather than
    customers only, and with its own aging summary that one doesn't have.

    Deliberately not built on the generic documents/print/partials/report
    partials used by every other report PDF (VAT, trial balance, ...) —
    a document a client/supplier actually receives deserves its own
    considered design, not the same plain internal-report shell every
    other PDF in this app shares.

    A company's own Invoice Templates design (document_type =
    'account_statement') can still swap the header band for a full-bleed
    letterhead image, and/or add a footer banner image — same mechanism
    every other document type offers, registered as mPDF's own repeating
    <htmlpageheader>/<htmlpagefooter> so either image repeats on every
    page a long statement spans.
--}}
@php
    $isCustomer = $type === 'customer';
    $logoData = $embed($company->logo_path ?? null);
    $letterheadData = $embed($template->letterhead_path ?? null);
    $footerData = $embed($template->footer_path ?? null);
    $layout = $template->layout ?? null;
    $accent = $template->accent_color ?? '#0f766e';
    $totalInvoiced = $totalInvoiced ?? 0.0;
    $totalReceived = $totalReceived ?? 0.0;
    $outstandingRows = $outstandingRows ?? collect();
    $agingBuckets = $agingBuckets ?? ['current' => 0.0, 'days_1_30' => 0.0, 'days_31_60' => 0.0, 'days_61_90' => 0.0, 'days_over_90' => 0.0];

    $partyLabelEn = $isCustomer ? __('Customer / Bill to') : __('Supplier / Pay to');
    $partyLabelAr = $isCustomer ? 'العميل' : 'المورد';
    $docWordEn = $isCustomer ? __('Invoice') : __('Bill');
    $docsWordEn = $isCustomer ? __('Invoices') : __('Bills');
    $invoicedLabelEn = $isCustomer ? __('Total invoiced') : __('Total billed');
    $receivedLabelEn = $isCustomer ? __('Total received') : __('Total paid');
    $dueLabelEn = $isCustomer ? __('Balance due') : __('Amount owed');
    $outstandingTitleEn = $isCustomer ? __('OUTSTANDING INVOICES') : __('OUTSTANDING BILLS');
    $outstandingTitleAr = $isCustomer ? 'الفواتير المستحقة' : 'الفواتير المستحقة للمورد';

    $agingTotal = array_sum($agingBuckets ?? []);
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
    .party-box { margin-top: 14px; padding: 10px 0; border-top: 1pt solid #e2e8f0; border-bottom: 1pt solid #e2e8f0; }
    .party-box .k { color: #64748b; font-size: 8pt; }
    .party-box .v { font-weight: bold; color: #0f172a; font-size: 10pt; }
    .summary-table { margin-top: 14px; }
    .summary-table td { width: 33.33%; padding: 12px; border: 1pt solid #e2e8f0; text-align: center; background: #f0fdfa; }
    .summary-table .k { color: #64748b; font-size: 7.5pt; text-transform: uppercase; }
    .summary-table .v { font-size: 13pt; font-weight: bold; margin-top: 4px; }
    .v-invoiced { color: #0f172a; }
    .v-received { color: #0f766e; }
    .v-due { color: #b45309; }
    .aging-table { margin-top: 10px; }
    .aging-table td { width: 20%; padding: 8px 6px; border: 1pt solid #e2e8f0; text-align: center; }
    .aging-table .k { color: #94a3b8; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.3px; }
    .aging-table .v { font-size: 10.5pt; font-weight: bold; margin-top: 3px; color: #0f172a; }
    .aging-table .current .v { color: #0f766e; }
    .aging-table .over90 .v { color: #b91c1c; }
    .period-banner { background: #0f172a; color: #ffffff; padding: 7px 12px; margin-top: 16px; font-size: 8.5pt; font-weight: bold; }
    .section-title { margin-top: 18px; padding-bottom: 4px; border-bottom: 1.5pt solid #0f172a; }
    .section-title .en { font-size: 11pt; font-weight: bold; color: #0f172a; }
    .section-title .ar { font-size: 10.5pt; font-weight: bold; color: #0f172a; }
    .data-table { margin-top: 8px; }
    .data-table th { background: #f1f5f9; color: #475569; font-size: 8pt; text-align: left; padding: 6px 8px; border-bottom: 1pt solid #cbd5e1; text-transform: uppercase; }
    .data-table td { padding: 6px 8px; border-bottom: 0.5pt solid #e2e8f0; font-size: 9pt; }
    .num { text-align: right; }
    .total-row td { font-weight: bold; background: #f8fafc; border-top: 1pt solid #cbd5e1; }
    .overdue-tag { color: #b91c1c; font-weight: bold; font-size: 7.5pt; }
    .closing-banner { margin-top: 16px; padding: 12px 16px; background: #fff7ed; border: 1pt solid #fed7aa; }
    .closing-banner .k { color: #9a3412; font-size: 9pt; font-weight: bold; }
    .closing-banner .sub { color: #c2703d; font-size: 7.5pt; margin-top: 2px; }
    .closing-banner .v { color: #9a3412; font-size: 15pt; font-weight: bold; }
    .footer-note { margin-top: 16px; color: #94a3b8; font-size: 7.5pt; border-top: 0.5pt solid #e2e8f0; padding-top: 8px; }
</style>
</head>
<body>

@if ($footerData)
    <htmlpagefooter name="statement-footer">
        <img src="{{ $footerData }}" style="width: 100%;" alt="">
    </htmlpagefooter>
    <sethtmlpagefooter name="statement-footer" page="ALL" />
@endif

@if ($layout === 'custom_letterhead' && $letterheadData)
    <htmlpageheader name="statement-letterhead">
        <img src="{{ $letterheadData }}" style="width: 100%;" alt="">
    </htmlpageheader>
    <sethtmlpageheader name="statement-letterhead" page="ALL" show-this-page="1" />
@else
    <table class="header-band">
        <tr>
            <td style="width: 60%;">
                @if ($logoData)<img src="{{ $logoData }}" style="height: 30px; margin-bottom: 8px;" alt="">@endif
                <div class="header-title">{{ __('ACCOUNT STATEMENT') }}</div>
                @if ($company->industry)<div class="header-sub">{{ strtoupper($company->industry) }} @if($company->city) | {{ strtoupper($company->city) }} @endif</div>@endif
            </td>
            <td style="width: 40%;" class="ar">
                <div class="header-title">كشف حساب</div>
                @if ($company->name_ar)<div class="header-company">{{ $company->name_ar }}</div>@endif
            </td>
        </tr>
    </table>
@endif

<table class="meta-table">
    <tr>
        <td style="width: 50%;">
            <div class="meta-k">{{ __('Statement date') }}</div>
            <div class="meta-v">{{ \App\Support\PlatformFormat::date(now()) }}</div>
        </td>
        <td style="width: 50%;">
            <div class="meta-k">{{ __('Statement period') }}</div>
            <div class="meta-v">{{ $period['from']->format('d M Y') }} &ndash; {{ $period['to']->format('d M Y') }}</div>
        </td>
    </tr>
</table>

<table class="party-box">
    <tr>
        <td style="width: 60%;">
            <div class="k">{{ $partyLabelEn }}</div>
            <div class="v">{{ $party->display_name ?? $party->name }}</div>
            @if ($party->vat_number)<div class="muted">{{ __('VAT No.') }} {{ $party->vat_number }}</div>@endif
        </td>
        <td style="width: 40%;" class="ar">
            <div class="k">{{ $partyLabelAr }}</div>
            @if ($party->name_ar)<div class="v">{{ $party->name_ar }}</div>@endif
        </td>
    </tr>
</table>

<table class="summary-table">
    <tr>
        <td><div class="k">{{ $invoicedLabelEn }}</div><div class="v v-invoiced">{{ \App\Support\Money::format($totalInvoiced) }}</div></td>
        <td><div class="k">{{ $receivedLabelEn }}</div><div class="v v-received">{{ \App\Support\Money::format($totalReceived) }}</div></td>
        <td><div class="k">{{ $dueLabelEn }}</div><div class="v v-due">{{ \App\Support\Money::format($closingBalance) }}</div></td>
    </tr>
</table>

@if ($agingTotal > 0.004)
    <table class="aging-table">
        <tr>
            <td class="current"><div class="k">{{ __('Current') }}</div><div class="v">{{ \App\Support\Money::format($agingBuckets['current']) }}</div></td>
            <td><div class="k">{{ __('1-30 days') }}</div><div class="v">{{ \App\Support\Money::format($agingBuckets['days_1_30']) }}</div></td>
            <td><div class="k">{{ __('31-60 days') }}</div><div class="v">{{ \App\Support\Money::format($agingBuckets['days_31_60']) }}</div></td>
            <td><div class="k">{{ __('61-90 days') }}</div><div class="v">{{ \App\Support\Money::format($agingBuckets['days_61_90']) }}</div></td>
            <td class="over90"><div class="k">{{ __('90+ days') }}</div><div class="v">{{ \App\Support\Money::format($agingBuckets['days_over_90']) }}</div></td>
        </tr>
    </table>
@endif

@if ($outstandingRows->isNotEmpty())
    <table class="section-title">
        <tr>
            <td style="width: 60%;" class="en">{{ $outstandingTitleEn }}</td>
            <td style="width: 40%;" class="ar">{{ $outstandingTitleAr }}</td>
        </tr>
    </table>
    <p class="muted" style="margin-top: 4px;">{{ __('Every :doc still carrying a balance, as of today — independent of the statement period above.', ['doc' => strtolower($docsWordEn)]) }}</p>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 20%;">{{ $docWordEn }} {{ __('No.') }}</th>
                <th style="width: 18%;">{{ __('Date') }}</th>
                <th style="width: 18%;">{{ __('Due date') }}</th>
                <th style="width: 22%;" class="num">{{ __('Total') }}</th>
                <th style="width: 22%;" class="num">{{ __('Balance due') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($outstandingRows as $row)
                @php $overdue = $row['due_date'] && $row['due_date']->lt(now()->startOfDay()); @endphp
                <tr>
                    <td>{{ $row['number'] }}</td>
                    <td>{{ $row['date']->format('d M Y') }}</td>
                    <td>
                        {{ $row['due_date']?->format('d M Y') ?? '—' }}
                        @if ($overdue)<br><span class="overdue-tag">{{ __('OVERDUE') }}</span>@endif
                    </td>
                    <td class="num">{{ \App\Support\Money::format($row['total']) }}</td>
                    <td class="num" style="font-weight: bold;">{{ \App\Support\Money::format($row['balance']) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4">{{ __('TOTAL OUTSTANDING') }}</td>
                <td class="num">{{ \App\Support\Money::format($outstandingRows->sum('balance')) }}</td>
            </tr>
        </tbody>
    </table>
@endif

@include('documents.print.partials.report.section-title', ['en' => __('TRANSACTION LEDGER'), 'ar' => 'سجل الحركات'])
<table class="data-table">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="num">{{ __('Debit') }}</th>
            <th class="num">{{ __('Credit') }}</th>
            <th class="num">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="4" class="muted" style="font-style: italic;">{{ __('Opening balance') }}</td>
            <td class="num" style="font-weight: bold;">{{ \App\Support\Money::format($openingBalance) }}</td>
        </tr>
        @foreach ($lines as $line)
            <tr>
                <td>{{ $line->date->format('Y-m-d') }}</td>
                <td>{{ $line->description }}</td>
                <td class="num">{{ $line->debit > 0 ? \App\Support\Money::format($line->debit) : '' }}</td>
                <td class="num">{{ $line->credit > 0 ? \App\Support\Money::format($line->credit) : '' }}</td>
                <td class="num" style="font-weight: bold;">{{ \App\Support\Money::format($line->balance) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="closing-banner">
    <tr>
        <td style="width: 60%;">
            <div class="k">{{ __('CLOSING BALANCE AS OF :date', ['date' => strtoupper(now()->format('d M Y'))]) }}</div>
            <div class="sub">{{ __(':invoiced_label :invoiced - :received_label :received', ['invoiced_label' => $invoicedLabelEn, 'invoiced' => number_format($totalInvoiced, 2), 'received_label' => $receivedLabelEn, 'received' => number_format($totalReceived, 2)]) }}</div>
        </td>
        <td style="width: 40%; text-align: right;">
            <div class="v">{{ \App\Support\Money::format($closingBalance) }}</div>
        </td>
    </tr>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
