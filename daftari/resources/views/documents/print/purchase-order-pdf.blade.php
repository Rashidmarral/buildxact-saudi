{{--
    Dedicated Purchase Order PDF — modeled on a real construction-industry
    PO a user shared as a reference (a sub-vendor/main-vendor Purchase
    Order they'd received): logo + company block top-left, a centered
    bilingual title, a PO number/date box top-right, a bilingual
    greeting/reference block, the line-item table with totals appended as
    rows beneath it, a numbered Terms & Notes section (from the PO's own
    Notes field), three-column signature blocks with the company stamp,
    and a document-distribution footer note.

    Deliberately NOT part of the shared documents.print.pdf layout system
    (see InvoiceTemplate) — a Purchase Order is an internal procurement
    instruction with its own approval trail, shaped differently enough
    from a client-facing Invoice/Quotation that folding this into that
    system would leak PO-specific chrome (the signature blocks, the
    distribution footer) onto unrelated document types. This always
    renders for Purchase Orders regardless of which invoice template
    layout the company has picked for everything else — see the "one
    global layout" gallery feature; Purchase Orders are the one document
    type deliberately outside it, because this isn't a skin choice, it's
    a different document shape.

    See PurchaseOrderController::downloadPdf(), which also merges in any
    PDF attachments on the order (a sub-vendor's own quotation, required
    approval documents, ...) as trailing pages via MpdfRenderer::mergePdfs()
    — that's what "below page for client quote / required approval
    documents" in the original feature request maps to, since Daftari has
    no structured "received quotation" object to render from.
--}}
@php
    $company = $order->company;
    $supplier = $order->supplier;
    $logoData = $embed($company->logo_path ?? null);
    $stampData = $embed($company->stamp_path ?? null);
    $notesLines = $order->notes
        ? array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($order->notes)), fn ($line) => trim($line) !== ''))
        : [];
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: 10pt; }
    table { border-collapse: collapse; width: 100%; }
    .ar { direction: rtl; text-align: right; }
    .muted { color: #64748b; font-size: 9pt; }
    .text-end { text-align: right; }
    .box { border: 0.75pt solid #cbd5e1; }
    .cell { padding: 4px 7px; }
    .footer-note { margin-top: 14px; font-size: 8pt; color: #94a3b8; text-align: center; border-top: 0.5pt solid #e2e8f0; padding-top: 6px; }
</style>
</head>
<body>

<table>
    <tr>
        <td style="width: 38%; vertical-align: top;">
            @if ($logoData)
                <img src="{{ $logoData }}" style="height: 56px;" alt="">
            @endif
            <div style="font-size: 12pt; font-weight: bold; margin-top: 4px;">{{ $company->name }}</div>
            @if ($company->name_ar)<div class="ar" style="font-weight: bold;">{{ $company->name_ar }}</div>@endif
            @if ($company->address)<div class="muted">{{ $company->address }}</div>@endif
            @if ($company->phone)<div class="muted">{{ $company->phone }}</div>@endif
            @if ($company->vat_number)<div class="muted">{{ __('VAT number') }} {{ $company->vat_number }}</div>@endif
        </td>
        <td style="width: 34%; vertical-align: middle; text-align: center;">
            <div style="font-size: 15pt; font-weight: bold;">{{ __('Purchase Order') }}</div>
            <div class="ar" style="font-size: 13pt; font-weight: bold;">أمر شراء</div>
        </td>
        <td style="width: 28%; vertical-align: top;">
            <table class="box">
                <tr><td class="cell box" style="font-weight: bold;">{{ __('PO No.') }}</td><td class="cell box text-end">{{ $order->po_number }}</td></tr>
                <tr><td class="cell box" style="font-weight: bold;">{{ __('Date') }}</td><td class="cell box text-end">{{ \App\Support\PlatformFormat::date($order->order_date) }}</td></tr>
                @if ($order->expected_date)
                    <tr><td class="cell box" style="font-weight: bold;">{{ __('Expected') }}</td><td class="cell box text-end">{{ \App\Support\PlatformFormat::date($order->expected_date) }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>
<table style="margin-top: 6px;"><tr><td style="height: 2px; background-color: #0f172a;"></td></tr></table>

<table class="box" style="margin-top: 14px;">
    <tr>
        <td class="cell" style="width: 50%; vertical-align: top;">
            <div><strong>{{ __('Date') }}:</strong> {{ \App\Support\PlatformFormat::date($order->order_date) }}</div>
            @if ($order->project)<div><strong>{{ __('Project') }}:</strong> {{ $order->project->name }}</div>@endif
            <div style="margin-top: 6px;"><strong>{{ __('To') }}:</strong> {{ $supplier->name }}</div>
            <div class="muted">{{ __('Dear Sirs,') }}</div>
            <div style="margin-top: 6px;">
                @if ($order->quotation_reference)
                    {{ __('With reference to the quotation submitted by you numbered (:ref), we hope you secure the items listed below in accordance with the following terms and specifications:', ['ref' => $order->quotation_reference]) }}
                @else
                    {{ __('Please supply/execute the items listed below in accordance with the following terms and specifications:') }}
                @endif
            </div>
        </td>
        <td class="cell ar" style="width: 50%; vertical-align: top;">
            <div><strong>التاريخ:</strong> {{ \App\Support\PlatformFormat::date($order->order_date) }}</div>
            @if ($order->project)<div><strong>اسم المشروع:</strong> {{ $order->project->name_ar ?: $order->project->name }}</div>@endif
            <div style="margin-top: 6px;"><strong>السادة:</strong> {{ $supplier->name_ar ?: $supplier->name }} المحترمين</div>
            <div class="muted">السلام عليكم ورحمة الله وبركاته،</div>
            <div style="margin-top: 6px;">
                @if ($order->quotation_reference)
                    بالإشارة إلى عرض السعر المقدم من قبلكم بالرقم ({{ $order->quotation_reference }}) نأمل منكم تأمين الأصناف الواردة أدناه طبقاً للشروط والمواصفات التالية:
                @else
                    نأمل منكم تأمين/تنفيذ الأصناف الواردة أدناه طبقاً للشروط والمواصفات التالية:
                @endif
            </div>
        </td>
    </tr>
</table>

<table class="box" style="margin-top: 14px;">
    <thead>
        <tr style="background-color: #f1f5f9; font-size: 8.5pt;">
            <th class="cell box" style="width: 4%;">#</th>
            <th class="cell box">{{ __('Description') }}<br><span class="ar" style="font-weight: normal;">البيان</span></th>
            <th class="cell box text-end" style="width: 10%;">{{ __('Unit') }}<br><span class="ar" style="font-weight: normal;">الوحدة</span></th>
            <th class="cell box text-end" style="width: 10%;">{{ __('Qty') }}<br><span class="ar" style="font-weight: normal;">الكمية</span></th>
            <th class="cell box text-end" style="width: 12%;">{{ __('Unit price') }}<br><span class="ar" style="font-weight: normal;">سعر الوحدة</span></th>
            <th class="cell box text-end" style="width: 14%;">{{ __('Net total') }}<br><span class="ar" style="font-weight: normal;">صافي الإجمالي</span></th>
            <th class="cell box" style="width: 12%;">{{ __('Remarks') }}<br><span class="ar" style="font-weight: normal;">ملاحظات</span></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($doc['lines'] as $index => $line)
            <tr>
                <td class="cell box" style="color: #64748b;">{{ $index + 1 }}</td>
                <td class="cell box">
                    {{ $line->description }}
                    @if (!empty($line->name_ar))<div class="ar">{{ $line->name_ar }}</div>@endif
                </td>
                <td class="cell box text-end">{{ ($line->unit?->symbol ?: $line->unit?->nameFor(app()->getLocale())) ?? $line->item?->unit ?? '—' }}</td>
                <td class="cell box text-end">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                <td class="cell box text-end">{{ number_format($line->unit_price, 2) }}</td>
                <td class="cell box text-end" style="font-weight: bold;">{{ number_format($line->line_total, 2) }}</td>
                <td class="cell box"></td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td class="cell box" colspan="5" style="font-weight: bold;">{{ __('Total') }} <span class="ar">الاجمالي</span></td>
            <td class="cell box text-end" style="font-weight: bold;" colspan="2">{{ \App\Support\Money::format($doc['subtotal']) }}</td>
        </tr>
        @if (($doc['discount_total'] ?? 0) > 0)
            <tr>
                <td class="cell box" colspan="5">{{ __('Discount') }} <span class="ar">الخصم</span></td>
                <td class="cell box text-end" colspan="2">-{{ \App\Support\Money::format($doc['discount_total']) }}</td>
            </tr>
        @endif
        <tr>
            <td class="cell box" colspan="5">{{ __('Total without VAT') }} <span class="ar">الاجمالي غير شامل الضريبة</span></td>
            <td class="cell box text-end" colspan="2">{{ \App\Support\Money::format($doc['subtotal'] - ($doc['discount_total'] ?? 0)) }}</td>
        </tr>
        <tr>
            <td class="cell box" colspan="5">{{ __('VAT') }} <span class="ar">ضريبة القيمة المضافة</span></td>
            <td class="cell box text-end" colspan="2">{{ \App\Support\Money::format($doc['vat_total']) }}</td>
        </tr>
        <tr style="background-color: #f1f5f9;">
            <td class="cell box" colspan="5" style="font-weight: bold; font-size: 10.5pt;">{{ __('Total with VAT') }} <span class="ar">الاجمالي شامل الضريبة</span></td>
            <td class="cell box text-end" style="font-weight: bold; font-size: 10.5pt;" colspan="2">{{ \App\Support\Money::format($doc['total']) }}</td>
        </tr>
    </tfoot>
</table>

@if (count($notesLines) > 0)
    <table style="margin-top: 16px;">
        <tr><td style="font-weight: bold; font-size: 10.5pt;">{{ __('Terms & Notes') }} <span class="ar" style="font-weight: bold;">ملاحظات</span></td></tr>
    </table>
    <table style="margin-top: 4px; font-size: 9pt;">
        @foreach ($notesLines as $i => $line)
            <tr>
                <td style="padding: 2px 4px 2px 0; width: 18px; vertical-align: top;">{{ $i + 1 }}.</td>
                <td style="padding: 2px 0;">{{ $line }}</td>
            </tr>
        @endforeach
    </table>
@endif

<table style="margin-top: 30px;">
    <tr>
        <td style="width: 33%; text-align: center;">
            <div style="border-bottom: 0.75pt solid #94a3b8; height: 40px;"></div>
            <div class="muted" style="margin-top: 4px; font-weight: bold;">{{ __('Project Manager') }}</div>
            <div class="muted ar">مدير المشروع</div>
        </td>
        <td style="width: 33%; text-align: center;">
            <div style="border-bottom: 0.75pt solid #94a3b8; height: 40px;"></div>
            <div class="muted" style="margin-top: 4px; font-weight: bold;">{{ __('Department Manager') }}</div>
            <div class="muted ar">مدير الإدارة / القسم</div>
        </td>
        <td style="width: 33%; text-align: center;">
            @if ($stampData)
                <img src="{{ $stampData }}" style="width: 80px; height: 80px;" alt="">
            @else
                <div style="border-bottom: 0.75pt solid #94a3b8; height: 40px;"></div>
            @endif
            <div class="muted" style="margin-top: 4px; font-weight: bold;">{{ __('Financial Manager') }}</div>
            <div class="muted ar">المدير المالي</div>
        </td>
    </tr>
</table>

<div class="footer-note">
    {{ __('Original: Finance Department') }} — <span class="ar">الأصل: الإدارة المالية</span>
    &nbsp;·&nbsp;
    {{ __('Copy: Project Management') }} — <span class="ar">صورة: إدارة المشاريع</span>
    &nbsp;·&nbsp;
    {{ __('Copy: Site') }} — <span class="ar">صورة: الموقع</span>
</div>
<div class="footer-note" style="border-top: none; padding-top: 0;">
    {{ $company->name }} &nbsp;·&nbsp; {{ $order->po_number }}
</div>

</body>
</html>
