{{--
    Server-generated PDF template, rendered through mPDF (see
    App\Services\MpdfRenderer). Deliberately a single, well-supported
    table/float layout rather than reusing documents.print.body's
    Tailwind flex/grid markup — mPDF's HTML renderer doesn't support
    flexbox or CSS grid, so that template can't be reused as-is here.
    The on-screen page and the browser's own Print/PDF button still use
    documents.print.body directly (a real browser renders it, so flex/
    grid work fine there); this template only serves the "Download PDF"
    / "Email PDF" actions, which need to work without any browser or
    Node.js involved.
--}}
@php
    $accent = $template->accent_color ?? '#0f766e';
    $bankAccounts = $doc['bank_accounts'] ?? (($doc['bank_account'] ?? null) ? collect([$doc['bank_account']]) : collect());
    $logoData = $embed($company->logo_path ?? null);
    $stampData = $embed($company->stamp_path ?? null);
    $letterheadData = $embed($template->letterhead_path ?? null);
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: 10pt; }
    table { border-collapse: collapse; width: 100%; }
    .accent-bar { background-color: {{ $accent }}; height: 5px; border-radius: 3px; margin-bottom: 14px; }
    .header-table td { vertical-align: top; }
    .company-name { font-size: 17pt; font-weight: bold; color: #0f172a; }
    .doc-title { font-size: 15pt; font-weight: bold; color: {{ $accent }}; text-align: right; }
    .muted { color: #64748b; font-size: 9pt; }
    .info-table td { border: 0.5pt solid #cbd5e1; padding: 6px 8px; font-size: 9.5pt; }
    .info-table .label { font-weight: bold; color: #334155; width: 18%; }
    .info-table .label-ar { font-weight: bold; color: #334155; width: 18%; text-align: right; direction: rtl; }
    .zatca-badge { display: inline-block; background-color: #d1fae5; color: #047857; font-size: 8pt; font-weight: bold; padding: 3px 10px; border-radius: 10px; margin-bottom: 4px; }
    .items-table th { background-color: #f1f5f9; border: 0.5pt solid #cbd5e1; padding: 6px; font-size: 8.5pt; text-align: left; }
    .items-table td { border: 0.5pt solid #e2e8f0; padding: 6px; font-size: 9pt; vertical-align: top; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .totals-table td { padding: 3px 0; font-size: 9.5pt; }
    .totals-table .total-row td { border-top: 1pt solid #0f172a; font-weight: bold; font-size: 11pt; padding-top: 6px; }
    .footer-note { margin-top: 18px; font-size: 8.5pt; color: #94a3b8; text-align: center; border-top: 0.5pt solid #e2e8f0; padding-top: 8px; }
    .ar { direction: rtl; text-align: right; }
</style>
</head>
<body>

@if ($letterheadData)
    <img src="{{ $letterheadData }}" style="width: 100%;" alt="">
@else
    <div class="accent-bar"></div>
    <table class="header-table">
        <tr>
            <td style="width: 65%;">
                @if ($logoData)
                    <img src="{{ $logoData }}" style="height: 42px; margin-bottom: 6px;" alt="">
                @endif
                <div class="company-name">{{ $company->name }}</div>
                @if ($company->name_ar)<div class="muted ar">{{ $company->name_ar }}</div>@endif
                @if ($company->vat_number)<div class="muted">{{ __('VAT') }}: {{ $company->vat_number }}</div>@endif
                @if ($company->address)<div class="muted">{{ $company->address }}</div>@endif
            </td>
            <td style="width: 35%;">
                <div class="doc-title">{{ $doc['type_label'] }}</div>
                <div class="doc-title ar" style="font-size: 11pt;">{{ $doc['type_label_ar'] ?? '' }}</div>
                <div class="muted text-end">{{ $doc['number'] }}</div>
            </td>
        </tr>
    </table>
@endif

<table class="info-table" style="margin-top: 14px;">
    <tr>
        <td class="label">{{ $doc['party_label'] }}</td>
        <td>
            {{ $doc['party']->name }}
            @if (!empty($doc['party']->name_ar))<div class="ar">{{ $doc['party']->name_ar }}</div>@endif
        </td>
        <td class="label-ar">{{ $doc['party_label_ar'] ?? __('Party') }}</td>
    </tr>
    @if (!empty($doc['party']->vat_number))
        <tr>
            <td class="label">{{ __('VAT number') }}</td>
            <td>{{ $doc['party']->vat_number }}</td>
            <td class="label-ar">رقم التسجيل الضريبي</td>
        </tr>
    @endif
    @if (method_exists($doc['party'], 'fullAddress') && $doc['party']->fullAddress())
        <tr>
            <td class="label">{{ __('Address') }}</td>
            <td>{{ $doc['party']->fullAddress() }}</td>
            <td class="label-ar">العنوان</td>
        </tr>
    @endif
    <tr>
        <td class="label">{{ $doc['date_label'] }}</td>
        <td>{{ $doc['date']->format('Y-m-d') }}@if (!empty($doc['date2'])) &nbsp;|&nbsp; {{ $doc['date2_label'] }}: {{ $doc['date2']->format('Y-m-d') }}@endif</td>
        <td class="label-ar">{{ $doc['date_label_ar'] ?? 'التاريخ' }}</td>
    </tr>
</table>

@if (!empty($doc['qr_code']))
    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 70%;"></td>
            <td style="width: 30%; text-align: right;">
                @if (!empty($doc['zatca_status']))
                    <div class="zatca-badge">{{ $doc['zatca_status'] === 'cleared' ? __('ZATCA Cleared') : __('ZATCA Reported') }}</div><br>
                @endif
                <img src="data:image/png;base64,{{ $doc['qr_code'] }}" style="width: 85px; height: 85px;" alt="">
                <div class="muted">{{ __('Scan to verify') }}</div>
            </td>
        </tr>
    </table>
@endif

<table class="items-table" style="margin-top: 14px;">
    <thead>
        <tr>
            <th style="width: 4%;">#</th>
            <th style="width: 32%;">{{ __('Description') }} / الوصف</th>
            <th class="text-end" style="width: 10%;">{{ __('Qty') }}</th>
            <th class="text-end" style="width: 13%;">{{ __('Price') }}</th>
            <th class="text-end" style="width: 15%;">{{ __('Taxable amount') }}</th>
            <th class="text-end" style="width: 13%;">{{ __('VAT') }}</th>
            <th class="text-end" style="width: 13%;">{{ __('Total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($doc['lines'] as $index => $line)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $line->description }}</td>
                <td class="text-end">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                <td class="text-end">{{ number_format($line->unit_price, 2) }}</td>
                <td class="text-end">{{ number_format($line->quantity * $line->unit_price, 2) }}</td>
                <td class="text-end">{{ number_format($line->vat_amount, 2) }} ({{ rtrim(rtrim(number_format($line->vat_rate, 2), '0'), '.') }}%)</td>
                <td class="text-end">{{ number_format($line->line_total, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table style="margin-top: 12px;">
    <tr>
        <td style="width: 55%; vertical-align: top;">
            @if ($bankAccounts->isNotEmpty())
                @php $ba = $bankAccounts->first(); @endphp
                <div style="font-size: 9pt;">
                    <div><strong>{{ __('Bank Name') }}:</strong> {{ $ba->bank_name ?: $ba->name }}</div>
                    @if ($ba->account_holder_name)<div><strong>{{ __('Account Name') }}:</strong> {{ $ba->account_holder_name }}</div>@endif
                    @if ($ba->account_number)<div><strong>{{ __('Account Number') }}:</strong> {{ $ba->account_number }}</div>@endif
                    @if ($ba->iban)<div><strong>{{ __('IBAN') }}:</strong> {{ $ba->iban }}</div>@endif
                </div>
            @endif
        </td>
        <td style="width: 45%; vertical-align: top;">
            <table class="totals-table">
                <tr><td>{{ __('Subtotal') }}</td><td class="text-end">SAR {{ number_format($doc['subtotal'], 2) }}</td></tr>
                @if (($doc['discount_total'] ?? 0) > 0)
                    <tr><td>{{ __('Discount') }}</td><td class="text-end">-SAR {{ number_format($doc['discount_total'], 2) }}</td></tr>
                @endif
                <tr><td>{{ __('Total VAT') }}</td><td class="text-end">SAR {{ number_format($doc['vat_total'], 2) }}</td></tr>
                <tr class="total-row"><td>{{ __('Total') }}</td><td class="text-end">SAR {{ number_format($doc['total'], 2) }}</td></tr>
                @foreach ($doc['extra_rows'] ?? [] as $row)
                    <tr><td>{{ $row['label'] }}</td><td class="text-end">SAR {{ number_format($row['value'], 2) }}</td></tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

@if (!empty($doc['notes']))
    <div style="margin-top: 14px; font-size: 9pt;">
        <strong>{{ __('Notes') }} / ملاحظات</strong>
        <div class="muted" style="margin-top: 2px;">{{ $doc['notes'] }}</div>
    </div>
@endif

@if ($template && $template->notesFor(app()->getLocale()))
    <div class="muted" style="margin-top: 6px; font-size: 8.5pt;">{{ $template->notesFor(app()->getLocale()) }}</div>
@endif

@if ($stampData)
    <table style="margin-top: 20px;">
        <tr>
            <td style="width: 70%;"></td>
            <td style="width: 30%; text-align: right;">
                <img src="{{ $stampData }}" style="width: 90px; height: 90px;" alt="">
            </td>
        </tr>
    </table>
@endif

<div class="footer-note">
    {{ $company->name }}@if ($company->name_ar) — {{ $company->name_ar }}@endif &nbsp;·&nbsp; {{ $doc['number'] }}
</div>

</body>
</html>
