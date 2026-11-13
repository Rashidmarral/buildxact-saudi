{{--
    "companies can issue delivery note for the customers" — proof of
    delivery for goods shipped to a client, standalone or generated from
    an invoice. Deliberately its own bespoke template (not routed through
    the shared documents.print.pdf/body system every financial document
    uses): a delivery note carries no VAT, prices or totals — it's a
    quantities-shipped record, not a billing document — so the generic
    layout dispatcher's financial-document assumptions don't fit it. Its
    visual language still echoes "Distribution Classic"
    (InvoiceTemplatePresets) on purpose: the same dashed-border info grid
    and tan title box, reusing whatever accent color the company has
    already chosen company-wide, so a delivery note doesn't look like a
    stranger next to the invoice it shipped.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $accent = $template->accent_color ?? '#92400e';
    $note = $deliveryNote;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: 9.5pt; }
    table { border-collapse: collapse; width: 100%; }
    .ar { direction: rtl; text-align: right; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .muted { color: #64748b; }
    .company-name { font-size: 13pt; font-weight: bold; color: #0f172a; }
    .title-box { border: 0.75pt solid {{ $accent }}; background-color: #fef9ec; padding: 8px 20px; }
    .title-box .en { font-weight: 600; color: #334155; }
    .title-box .ar-title { font-weight: bold; color: #0f172a; }
    .info-grid { margin-top: 10px; border: 0.75pt dashed #94a3b8; }
    .info-grid td { padding: 4px 8px; font-size: 8.5pt; }
    .items-table { margin-top: 10px; border: 0.75pt solid #94a3b8; }
    .items-table th { background-color: #f1f5f9; color: #334155; padding: 4px; font-size: 8pt; border-right: 0.5pt solid #cbd5e1; }
    .items-table td { padding: 4px; border-right: 0.5pt solid #f1f5f9; border-top: 0.5pt solid #e2e8f0; }
    .sign-block { margin-top: 30px; font-size: 8pt; }
    .footer-note { margin-top: 18px; font-size: 8pt; color: #94a3b8; text-align: center; }
</style>
</head>
<body>

<table>
    <tr>
        <td style="width: 50%; vertical-align: top;">
            @if ($logoData)<img src="{{ $logoData }}" style="height: 60px; margin-bottom: 6px;" alt="">@endif
            <div class="company-name">{{ $company->name }}</div>
            @if ($company->address)<div class="muted">{{ $company->address }}</div>@endif
            @if ($company->phone)<div class="muted">{{ $company->phone }}</div>@endif
        </td>
        <td class="ar" style="width: 50%; vertical-align: top;">
            @if ($company->name_ar)<div class="company-name">{{ $company->name_ar }}</div>@endif
            @if ($company->address)<div class="muted">{{ $company->address }}</div>@endif
            @if ($company->phone)<div class="muted">{{ $company->phone }}</div>@endif
        </td>
    </tr>
</table>

<table style="margin-top: 14px;">
    <tr>
        <td style="width: 50%;">
            <table class="title-box">
                <tr><td class="text-center">
                    <div class="ar-title ar">مذكرة تسليم</div>
                    <div class="en">{{ __('Delivery Note') }}</div>
                </td></tr>
            </table>
        </td>
        <td class="text-end" style="width: 50%; vertical-align: middle; font-weight: bold; font-size: 10pt; color: #0f172a;">
            {{ __('No.') }} : {{ $note->delivery_note_number }}
        </td>
    </tr>
</table>

@php
    $infoRows = array_values(array_filter([
        [
            ['en' => 'Date', 'ar' => 'التاريخ', 'value' => \App\Support\PlatformFormat::date($note->issue_date)],
            ['en' => 'Status', 'ar' => 'الحالة', 'value' => $note->isIssued() ? __('Issued') : __('Cancelled')],
        ],
        [
            ['en' => 'Client', 'ar' => 'العميل', 'value' => $note->client->name.($note->client->name_ar ? ' / '.$note->client->name_ar : '')],
        ],
        $note->invoice ? [
            ['en' => 'Related invoice', 'ar' => 'الفاتورة المرتبطة', 'value' => $note->invoice->invoice_number],
        ] : null,
        ($note->driver_name || $note->vehicle_number) ? array_values(array_filter([
            $note->driver_name ? ['en' => 'Driver', 'ar' => 'السائق', 'value' => $note->driver_name] : null,
            $note->vehicle_number ? ['en' => 'Vehicle No.', 'ar' => 'رقم المركبة', 'value' => $note->vehicle_number] : null,
        ])) : null,
    ]));
@endphp
<table class="info-grid">
    @foreach ($infoRows as $i => $row)
        <tr style="{{ $i > 0 ? 'border-top: 0.5pt dashed #cbd5e1;' : '' }}">
            @foreach ($row as $j => $cell)
                <td style="{{ $j < count($row) - 1 ? 'border-right: 0.5pt dashed #cbd5e1;' : '' }}">
                    <span class="muted">{{ $cell['en'] }} :</span>
                    <strong>{{ $cell['value'] }}</strong>
                    <span class="ar muted">: {{ $cell['ar'] }}</span>
                </td>
            @endforeach
        </tr>
    @endforeach
</table>

<table class="items-table">
    <thead>
        <tr>
            <th style="width: 6%;">#</th>
            <th style="width: 16%;">{{ __('Item Code') }}</th>
            <th class="text-start">{{ __('Description') }}</th>
            <th style="width: 12%;">{{ __('Unit') }}</th>
            <th class="text-end" style="width: 14%;">{{ __('Quantity') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($note->items as $index => $line)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $line->item?->sku ?: '—' }}</td>
                <td>{{ $line->description }}</td>
                <td class="text-center">{{ ($line->unit?->symbol ?: $line->unit?->nameFor(app()->getLocale())) ?? $line->item?->unit ?? '—' }}</td>
                <td class="text-end">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if ($note->notes)
    <div style="margin-top: 10px; font-size: 8.5pt;"><strong>{{ __('Notes') }}:</strong> {{ $note->notes }}</div>
@endif

@if (! $note->isIssued() && $note->cancellation_reason)
    <div style="margin-top: 10px; font-size: 8.5pt; color: #b91c1c;"><strong>{{ __('Cancellation reason') }}:</strong> {{ $note->cancellation_reason }}</div>
@endif

<table class="sign-block">
    <tr>
        <td style="width: 50%; vertical-align: top;">
            <div style="font-weight: bold; color: #334155;">{{ __('Received By') }} :</div>
            <div style="margin-top: 10px; color: #64748b;">{{ __('Name') }} : {{ $note->received_by_name ?: '______________________' }}</div>
            <div style="margin-top: 10px; color: #64748b;">{{ __('Signature') }} : ______________________</div>
        </td>
        <td class="text-end" style="width: 50%; vertical-align: top;">
            <div style="font-weight: bold; color: #334155;">{{ __('Issued By') }} :</div>
            <div style="margin-top: 10px; color: #64748b;">{{ __('Name') }} : {{ $note->creator->name ?? '______________________' }}</div>
            <div style="margin-top: 10px; color: #64748b;">{{ __('Signature') }} : ______________________</div>
        </td>
    </tr>
</table>

<table style="margin-top: 18px;"><tr><td class="footer-note">
    {{ $company->name }}@if ($company->name_ar) — <span class="ar">{{ $company->name_ar }}</span>@endif &nbsp;·&nbsp; {{ $note->delivery_note_number }}
</td></tr></table>

</body>
</html>
