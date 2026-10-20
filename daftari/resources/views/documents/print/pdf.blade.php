{{--
    Server-generated PDF template, rendered through mPDF (see
    App\Services\MpdfRenderer). Mirrors documents.print.body's 5 layouts
    (bilingual_classic, custom_letterhead, minimal, bordered, boxed) so a
    downloaded PDF matches whichever layout the company has configured —
    but rebuilt entirely with tables/floats rather than body's Tailwind
    flex/grid markup, since mPDF's HTML renderer doesn't support either.
    The on-screen page and the browser's own Print/PDF button still use
    documents.print.body directly (a real browser renders it, so flex/
    grid work fine there); this template only serves the "Download PDF"
    / "Email PDF" actions, which need to work without any browser or
    Node.js involved.
--}}
@php
    $accent = $template->accent_color ?? '#0f766e';
    $totalsColor = $template ? $template->totalsColor() : $accent;
    $showVatColumn = $template->show_vat_column ?? true;
    $layout = $template->layout ?? 'minimal';
    // "Compact" (the default) is what lets a short 2-3 line invoice fit
    // on a single page — see PdfPageDensityTest. "Comfortable" restores
    // more breathing room between sections for companies that don't mind
    // spilling onto an extra page.
    $isCompact = ! $template || $template->isCompact();
    $sectionGap = $isCompact ? 10 : 18;
    $headerGap = $isCompact ? 10 : 16;
    $qrGap = $isCompact ? 4 : 20;
    $signatureGap = $isCompact ? 18 : 40;
    $footerGap = $isCompact ? 4 : 18;
    $notesGap = $isCompact ? 6 : 14;
    $showLogo = $template->show_logo ?? true;
    $tableHeaderColor = $template->table_header_color ?? null;
    $showUnitLabels = $template->show_unit_labels ?? true;
    $showPartyVatNumber = $template->show_party_vat_number ?? true;
    $showItemDescription = $template->show_item_description ?? true;
    $bankAccounts = $doc['bank_accounts'] ?? (($doc['bank_account'] ?? null) ? collect([$doc['bank_account']]) : collect());
    $logoData = $embed($company->logo_path ?? null);
    $stampData = $embed($company->stamp_path ?? null);
    // A company-chosen override (Settings -> stamp size) beats each
    // layout's own default below; null falls back to that default.
    $stampSizeOverride = $company->stamp_size ?: null;
    $letterheadData = $embed($template->letterhead_path ?? null);
    $footerData = $embed($template->footer_path ?? null);

    $languageMode = $template->language_mode ?? 'bilingual';
    $tableDirection = $template->table_direction ?? 'ltr';
    $showEn = $languageMode !== 'arabic_only';
    $showAr = $languageMode !== 'english_only';
    // Structural labels (Subtotal, VAT, Description, table headers, ...) —
    // as opposed to $primary/$secondary, which carry the document's own
    // bilingual data (party name, item description). This previously
    // always returned the plain English key outside arabic_only mode, so
    // every "bilingual" layout's static labels silently rendered English-
    // only — only the dynamic $primary/$secondary content was actually
    // bilingual. Now combines both languages on one line in bilingual
    // mode, falling back to English alone if a key has no ar.json entry.
    $lbl = function (string $key) use ($languageMode) {
        if ($languageMode === 'arabic_only') {
            return \Illuminate\Support\Facades\Lang::get($key, [], 'ar');
        }

        if ($languageMode === 'english_only') {
            return \Illuminate\Support\Facades\Lang::get($key, [], 'en');
        }

        $ar = \Illuminate\Support\Facades\Lang::get($key, [], 'ar');

        return $ar !== $key ? "{$key} / {$ar}" : $key;
    };
    $primary = fn (string $en, ?string $ar = null) => $languageMode === 'arabic_only' && $ar ? $ar : $en;
    $secondary = fn (?string $ar = null) => $showAr && $languageMode !== 'arabic_only' ? $ar : null;
    // $primary()/$secondary() split a name across a main line + a sub
    // line — exactly right where there's room for two lines (the default
    // minimal/bordered/boxed layout's own party block). The single-line
    // label/value rows inside retail_classic's and sage_classic's Seller/
    // Customer address boxes have no second line to put $secondary() in,
    // so a party with no English name_ar fallback silently printed
    // English-only there even in bilingual mode — this combines both
    // onto that one line instead, the same "English / Arabic" shape
    // $lbl() already gives every static label in those same boxes.
    $bilingualName = fn (string $en, ?string $ar = null) => $ar && $showAr && $languageMode !== 'arabic_only' ? "{$en} / {$ar}" : $primary($en, $ar);
    // ZATCA's cbc:IssueTime (and the QR code built from it) already
    // carries the real issue time — invoices.issue_date itself is a
    // date-only column with nothing to show — but no printed layout
    // ever displayed it, leaving the buyer's copy silently missing the
    // one field a ZATCA auditor can cross-check against the QR/XML.
    // Only Invoice/CreditNote/DebitNote's own pdfData()/$doc builder
    // sets 'issue_time' (see ZatcaXmlGenerator's own cbc:IssueTime
    // source) — Quotations, Bills, and Purchase Returns aren't ZATCA
    // documents and simply don't set it, so they render unchanged.
    $issuedDisplay = \App\Support\PlatformFormat::date($doc['date'])
        .(! empty($doc['issue_time']) ? ' '.\App\Support\PlatformFormat::time($doc['issue_time']) : '');
    // See InvoiceTemplate's own doc comments: this nudges the base/
    // paragraph text size (body copy, notes, footer) rather than the
    // already page-density-tuned item tables scattered per layout —
    // and, unlike the browser view's Tailwind rem classes, every size
    // here is a literal PHP-interpolated pt value already, so scaling
    // it is just arithmetic, no CSS cascade limitation to work around.
    $tplFontScale = $template?->fontSizeScale() ?? 1.0;
    $tplBorder = $template?->borderStyleCss();
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: {{ round(10 * $tplFontScale, 1) }}pt; }
    table { border-collapse: collapse; width: 100%; }
    .muted { color: #64748b; font-size: {{ round(9 * $tplFontScale, 1) }}pt; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .ar { direction: rtl; text-align: right; }
    /* The Phase 2 QR is a dense 9-tag payload (~450-500 base64 chars,
       version ~25+ even at Quartile EC) — printed too small, its modules
       shrink below what a phone camera can resolve and every scan comes
       back corrupted, even though the underlying data is correct. 85px
       (~22mm at 96dpi) was sized for the old 5-tag Phase 1 QR; Phase 2
       needs meaningfully more physical area for the same module count to
       stay scannable. */
    .qr-img { width: 180px; height: 180px; }
    .footer-note { margin-top: {{ $footerGap + 4 }}px; font-size: {{ round(8.5 * $tplFontScale, 1) }}pt; color: #94a3b8; text-align: center; }
    /* No fixed height here — a real company stamp/seal is rarely a
       perfect square, and mPDF (unlike a browser) has no object-fit
       support, so forcing both width and height stretches/distorts a
       non-square stamp image. Width alone lets it scale proportionally. */
    .stamp-img { width: 130px; }
    .notes-block { margin-top: {{ $notesGap }}px; font-size: {{ round(8.5 * $tplFontScale, 1) }}pt; }
    @if ($tplBorder)
        .doc-frame { border: {{ $tplBorder }}; padding: 10px; }
    @endif
</style>
</head>
<body>
@if ($tplBorder)
<div class="doc-frame">
@endif
{{-- Note: mPDF's table renderer has known instability combining a
     dir="rtl" <body> with certain colspan/border table shapes (a real
     "Undefined array key BORDER-RIGHT" crash was hit here), so
     table_direction only flips column order in the browser-rendered
     documents.print.body view. Arabic text itself still renders
     correctly in the PDF via the per-element dir="rtl" spans below,
     regardless of the body's own direction. --}}

{{--
    Registered as mPDF's own repeating <htmlpagefooter> (not a plain
    trailing <img>) so it's pinned to the bottom of EVERY page,
    available on every layout — a plain <img> at the end of the
    document just flowed once wherever the content happened to end,
    so on a long enough document it could land mid-page or spill onto
    its own trailing page instead of repeating at the bottom of each
    one (same bug found and fixed in letter-pdf.blade.php).
--}}
@if ($footerData)
    <htmlpagefooter name="footer-banner">
        <img src="{{ $footerData }}" style="width: 100%;" alt="">
    </htmlpagefooter>
    <sethtmlpagefooter name="footer-banner" page="ALL" />
@endif

@if ($layout === 'bilingual_classic')
    <table style="border: 1.5pt solid {{ $accent }}; margin-bottom: {{ $sectionGap }}px;"><tr><td style="height: 6px;"></td></tr></table>

    @include('documents.print.pdf-bilingual-header', ['company' => $company, 'showLogo' => $showLogo, 'logoData' => $logoData])

    <table style="margin-top: {{ $headerGap }}px; border-bottom: 1.5pt solid #1e293b; padding-bottom: 5px;">
        <tr><td style="text-align: center; font-size: 17pt; font-weight: bold; color: #0f172a; padding-bottom: 5px;">
            <span class="ar">{{ $doc['type_label_ar'] ?? '' }}</span> &nbsp; {{ $doc['type_label'] }}
        </td></tr>
    </table>

    @include('documents.print.pdf-payment-status-badge', ['doc' => $doc])

    <table style="margin-top: {{ $notesGap }}px; border: 0.5pt solid #cbd5e1;">
        <tr style="border-bottom: 0.5pt solid #cbd5e1;">
            <td style="width: 18%; padding: 2px 6px; font-weight: bold; font-size: 8.5pt;">{{ $doc['party_label'] }}</td>
            <td style="padding: 2px 6px; text-align: center; font-size: 8.5pt;">
                {{ $doc['party']->name }}
                @if (!empty($doc['party']->name_ar))<div class="ar">{{ $doc['party']->name_ar }}</div>@endif
            </td>
            <td class="ar" style="width: 18%; padding: 2px 6px; font-weight: bold; font-size: 8.5pt;">{{ $doc['party_label_ar'] ?? __('Party') }}</td>
        </tr>
        @if ($showPartyVatNumber)
            <tr style="border-bottom: 0.5pt solid #cbd5e1;">
                <td style="padding: 2px 6px; font-weight: bold; font-size: 8.5pt;">{{ __('VAT number') }}</td>
                <td style="padding: 2px 6px; text-align: center; font-size: 8.5pt;">{{ $doc['party']->vat_number ?: '—' }}</td>
                <td class="ar" style="padding: 2px 6px; font-weight: bold; font-size: 8.5pt;">رقم التسجيل الضريبي</td>
            </tr>
        @endif
        @if (method_exists($doc['party'], 'fullAddress') && $doc['party']->fullAddress())
            <tr style="border-bottom: 0.5pt solid #cbd5e1;">
                <td style="padding: 2px 6px; font-weight: bold; font-size: 8.5pt;">{{ __('Address') }}</td>
                <td style="padding: 2px 6px; text-align: center; font-size: 8.5pt;">{{ $doc['party']->fullAddress() }}</td>
                <td class="ar" style="padding: 2px 6px; font-weight: bold; font-size: 8.5pt;">العنوان</td>
            </tr>
        @endif
        <tr @if (!empty($doc['date2']) || !empty($doc['payment_method'])) style="border-bottom: 0.5pt solid #cbd5e1;" @endif>
            <td style="padding: 2px 6px; font-weight: bold; font-size: 8.5pt;">{{ __('Number') }}</td>
            <td style="padding: 2px 6px; text-align: center; font-size: 8.5pt;">{{ $doc['number'] }} | {{ $doc['date_label'] }} {{ $issuedDisplay }}</td>
            <td class="ar" style="padding: 2px 6px; font-weight: bold; font-size: 8.5pt;">رقم | التاريخ</td>
        </tr>
        @if (!empty($doc['date2']))
            <tr @if (!empty($doc['payment_method'])) style="border-bottom: 0.5pt solid #cbd5e1;" @endif>
                <td style="padding: 2px 6px; font-weight: bold; font-size: 8.5pt;">{{ $doc['date2_label'] }}</td>
                <td style="padding: 2px 6px; text-align: center; font-size: 8.5pt;">{{ \App\Support\PlatformFormat::date($doc['date2']) }}</td>
                <td class="ar" style="padding: 2px 6px; font-weight: bold; font-size: 8.5pt;">{{ $doc['date2_label_ar'] ?? '' }}</td>
            </tr>
        @endif
        @if (!empty($doc['payment_method']))
            <tr>
                <td style="padding: 2px 6px; font-weight: bold; font-size: 8.5pt;">{{ __('Payment method') }}</td>
                <td style="padding: 2px 6px; text-align: center; font-size: 8.5pt;">{{ $doc['payment_method'] }}</td>
                <td class="ar" style="padding: 2px 6px; font-weight: bold; font-size: 8.5pt;">طريقة الدفع</td>
            </tr>
        @endif
    </table>

    <table style="margin-top: {{ $notesGap }}px;">
        <thead>
            <tr style="border-bottom: 1.5pt solid #1e293b; text-align: left; font-size: 7.5pt; line-height: 1.25; @if ($tableHeaderColor) background-color: {{ $tableHeaderColor }}; @endif">
                <th style="padding: 2px 6px; width: 3%; border-right: 0.5pt solid #e2e8f0;">#</th>
                <th style="padding: 2px 6px; border-right: 0.5pt solid #e2e8f0;">{{ __('Description') }}<br><span class="ar" style="font-weight: normal;">الوصف</span></th>
                <th class="text-end" style="padding: 2px 6px; width: 8%; border-right: 0.5pt solid #e2e8f0;">{{ __('Qty') }}<br><span class="ar" style="font-weight: normal;">الكمية</span></th>
                <th class="text-end" style="padding: 2px 6px; width: 11%; @if ($showVatColumn) border-right: 0.5pt solid #e2e8f0; @endif">{{ __('Price') }}<br><span class="ar" style="font-weight: normal;">السعر</span></th>
                @if ($showVatColumn)
                    <th class="text-end" style="padding: 2px 6px; width: 17%; border-right: 0.5pt solid #e2e8f0;">{{ __('Taxable amount') }}<br><span class="ar" style="font-weight: normal;">المبلغ الخاضع للضريبة</span></th>
                    <th class="text-end" style="padding: 2px 6px; width: 15%;">{{ __('VAT amount') }}<br><span class="ar" style="font-weight: normal;">القيمة المضافة</span></th>
                @endif
                <th class="text-end" style="padding: 2px 6px; width: 15%;">{{ __('Line amount') }}<br><span class="ar" style="font-weight: normal;">المجموع</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $index => $line)
                <tr style="border-bottom: 0.5pt solid #e2e8f0; font-size: 8pt;">
                    <td style="padding: 2px 6px; vertical-align: top; color: #64748b; border-right: 0.5pt solid #e2e8f0;">{{ $index + 1 }}</td>
                    <td style="padding: 2px 6px; vertical-align: top; border-right: 0.5pt solid #e2e8f0;">
                        <strong>{{ $primary($line->description, $line->name_ar) }}</strong>
                        @if ($secondary($line->name_ar))<div class="ar">{{ $line->name_ar }}</div>@endif
                        @if ($showItemDescription && !empty($line->item_description))<div class="muted">{{ $line->item_description }}</div>@endif
                    </td>
                    <td class="text-end" style="padding: 2px 6px; vertical-align: top; border-right: 0.5pt solid #e2e8f0;">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }} @if ($showUnitLabels)<span class="muted">{{ ($line->unit?->symbol ?: $line->unit?->nameFor(app()->getLocale())) ?? $line->item?->unit }}</span>@endif</td>
                    <td class="text-end" style="padding: 2px 6px; vertical-align: top; @if ($showVatColumn) border-right: 0.5pt solid #e2e8f0; @endif">{{ number_format($line->unit_price, 2) }}</td>
                    @if ($showVatColumn)
                        <td class="text-end" style="padding: 2px 6px; vertical-align: top; border-right: 0.5pt solid #e2e8f0;">{{ number_format($line->quantity * $line->unit_price, 2) }}</td>
                        <td class="text-end" style="padding: 2px 6px; vertical-align: top;">{{ number_format($line->vat_amount, 2) }}<br><span class="muted">{{ rtrim(rtrim(number_format($line->vat_rate, 2), '0'), '.') }}%</span></td>
                    @endif
                    <td class="text-end" style="padding: 2px 6px; vertical-align: top; font-weight: bold;">{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @include('documents.print.pdf-bank-totals', ['doc' => $doc, 'bankAccounts' => $bankAccounts, 'sectionGap' => $notesGap])

    @include('documents.print.pdf-notes-stamp', ['doc' => $doc, 'template' => $template, 'stampData' => $stampData, 'stampSize' => $stampSizeOverride ?: 40, 'qrGap' => 0])

    {{-- The bilingual party/info table, item table, and bank-totals block
         here all run taller per row than the other layouts (every row
         repeats its label in both languages), so this layout's own
         signature gap is trimmed a bit further than the shared default
         to keep more items fitting on one page. --}}
    @include('documents.print.pdf-signature', ['signatureGap' => 0])

    <table style="margin-top: 2px;"><tr><td class="footer-note">
        {{ $company->name }}@if ($company->name_ar) — <span class="ar">{{ $company->name_ar }}</span>@endif &nbsp;·&nbsp; {{ $doc['number'] }}
    </td></tr></table>

@elseif ($layout === 'custom_letterhead')
    {{--
        Registered as mPDF's own repeating <htmlpageheader> (not a plain
        inline <img>) so it's pinned to the top of EVERY page the
        document spans, instead of flowing once wherever the content
        happened to end — a plain <img> here meant any document long
        enough to reach a second page lost its letterhead entirely past
        page 1 (same bug found and fixed in letter-pdf.blade.php; see
        MpdfRenderer::resolvePageMargins() for the matching page-1
        margin fix this needs to avoid overlapping body content).
    --}}
    @if ($letterheadData)
        <htmlpageheader name="letterhead">
            <img src="{{ $letterheadData }}" style="width: 100%;" alt="">
        </htmlpageheader>
        <sethtmlpageheader name="letterhead" page="ALL" show-this-page="1" />
    @else
        @include('documents.print.pdf-bilingual-header', ['company' => $company, 'showLogo' => $showLogo, 'logoData' => $logoData])
    @endif

    <table style="margin-top: 12px;">
        <tr><td style="text-align: center; font-size: 12pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt;">
            {{ strtoupper($doc['type_label']) }} <span class="ar" style="font-weight: normal;">/ ({{ $doc['type_label_ar'] ?? '' }})</span>
        </td></tr>
    </table>

    @include('documents.print.pdf-payment-status-badge', ['doc' => $doc])

    <table style="margin-top: 12px; font-size: 9.5pt;">
        <tr>
            <td style="width: 25%; padding: 3px 0; font-weight: bold; color: #475569;">{{ $doc['party_label'] }}</td>
            <td style="width: 25%; padding: 3px 0;">{{ $doc['party']->name }}</td>
            <td style="width: 25%; padding: 3px 0; font-weight: bold; color: #475569;">{{ $lbl('Number') }}</td>
            <td style="padding: 3px 0;">{{ $doc['number'] }}</td>
        </tr>
        <tr>
            <td style="padding: 3px 0; font-weight: bold; color: #475569;">{{ $doc['date_label'] }}</td>
            <td style="padding: 3px 0;">{{ $issuedDisplay }}</td>
            @if (!empty($doc['date2']))
                <td style="padding: 3px 0; font-weight: bold; color: #475569;">{{ $doc['date2_label'] }}</td>
                <td style="padding: 3px 0;">{{ \App\Support\PlatformFormat::date($doc['date2']) }}</td>
            @endif
        </tr>
        @if (!empty($doc['payment_method']))
            <tr><td style="padding: 3px 0; font-weight: bold; color: #475569;">{{ $lbl('Payment method') }}</td><td style="padding: 3px 0;" colspan="3">{{ $doc['payment_method'] }}</td></tr>
        @endif
        @if (!empty($doc['ref_no']))
            <tr><td style="padding: 3px 0; font-weight: bold; color: #475569;">{{ $lbl('Ref No') }}</td><td style="padding: 3px 0;" colspan="3">{{ $doc['ref_no'] }}</td></tr>
        @endif
        @if (method_exists($doc['party'], 'fullAddress') && $doc['party']->fullAddress())
            <tr><td style="padding: 3px 0; font-weight: bold; color: #475569;">{{ $lbl('Address') }}</td><td style="padding: 3px 0;" colspan="3">{{ $doc['party']->fullAddress() }}</td></tr>
        @endif
    </table>

    <table style="margin-top: 12px; border: 0.5pt solid #cbd5e1;">
        <thead>
            <tr style="background-color: {{ $tableHeaderColor ?: '#f8fafc' }}; text-align: left; font-size: 8.5pt;">
                <th style="border: 0.5pt solid #cbd5e1; padding: 5px; width: 8%;">Sr</th>
                <th style="border: 0.5pt solid #cbd5e1; padding: 5px;">{{ $lbl('Items') }}</th>
                <th class="text-end" style="border: 0.5pt solid #cbd5e1; padding: 5px; width: 18%;">{{ $lbl('Quantity') }}</th>
                <th class="text-end" style="border: 0.5pt solid #cbd5e1; padding: 5px; width: 18%;">{{ $lbl('Rate') }}</th>
                <th class="text-end" style="border: 0.5pt solid #cbd5e1; padding: 5px; width: 20%;">{{ $lbl('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $index => $line)
                <tr>
                    <td style="border: 0.5pt solid #cbd5e1; padding: 5px; vertical-align: top; color: #64748b;">{{ $index + 1 }}</td>
                    <td style="border: 0.5pt solid #cbd5e1; padding: 5px; vertical-align: top;">
                        {{ $primary($line->description, $line->name_ar) }}
                        @if ($secondary($line->name_ar))<div class="ar">{{ $line->name_ar }}</div>@endif
                        @if ($showItemDescription && !empty($line->item_description))<div class="muted">{{ $line->item_description }}</div>@endif
                    </td>
                    <td class="text-end" style="border: 0.5pt solid #cbd5e1; padding: 5px; vertical-align: top;">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }} @if ($showUnitLabels){{ ($line->unit?->symbol ?: $line->unit?->nameFor(app()->getLocale())) ?? $line->item?->unit }}@endif</td>
                    <td class="text-end" style="border: 0.5pt solid #cbd5e1; padding: 5px; vertical-align: top;">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="text-end" style="border: 0.5pt solid #cbd5e1; padding: 5px; vertical-align: top; font-weight: bold;">{{ number_format($line->quantity * $line->unit_price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 12px;">
        <tr>
            <td style="width: 55%; vertical-align: top; font-size: 9.5pt;">
                <div style="font-weight: bold; color: #334155;">{{ $lbl('In Words') }}</div>
                <div class="muted">{{ $languageMode === 'arabic_only' ? \App\Support\NumberToWords::arabicRiyals($doc['total']) : \App\Support\NumberToWords::sar($doc['total'], 'SAR') }}</div>
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table>
                    <tr><td style="padding: 2px 0; font-weight: bold;">{{ $lbl('Total') }}</td><td class="text-end" style="padding: 2px 0;">{{ number_format($doc['subtotal'] - ($doc['discount_total'] ?? 0), 2) }}</td></tr>
                    <tr><td style="padding: 2px 0; font-weight: bold;">{{ $lbl('Total Tax') }}</td><td class="text-end" style="padding: 2px 0;">{{ number_format($doc['vat_total'], 2) }}</td></tr>
                    <tr><td style="padding: 4px 0; font-weight: bold; font-size: 11pt; border-top: 1pt solid #0f172a;">{{ $lbl('Grand Total') }}</td><td class="text-end" style="padding: 4px 0; font-weight: bold; font-size: 11pt; border-top: 1pt solid #0f172a;">{{ number_format($doc['total'], 2) }}</td></tr>
                    @foreach ($doc['extra_rows'] ?? [] as $row)
                        @php
                            $rowColor = match ($row['variant'] ?? null) { 'red' => '#dc2626', 'green' => '#059669', default => '#334155' };
                            $rowWeight = ! empty($row['emphasis']) ? 'font-weight: bold;' : '';
                        @endphp
                        <tr><td style="padding: 2px 0; color: {{ $rowColor }}; {{ $rowWeight }}">{{ $primary($row['label'], $row['label_ar'] ?? null) }}</td><td class="text-end" style="padding: 2px 0; color: {{ $rowColor }}; {{ $rowWeight }}">{{ number_format($row['value'], 2) }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    @if (!empty($doc['salesperson']))
        <div style="margin-top: 12px; font-size: 9.5pt;">
            <div style="font-weight: bold;">{{ $lbl('Sales Executive') }}</div>
            <div class="muted">{{ $doc['salesperson']->name }}@if ($doc['salesperson']->phone) — {{ $lbl('Contact No') }}: {{ $doc['salesperson']->phone }}@endif</div>
        </div>
    @endif

    @if ($bankAccounts->isNotEmpty())
        <div style="margin-top: 10px; font-size: 9.5pt;">
            <div style="font-weight: bold;">{{ $lbl('Bank Account Details') }}:</div>
            @foreach ($bankAccounts as $ba)
                <div class="muted">{{ $lbl('Bank Name') }}: {{ $ba->bank_name ?: $ba->name }} &nbsp; {{ $lbl('IBAN') }}: {{ $ba->iban }} &nbsp; {{ $lbl('A/C #') }}: {{ $ba->account_number }}</div>
            @endforeach
        </div>
    @endif

    @include('documents.print.pdf-notes-stamp', ['doc' => $doc, 'template' => $template, 'stampData' => $stampData, 'stampSize' => $stampSizeOverride ?: 150])

    @include('documents.print.pdf-signature')

    {{-- Table-wrapped, not a bare trailing <div> — mPDF's page-break
         sizing for a block <div> immediately after a <table> at the very
         end of the flow overestimates the space it needs (reproduced:
         forces a lone 2nd page even with 20+mm of visible room left),
         so every layout's closing footer note uses this shape instead.
         Also drops the hardcoded "Page 1 of 1" claim (it never reflected
         the real page count) in favor of the same company/number format
         every other layout's footer already uses. --}}
    <table style="margin-top: {{ $footerGap + 4 }}px;"><tr><td class="footer-note">
        {{ $company->name }}@if ($company->name_ar) — <span class="ar">{{ $company->name_ar }}</span>@endif &nbsp;·&nbsp; {{ $doc['number'] }}
    </td></tr></table>

@elseif ($layout === 'quotation_offer')
    {{-- Modeled 1:1 on a real construction-industry price quotation a
         user shared as a reference: plain (unboxed) logo top-left, a
         plain No./Date/Hijri date/C.R. block top-right, a centered
         title, a "Messrs. X — Peace be upon you" salutation, the items
         table with totals appended as rows beneath it, a numbered
         payment-terms section, a single signature+stamp+phone block
         (always on the left, regardless of language), and a bilingual
         address footer band. Column order stays LTR even in Arabic mode
         — see the dir="rtl" warning at the top of this file; only text
         alignment flips per cell, never table structure. --}}
    @php
        $isArOnly = $languageMode === 'arabic_only';
        $hijriDate = \App\Support\HijriDate::format($doc['date']);
        $qoSignerLabel = $isArOnly
            ? ($template->signature_label_ar ?: 'المفوض بالتوقيع')
            : ($template->signature_label_en ?: 'Authorized Signatory').($showAr ? (' / '.($template->signature_label_ar ?: 'المفوض بالتوقيع')) : '');
        $qoNotesLines = ! empty($doc['notes'])
            ? array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($doc['notes'])), fn ($l) => trim($l) !== ''))
            : [];
        $qoRight = $isArOnly ? 'right' : 'left';
        $qoLeft = $isArOnly ? 'left' : 'right';
        // A company-written override for the boilerplate "Dear Sirs, We
        // are pleased to submit..." block below — or hide it entirely.
        // Null/blank falls back to the default wording.
        $qoShowGreeting = $template->show_greeting ?? true;
        $qoCustomGreetingEn = trim((string) ($template->greeting_en ?? ''));
        $qoCustomGreetingAr = trim((string) ($template->greeting_ar ?? ''));
        $qoHasCustomGreeting = $qoCustomGreetingEn !== '' || $qoCustomGreetingAr !== '';
    @endphp

    <table>
        <tr>
            <td style="width: 22%; vertical-align: top;">
                @if ($showLogo && $logoData)
                    <img src="{{ $logoData }}" style="height: 64px;" alt="">
                @endif
            </td>
            <td style="width: 53%; vertical-align: middle; text-align: center;">
                <div style="font-size: 13pt; font-weight: bold; color: #0f172a;">
                    @if ($isArOnly)
                        {{ $doc['type_label_ar'] ?? $doc['type_label'] }}
                    @else
                        {{ $doc['type_label'] }}@if ($showAr && !empty($doc['type_label_ar'])) / {{ $doc['type_label_ar'] }}@endif
                    @endif
                </div>
            </td>
            <td style="width: 25%; vertical-align: top; text-align: right; font-size: 8.5pt; color: #334155;">
                <div>{{ $lbl('No.') }} : {{ $doc['number'] }}</div>
                <div>{{ $lbl('Date') }} : {{ $issuedDisplay }}</div>
                @if ($hijriDate)
                    <div>{{ $lbl('Date') }} : {{ $hijriDate }}</div>
                @endif
                @if (!empty($doc['date2']))
                    <div>{{ $doc['date2_label'] }}@if ($showAr && !empty($doc['date2_label_ar'])) / {{ $doc['date2_label_ar'] }}@endif : {{ \App\Support\PlatformFormat::date($doc['date2']) }}</div>
                @endif
                @if ($company->cr_number)
                    <div>{{ $lbl('C.R.') }} : {{ $company->cr_number }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table style="margin-top: {{ $sectionGap }}px;">
        <tr>
            <td style="text-align: {{ $qoRight }}; font-weight: bold; font-size: 10pt;">
                @if ($isArOnly)
                    السادة/ {{ $doc['party']->name_ar ?: $doc['party']->name }} المحترمين
                @else
                    {{ $doc['party_label'] }}@if ($showAr && !empty($doc['party_label_ar'])) / {{ $doc['party_label_ar'] }}@endif: {{ $doc['party']->name }}@if ($showAr && !empty($doc['party']->name_ar)) / {{ $doc['party']->name_ar }}@endif
                @endif
            </td>
        </tr>
        @if ($qoShowGreeting)
            <tr>
                <td style="text-align: {{ $qoRight }}; padding-top: 4px; font-size: 9.5pt;">
                    @if ($qoHasCustomGreeting)
                        @if ($isArOnly)
                            {{ $qoCustomGreetingAr ?: $qoCustomGreetingEn }}
                        @else
                            {{ $qoCustomGreetingEn ?: $qoCustomGreetingAr }}@if ($showAr && $qoCustomGreetingEn !== '' && $qoCustomGreetingAr !== '') / {{ $qoCustomGreetingAr }}@endif
                        @endif
                    @else
                        {{ $isArOnly ? 'السلام عليكم ورحمة الله وبركاته،' : 'Dear Sirs,' }}@if (!$isArOnly && $showAr) / السلام عليكم ورحمة الله وبركاته،@endif
                    @endif
                </td>
            </tr>
            @unless ($qoHasCustomGreeting)
                <tr>
                    <td style="text-align: {{ $qoRight }}; padding-top: 4px; font-size: 9.5pt; font-weight: bold;">
                        @if ($isArOnly)
                            يسرنا نحن {{ $company->name_ar ?: $company->name }} أن نقدم لكم {{ $doc['type_label_ar'] ?? 'عرض السعر' }} التالي وفق البنود التالية:
                        @else
                            We, {{ $company->name }}, are pleased to submit the following {{ \Illuminate\Support\Str::lower($doc['type_label']) }} in accordance with the items below:
                            @if ($showAr)
                                <br><span class="ar">يسرنا نحن {{ $company->name_ar ?: $company->name }} أن نقدم لكم {{ $doc['type_label_ar'] ?? 'عرض السعر' }} التالي وفق البنود التالية:</span>
                            @endif
                        @endif
                    </td>
                </tr>
            @endunless
        @endif
    </table>

    <table style="margin-top: {{ $sectionGap }}px; border: 0.5pt solid #cbd5e1;">
        <thead>
            <tr style="background-color: {{ $tableHeaderColor ?: '#f8fafc' }}; font-size: 8.5pt;">
                <th style="border: 0.5pt solid #cbd5e1; padding: 5px; width: 6%;">{{ $isArOnly ? 'البند' : '#' }}@if (!$isArOnly && $showAr) / البند@endif</th>
                <th style="border: 0.5pt solid #cbd5e1; padding: 5px; text-align: {{ $qoRight }};">{{ $lbl('Description') }}</th>
                <th style="border: 0.5pt solid #cbd5e1; padding: 5px; width: 10%; text-align: {{ $qoLeft }};">{{ $lbl('Unit') }}</th>
                <th style="border: 0.5pt solid #cbd5e1; padding: 5px; width: 10%; text-align: {{ $qoLeft }};">{{ $lbl('Qty') }}</th>
                <th style="border: 0.5pt solid #cbd5e1; padding: 5px; width: 15%; text-align: {{ $qoLeft }};">{{ $lbl('Unit price') }}</th>
                <th style="border: 0.5pt solid #cbd5e1; padding: 5px; width: 16%; text-align: {{ $qoLeft }};">{{ $lbl('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $index => $line)
                <tr>
                    <td style="border: 0.5pt solid #cbd5e1; padding: 5px; color: #64748b;">{{ $index + 1 }}</td>
                    <td style="border: 0.5pt solid #cbd5e1; padding: 5px; text-align: {{ $qoRight }};">
                        {{ $primary($line->description, $line->name_ar) }}
                        @if ($secondary($line->name_ar))<div class="ar">{{ $line->name_ar }}</div>@endif
                    </td>
                    <td style="border: 0.5pt solid #cbd5e1; padding: 5px; text-align: {{ $qoLeft }};">{{ ($line->unit?->symbol ?: $line->unit?->nameFor(app()->getLocale())) ?? $line->item?->unit ?? '—' }}</td>
                    <td style="border: 0.5pt solid #cbd5e1; padding: 5px; text-align: {{ $qoLeft }};">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                    <td style="border: 0.5pt solid #cbd5e1; padding: 5px; text-align: {{ $qoLeft }};">{{ number_format($line->unit_price, 2) }}</td>
                    <td style="border: 0.5pt solid #cbd5e1; padding: 5px; text-align: {{ $qoLeft }}; font-weight: bold;">{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="border: 0.5pt solid #cbd5e1; padding: 5px; font-weight: bold; text-align: {{ $qoRight }};">{{ $lbl('Total excluding VAT') }}</td>
                <td colspan="2" style="border: 0.5pt solid #cbd5e1; padding: 5px; text-align: {{ $qoLeft }};">{{ \App\Support\Money::format($doc['subtotal'] - ($doc['discount_total'] ?? 0)) }}</td>
            </tr>
            @if (($doc['discount_total'] ?? 0) > 0)
                <tr>
                    <td colspan="4" style="border: 0.5pt solid #cbd5e1; padding: 5px; text-align: {{ $qoRight }};">{{ $lbl('Discount') }}</td>
                    <td colspan="2" style="border: 0.5pt solid #cbd5e1; padding: 5px; text-align: {{ $qoLeft }};">-{{ \App\Support\Money::format($doc['discount_total']) }}</td>
                </tr>
            @endif
            <tr>
                <td colspan="4" style="border: 0.5pt solid #cbd5e1; padding: 5px; text-align: {{ $qoRight }};">{{ $lbl('VAT') }}</td>
                <td colspan="2" style="border: 0.5pt solid #cbd5e1; padding: 5px; text-align: {{ $qoLeft }};">{{ \App\Support\Money::format($doc['vat_total']) }}</td>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <td colspan="4" style="border: 0.5pt solid #cbd5e1; padding: 5px; font-weight: bold; font-size: 10pt; text-align: {{ $qoRight }};">{{ $lbl('Total including VAT') }}</td>
                <td colspan="2" style="border: 0.5pt solid #cbd5e1; padding: 5px; font-weight: bold; font-size: 10pt; text-align: {{ $qoLeft }};">{{ \App\Support\Money::format($doc['total']) }}</td>
            </tr>
            @foreach ($doc['extra_rows'] ?? [] as $row)
                @php
                    $rowStyle = match ($row['variant'] ?? null) {
                        'red' => 'color: #dc2626;',
                        'green' => 'color: #059669;',
                        default => '',
                    };
                @endphp
                <tr>
                    <td colspan="4" style="border: 0.5pt solid #cbd5e1; padding: 5px; font-weight: bold; text-align: {{ $qoRight }}; {{ $rowStyle }}">{{ $row['label'] }}</td>
                    <td colspan="2" style="border: 0.5pt solid #cbd5e1; padding: 5px; font-weight: bold; text-align: {{ $qoLeft }}; {{ $rowStyle }}">{{ \App\Support\Money::format($row['value'], $row['currency'] ?? null) }}</td>
                </tr>
            @endforeach
        </tfoot>
    </table>

    @if (count($qoNotesLines) > 0)
        <table style="margin-top: {{ $notesGap }}px;">
            <tr><td style="font-weight: bold; font-size: 10pt; text-align: {{ $qoRight }};">{{ $lbl('Payment Terms') }}</td></tr>
        </table>
        <table style="margin-top: 4px; font-size: 9pt;">
            @foreach ($qoNotesLines as $i => $line)
                <tr>
                    @if ($isArOnly)
                        <td style="padding: 2px 0; text-align: right;">{{ $line }}</td>
                        <td style="padding: 2px 0 2px 4px; width: 20px; text-align: right; vertical-align: top;">.{{ $i + 1 }}</td>
                    @else
                        <td style="padding: 2px 4px 2px 0; width: 20px; vertical-align: top;">{{ $i + 1 }}.</td>
                        <td style="padding: 2px 0;">{{ $line }}</td>
                    @endif
                </tr>
            @endforeach
        </table>
    @endif

    <table style="margin-top: {{ $signatureGap }}px;">
        <tr><td style="text-align: {{ $qoRight }}; font-size: 9.5pt;">{{ $isArOnly ? 'وتفضلوا بقبول فائق الاحترام،،،' : 'Yours faithfully,' }}@if (!$isArOnly && $showAr) / وتفضلوا بقبول فائق الاحترام،،،@endif</td></tr>
    </table>
    <table style="margin-top: 8px;">
        <tr>
            <td style="width: 45%; text-align: {{ $qoRight }};">
                @if ($stampData)
                    <img src="{{ $stampData }}" style="width: {{ $stampSizeOverride ?: 130 }}px;" alt="">
                @endif
                <div style="font-weight: bold; margin-top: 4px;">{{ $qoSignerLabel }}</div>
                @if ($company->phone)<div class="muted">{{ $company->phone }}</div>@endif
            </td>
            <td style="width: 55%;"></td>
        </tr>
    </table>

    <table style="margin-top: 8px;">
        <tr><td class="footer-note">
            @if ($company->address || $company->city)
                <div>{{ $company->address }}{{ $company->address && $company->city ? ' - ' : '' }}{{ $company->city }}</div>
            @endif
            <div>
                Kingdom of Saudi Arabia — <span class="ar">المملكة العربية السعودية</span>
                @if ($company->phone) &nbsp;·&nbsp; Phone: {{ $company->phone }} @endif
            </div>
        </td></tr>
    </table>

@elseif ($layout === 'sage_classic')
    {{-- mPDF counterpart of the "Sage Classic" body.blade.php branch —
         same fields/order, table/inline-style markup throughout. --}}
    <table>
        <tr>
            <td style="width: 50%; vertical-align: top;">
                @if ($showLogo && $logoData)
                    <img src="{{ $logoData }}" style="height: 44px;" alt="">
                @endif
                @if (!empty($doc['qr_code']))
                    <table style="margin-top: 8px;"><tr><td><img src="data:image/png;base64,{{ $doc['qr_code'] }}" style="width: 130px; height: 130px;" alt=""></td></tr></table>
                @endif
            </td>
            <td style="width: 50%; vertical-align: top;">
                <table style="border: 0.75pt solid #cbd5e1;">
                    <tr style="border-bottom: 0.5pt solid #cbd5e1;"><td style="padding: 3px 8px; font-size: 8pt; font-weight: bold; color: #475569; background-color: #f8fafc;">{{ $lbl('Invoice Number') }}</td><td class="text-end" style="padding: 3px 8px; font-size: 8pt;">{{ $doc['number'] }}</td></tr>
                    <tr style="border-bottom: 0.5pt solid #cbd5e1;"><td style="padding: 3px 8px; font-size: 8pt; font-weight: bold; color: #475569; background-color: #f8fafc;">{{ $lbl('Invoice Date') }}</td><td class="text-end" style="padding: 3px 8px; font-size: 8pt;">{{ $issuedDisplay }}</td></tr>
                    @if (!empty($doc['payment_method']))
                        <tr><td style="padding: 3px 8px; font-size: 8pt; font-weight: bold; color: #475569; background-color: #f8fafc;">{{ $lbl('Payment Type') }}</td><td class="text-end" style="padding: 3px 8px; font-size: 8pt;">{{ $doc['payment_method'] }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: {{ $headerGap }}px; border-bottom: 1.5pt solid #1e293b; padding-bottom: 5px;">
        <tr><td style="text-align: center; font-size: 15pt; font-weight: bold; color: #0f172a; padding-bottom: 5px;">
            @if ($showAr){{ $doc['type_label_ar'] ?? '' }}@endif
            @if ($showEn && $showAr) &nbsp;/&nbsp; @endif
            @if ($showEn){{ $doc['type_label'] }}@endif
        </td></tr>
    </table>

    @include('documents.print.pdf-payment-status-badge', ['doc' => $doc])

    @php
        $sellerRows = [
            ['label' => $lbl('Seller Name'), 'value' => $bilingualName($company->name, $company->name_ar)],
            ['label' => $lbl('Vat Number'), 'value' => $company->vat_number],
            ['label' => $lbl('CR Number'), 'value' => $company->cr_number],
            ['label' => $lbl('Building No'), 'value' => $company->building_number],
            ['label' => $lbl('Street Name'), 'value' => $company->street_name],
            ['label' => $lbl('District'), 'value' => $company->district],
            ['label' => $lbl('Postal Code'), 'value' => $company->postal_code],
            ['label' => $lbl('City'), 'value' => $company->city],
        ];
        $buyerRows = [
            ['label' => $lbl('Customer Name'), 'value' => $bilingualName($doc['party']->name, $doc['party']->name_ar ?? null)],
            ['label' => $lbl('Vat Number'), 'value' => $doc['party']->vat_number ?? null],
            ['label' => $lbl('Buyer C.R. No'), 'value' => $doc['party']->cr_number ?? null],
            ['label' => $lbl('Building No'), 'value' => $doc['party']->building_number ?? null],
            ['label' => $lbl('Street Name'), 'value' => $doc['party']->street_name ?? null],
            ['label' => $lbl('District'), 'value' => $doc['party']->district ?? null],
            ['label' => $lbl('Postal Code'), 'value' => $doc['party']->postal_code ?? null],
            ['label' => $lbl('City'), 'value' => $doc['party']->city ?? null],
        ];
    @endphp
    <table style="margin-top: {{ $sectionGap }}px;">
        <tr>
            <td style="width: 49%; vertical-align: top;">
                @include('documents.print.pdf-address-block', ['title' => $lbl('Seller').':', 'headerStyle' => 'background-color: #16a34a; color: #ffffff;', 'rows' => $sellerRows])
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%; vertical-align: top;">
                @include('documents.print.pdf-address-block', ['title' => $lbl('Buyer').':', 'headerStyle' => 'background-color: #16a34a; color: #ffffff;', 'rows' => $buyerRows])
            </td>
        </tr>
    </table>

    <table style="margin-top: {{ $notesGap }}px; border: 0.5pt solid #cbd5e1;">
        <thead>
            <tr style="background-color: #f1f5f9; color: #334155;">
                <th style="padding: 3px 5px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Sno') }}</th>
                <th style="padding: 3px 5px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Item Code') }}</th>
                <th class="text-start" style="padding: 3px 5px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Item Name') }}</th>
                <th class="text-end" style="padding: 3px 5px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Qty') }}</th>
                <th class="text-end" style="padding: 3px 5px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Price') }}</th>
                <th class="text-end" style="padding: 3px 5px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Amount') }}</th>
                <th class="text-end" style="padding: 3px 5px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Disc Amt') }}</th>
                <th class="text-end" style="padding: 3px 5px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Tax Percent') }}</th>
                <th class="text-end" style="padding: 3px 5px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Tax Amt') }}</th>
                <th class="text-end" style="padding: 3px 5px; font-size: 7pt; line-height: 1.15;">{{ $lbl('Total With Tax') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $index => $line)
                <tr style="border-bottom: 0.5pt solid #f1f5f9;">
                    <td class="text-center" style="padding: 3px 5px; border-right: 0.5pt solid #f1f5f9;">{{ $index + 1 }}</td>
                    <td class="text-center" style="padding: 3px 5px; border-right: 0.5pt solid #f1f5f9;">{{ $line->item?->sku ?: '—' }}</td>
                    <td style="padding: 3px 5px; border-right: 0.5pt solid #f1f5f9;">{{ $primary($line->description, $line->name_ar) }}</td>
                    <td class="text-end" style="padding: 3px 5px; border-right: 0.5pt solid #f1f5f9;">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                    <td class="text-end" style="padding: 3px 5px; border-right: 0.5pt solid #f1f5f9;">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="text-end" style="padding: 3px 5px; border-right: 0.5pt solid #f1f5f9;">{{ number_format($line->quantity * $line->unit_price, 2) }}</td>
                    <td class="text-end" style="padding: 3px 5px; border-right: 0.5pt solid #f1f5f9;">0.00</td>
                    <td class="text-end" style="padding: 3px 5px; border-right: 0.5pt solid #f1f5f9;">{{ rtrim(rtrim(number_format($line->vat_rate, 2), '0'), '.') }}%</td>
                    <td class="text-end" style="padding: 3px 5px; border-right: 0.5pt solid #f1f5f9;">{{ number_format($line->vat_amount, 2) }}</td>
                    <td class="text-end" style="padding: 3px 5px; font-weight: bold;">{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: {{ $notesGap }}px;">
        <tr><td style="width: 55%;"></td>
            <td style="width: 45%;">
                <table>
                    <tr><td style="padding: 2px 0; color: #64748b;">{{ $lbl('Total (Excluding VAT)') }}</td><td class="text-end" style="padding: 2px 0;">{{ \App\Support\Money::format($doc['subtotal']) }}</td></tr>
                    @if (($doc['discount_total'] ?? 0) > 0)
                        <tr><td style="padding: 2px 0; color: #64748b;">{{ $lbl('Discount') }}</td><td class="text-end" style="padding: 2px 0;">{{ \App\Support\Money::format($doc['discount_total']) }}</td></tr>
                    @endif
                    <tr><td style="padding: 2px 0; color: #64748b;">{{ $lbl('Total Taxable Amount (Excluding VAT)') }}</td><td class="text-end" style="padding: 2px 0;">{{ \App\Support\Money::format($doc['subtotal'] - ($doc['discount_total'] ?? 0)) }}</td></tr>
                    <tr><td style="padding: 2px 0; color: #64748b;">{{ $lbl('Total VAT 15%') }}</td><td class="text-end" style="padding: 2px 0;">{{ \App\Support\Money::format($doc['vat_total']) }}</td></tr>
                    <tr style="border-top: 1.5pt solid #1e293b;"><td style="padding: 3px 0; font-weight: bold; color: #0f172a;">{{ $lbl('Total Amount Due') }}</td><td class="text-end" style="padding: 3px 0; font-weight: bold; color: #0f172a;">{{ \App\Support\Money::format($doc['total']) }}</td></tr>
                    @foreach ($doc['extra_rows'] ?? [] as $row)
                        @php
                            $rowStyle = match ($row['variant'] ?? null) {
                                'red' => 'color: #dc2626; font-weight: bold;',
                                'green' => 'color: #059669; font-weight: bold;',
                                default => ($row['emphasis'] ?? false) ? 'color: #0f172a; font-weight: bold;' : 'color: #64748b;',
                            };
                        @endphp
                        <tr><td style="padding: 2px 0; {{ $rowStyle }}">{{ $row['label'] }}</td><td class="text-end" style="padding: 2px 0; {{ $rowStyle }}">{{ \App\Support\Money::format($row['value'], $row['currency'] ?? null) }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <div style="margin-top: {{ $notesGap }}px; font-size: 9pt;">
        <span style="font-weight: bold; color: #334155;">{{ $lbl('Amount in Words') }}:</span>
        {{ $languageMode === 'arabic_only' ? \App\Support\NumberToWords::arabicRiyals($doc['total']) : \App\Support\NumberToWords::sar($doc['total'], 'SAR') }}
    </div>

    @include('documents.print.pdf-notes-stamp', ['doc' => $doc, 'template' => $template, 'stampData' => $stampData, 'stampSize' => $stampSizeOverride ?: 35, 'hideQr' => true])

    {{-- Bilingual labels throughout this layout's seller/buyer boxes and
         item table (each cell combines the English and Arabic text, e.g.
         "Seller Name / اسم البائع") run taller than a single-language
         layout's, so — as with retail_classic below — the shared gap
         tuned for the other layouts is trimmed further here. --}}
    @include('documents.print.pdf-signature', ['signatureGap' => 0])

    {{-- Table-wrapped, not a bare trailing <div> — see the matching
         comment on bilingual_classic's own footer above for why. --}}
    <table style="margin-top: 2px;"><tr><td class="footer-note">
        {{ $company->name }}@if ($company->name_ar) — <span class="ar">{{ $company->name_ar }}</span>@endif &nbsp;·&nbsp; {{ $doc['number'] }}
    </td></tr></table>

@elseif ($layout === 'retail_classic')
    {{-- mPDF counterpart of the "Retail Classic" body.blade.php branch —
         same fields/order, table/inline-style markup throughout. --}}
    @include('documents.print.pdf-bilingual-header', ['company' => $company, 'showLogo' => $showLogo, 'logoData' => $logoData])

    <table style="margin-top: {{ $headerGap }}px; border-bottom: 1.5pt solid #1e293b; padding-bottom: 5px;">
        <tr><td style="text-align: center; font-size: 16pt; font-weight: bold; color: #0f172a; padding-bottom: 5px;">
            <span class="ar">{{ $doc['type_label_ar'] ?? '' }}</span> &nbsp;/&nbsp; {{ $doc['type_label'] }}
        </td></tr>
    </table>

    @include('documents.print.pdf-payment-status-badge', ['doc' => $doc])

    <table style="margin-top: {{ $sectionGap }}px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <table style="border: 0.75pt solid #cbd5e1;">
                    <tr style="border-bottom: 0.5pt solid #cbd5e1;"><td style="padding: 3px 8px; font-size: 8pt; font-weight: bold; color: #475569; background-color: #f8fafc;">{{ $lbl('Invoice Ser.') }}</td><td class="text-end" style="padding: 3px 8px; font-size: 8pt;">{{ $doc['number'] }}</td></tr>
                    <tr style="border-bottom: 0.5pt solid #cbd5e1;"><td style="padding: 3px 8px; font-size: 8pt; font-weight: bold; color: #475569; background-color: #f8fafc;">{{ $lbl('Invoice Date') }}</td><td class="text-end" style="padding: 3px 8px; font-size: 8pt;">{{ $issuedDisplay }}</td></tr>
                    <tr style="border-bottom: 0.5pt solid #cbd5e1;"><td style="padding: 3px 8px; font-size: 8pt; font-weight: bold; color: #475569; background-color: #f8fafc;">{{ $lbl('Date Of Supply') }}</td><td class="text-end" style="padding: 3px 8px; font-size: 8pt;">{{ \App\Support\PlatformFormat::date($doc['date']) }}</td></tr>
                    @if (!empty($doc['payment_method']))
                        <tr><td style="padding: 3px 8px; font-size: 8pt; font-weight: bold; color: #475569; background-color: #f8fafc;">{{ $lbl('Payment Mthd') }}</td><td class="text-end" style="padding: 3px 8px; font-size: 8pt;">{{ $doc['payment_method'] }}</td></tr>
                    @endif
                </table>
            </td>
            <td style="width: 40%; text-align: center; vertical-align: top;">
                @if (!empty($doc['qr_code']))
                    <img src="data:image/png;base64,{{ $doc['qr_code'] }}" style="width: 150px; height: 150px;" alt="">
                @endif
            </td>
        </tr>
    </table>

    @php
        $retailSellerRows = [
            ['label' => $lbl('Tax Code'), 'value' => $company->vat_number],
            ['label' => $lbl('R.C'), 'value' => $company->cr_number],
            ['label' => $lbl('Building'), 'value' => $company->building_number],
            ['label' => $lbl('Street'), 'value' => $company->street_name],
            ['label' => $lbl('Area'), 'value' => $company->district],
            ['label' => $lbl('Add No'), 'value' => $company->additional_number ?? null],
            ['label' => $lbl('Country'), 'value' => $primary('Kingdom of Saudi Arabia', 'المملكة العربية السعودية')],
            ['label' => $lbl('City'), 'value' => $company->city],
        ];
        $retailCustomerRows = [
            ['label' => $lbl('Customer'), 'value' => $bilingualName($doc['party']->name, $doc['party']->name_ar ?? null)],
            ['label' => $lbl('Tax Code'), 'value' => $doc['party']->vat_number ?? null],
            ['label' => $lbl('R.C'), 'value' => $doc['party']->cr_number ?? null],
            ['label' => $lbl('Building'), 'value' => $doc['party']->building_number ?? null],
            ['label' => $lbl('Street'), 'value' => $doc['party']->street_name ?? null],
            ['label' => $lbl('Area'), 'value' => $doc['party']->district ?? null],
            ['label' => $lbl('Country'), 'value' => $primary('Kingdom of Saudi Arabia', 'المملكة العربية السعودية')],
            ['label' => $lbl('City'), 'value' => $doc['party']->city ?? null],
        ];
        $retailTaxCode = fn ($item) => match ($item->taxRate?->type ?? null) {
            'exempt' => 'E', 'zero_rated' => 'Z', 'not_subject' => 'O', 'standard' => 'S',
            default => (float) $item->vat_rate > 0 ? 'S' : 'Z',
        };
        $vatGroups = [];
        foreach ($doc['lines'] as $line) {
            $rate = number_format((float) $line->vat_rate, 2);
            $code = $retailTaxCode($line);
            $key = $code.'|'.$rate;
            $vatGroups[$key]['code'] = $code;
            $vatGroups[$key]['rate'] = $rate;
            $vatGroups[$key]['taxable'] = ($vatGroups[$key]['taxable'] ?? 0) + ($line->quantity * $line->unit_price);
            $vatGroups[$key]['tax'] = ($vatGroups[$key]['tax'] ?? 0) + (float) $line->vat_amount;
        }
    @endphp
    <table style="margin-top: {{ $notesGap }}px;">
        <tr>
            <td style="width: 49%; vertical-align: top;">
                @include('documents.print.pdf-address-block', ['title' => $lbl('Seller'), 'headerStyle' => 'background-color: #1d4ed8; color: #ffffff;', 'borderStyle' => 'border-width: 1.5pt; border-color: #1d4ed8;', 'rows' => $retailSellerRows])
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%; vertical-align: top;">
                @include('documents.print.pdf-address-block', ['title' => $lbl('Customer'), 'headerStyle' => 'background-color: #1d4ed8; color: #ffffff;', 'borderStyle' => 'border-width: 1.5pt; border-color: #1d4ed8;', 'rows' => $retailCustomerRows])
            </td>
        </tr>
    </table>

    <table style="margin-top: {{ $notesGap }}px; border: 0.5pt solid #cbd5e1;">
        <thead>
            <tr style="background-color: #f1f5f9; color: #334155;">
                <th style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">#</th>
                <th class="text-start" style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Item Name') }}</th>
                <th style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Item ID') }}</th>
                <th class="text-end" style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Unit') }}</th>
                <th class="text-end" style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Quantity') }}</th>
                <th class="text-end" style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Unit Price') }}</th>
                <th class="text-end" style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Disc.') }}</th>
                <th class="text-end" style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Taxable Amount') }}</th>
                <th class="text-end" style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Tax Rate') }}</th>
                <th style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Tax Code') }}</th>
                <th class="text-end" style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Tax Amount') }}</th>
                <th class="text-end" style="padding: 2px 4px; font-size: 7pt; line-height: 1.15;">{{ $lbl('Item Subtotal Including VAT') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $index => $line)
                <tr style="border-bottom: 0.5pt solid #f1f5f9;">
                    <td class="text-center" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">{{ $index + 1 }}</td>
                    <td style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">
                        {{ $primary($line->description, $line->name_ar) }}
                        @if (!empty($line->item_description))<div class="muted" style="font-size: 8pt;">{{ $line->item_description }}</div>@endif
                    </td>
                    <td class="text-center" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">{{ $line->item?->sku ?: '—' }}</td>
                    <td class="text-end" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">{{ ($line->unit?->symbol ?: $line->unit?->nameFor(app()->getLocale())) ?? $line->item?->unit ?? '—' }}</td>
                    <td class="text-end" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                    <td class="text-end" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="text-end" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">0.00</td>
                    <td class="text-end" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">{{ number_format($line->quantity * $line->unit_price, 2) }}</td>
                    <td class="text-end" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">{{ rtrim(rtrim(number_format($line->vat_rate, 2), '0'), '.') }}%</td>
                    <td class="text-center" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">{{ $retailTaxCode($line) }}</td>
                    <td class="text-end" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">{{ number_format($line->vat_amount, 2) }}</td>
                    <td class="text-end" style="padding: 2px 4px; font-weight: bold;">{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: {{ $notesGap }}px; font-weight: bold; font-size: 9.5pt; color: #334155;">{{ $lbl('VAT Summary') }}</div>
    <table style="margin-top: 3px; border: 0.5pt solid #cbd5e1;">
        <thead>
            <tr style="background-color: #f1f5f9; color: #334155;">
                <th style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Tax Code') }}</th>
                <th class="text-end" style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Tax Rate') }}</th>
                <th class="text-end" style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Taxable Amount') }}</th>
                <th class="text-end" style="padding: 2px 4px; font-size: 7pt; line-height: 1.15; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Tax Amount') }}</th>
                <th class="text-end" style="padding: 2px 4px; font-size: 7pt; line-height: 1.15;">{{ $lbl('Including Tax') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($vatGroups as $group)
                <tr style="border-bottom: 0.5pt solid #f1f5f9;">
                    <td class="text-center" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">{{ $group['code'] }}</td>
                    <td class="text-end" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">{{ $group['rate'] }}%</td>
                    <td class="text-end" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">{{ number_format($group['taxable'], 2) }}</td>
                    <td class="text-end" style="padding: 2px 4px; border-right: 0.5pt solid #f1f5f9;">{{ number_format($group['tax'], 2) }}</td>
                    <td class="text-end" style="padding: 2px 4px;">{{ number_format($group['taxable'] + $group['tax'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: {{ $notesGap }}px;">
        <tr><td style="width: 55%;"></td>
            <td style="width: 45%;">
                <table>
                    <tr><td style="padding: 2px 0; color: #64748b;">{{ $lbl('Total Excluding VAT') }}</td><td class="text-end" style="padding: 2px 0;">{{ \App\Support\Money::format($doc['subtotal']) }}</td></tr>
                    <tr><td style="padding: 2px 0; color: #64748b;">{{ $lbl('Discount') }}</td><td class="text-end" style="padding: 2px 0;">{{ \App\Support\Money::format($doc['discount_total'] ?? 0) }}</td></tr>
                    <tr><td style="padding: 2px 0; color: #64748b;">{{ $lbl('Other Charges') }}</td><td class="text-end" style="padding: 2px 0;">{{ \App\Support\Money::format(0) }}</td></tr>
                    <tr><td style="padding: 2px 0; color: #64748b;">{{ $lbl('Total Taxable Amount') }}</td><td class="text-end" style="padding: 2px 0;">{{ \App\Support\Money::format($doc['subtotal'] - ($doc['discount_total'] ?? 0)) }}</td></tr>
                    <tr><td style="padding: 2px 0; color: #64748b;">{{ $lbl('Total VAT') }}</td><td class="text-end" style="padding: 2px 0;">{{ \App\Support\Money::format($doc['vat_total']) }}</td></tr>
                    <tr style="border-top: 1.5pt solid #1e293b;"><td style="padding: 3px 0; font-weight: bold; color: #0f172a;">{{ $lbl('Total Amount Including VAT') }}</td><td class="text-end" style="padding: 3px 0; font-weight: bold; color: #0f172a;">{{ \App\Support\Money::format($doc['total']) }}</td></tr>
                    @foreach ($doc['extra_rows'] ?? [] as $row)
                        @php
                            $rowStyle = match ($row['variant'] ?? null) {
                                'red' => 'color: #dc2626; font-weight: bold;',
                                'green' => 'color: #059669; font-weight: bold;',
                                default => ($row['emphasis'] ?? false) ? 'color: #0f172a; font-weight: bold;' : 'color: #64748b;',
                            };
                        @endphp
                        <tr><td style="padding: 2px 0; {{ $rowStyle }}">{{ $row['label'] }}</td><td class="text-end" style="padding: 2px 0; {{ $rowStyle }}">{{ \App\Support\Money::format($row['value'], $row['currency'] ?? null) }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <div style="margin-top: {{ $notesGap }}px; font-size: 9pt;">
        <span style="font-weight: bold; color: #334155;">{{ $lbl('Amount in Words') }}:</span>
        {{ $languageMode === 'arabic_only' ? \App\Support\NumberToWords::arabicRiyals($doc['total']) : \App\Support\NumberToWords::sar($doc['total'], 'SAR') }}
    </div>

    @include('documents.print.pdf-notes-stamp', ['doc' => $doc, 'template' => $template, 'stampData' => $stampData, 'stampSize' => $stampSizeOverride ?: 65, 'hideQr' => true])

    {{-- This layout packs more boxes/tables (seller + buyer national
         address blocks, a dedicated VAT Summary table on top of the
         usual totals) than any of the other layouts, so the signature
         gap is trimmed further here specifically — the shared
         $signatureGap already tuned for the other 6 layouts left this
         one's trailing footer line alone on an otherwise-blank 2nd
         page. Passed as an include override (not a reassignment
         one-liner): the inline-PHP shorthand directive, used without
         its own block-closing counterpart, gets mismatched by Blade's
         raw-PHP-block regex against the next unrelated closing tag
         anywhere later in this (very long) file, silently swallowing
         everything between as uncompiled text. --}}
    @include('documents.print.pdf-signature', ['signatureGap' => 2])

    {{-- Table-wrapped, not a bare trailing <div> — see the matching
         comment on bilingual_classic's own footer above for why. --}}
    <table style="margin-top: {{ $footerGap + 4 }}px;"><tr><td class="footer-note">
        {{ $company->name }}@if ($company->name_ar) — <span class="ar">{{ $company->name_ar }}</span>@endif &nbsp;·&nbsp; {{ $doc['number'] }}
    </td></tr></table>

@elseif ($layout === 'distribution_classic')
    {{-- mPDF counterpart of the "Distribution Classic" body.blade.php
         branch — same fields/order/column layout, table/inline-style
         markup throughout. Column order stays left-to-right regardless
         of $tableDirection (see this file's own header comment on why
         mPDF never flips column order), matching every other layout in
         this file. --}}
    @php
        $dcHijri = \App\Support\HijriDate::format($doc['date']);
        $dcTime = \App\Support\PlatformFormat::time($doc['issue_time'] ?? $doc['date']);
        $dcParty = $doc['party'];
        $dcTotalQty = $doc['lines']->sum(fn ($l) => (float) $l->quantity);
        $dcPaid = collect($doc['extra_rows'] ?? [])->first(fn ($r) => ($r['label'] ?? '') === __('Paid'))['value'] ?? 0;
        $dcBalance = collect($doc['extra_rows'] ?? [])->first(fn ($r) => in_array($r['label'] ?? '', [__('Balance due'), __('Overpaid')]))['value'] ?? max($doc['total'] - $dcPaid, 0);
        $dcRates = $doc['lines']->pluck('vat_rate')->unique();
        $dcVatLabel = $dcRates->count() === 1 ? $lbl('Total VAT').' '.rtrim(rtrim(number_format($dcRates->first(), 2), '0'), '.').'%' : $lbl('Total VAT');
        $dcBank = $doc['bank_account'] ?? null;
        $dcCell = 'padding: 3px 6px; font-size: 7.5pt;';
        $dcInfoRows = [
            [
                ['en' => 'Hijri date', 'ar' => 'التاريخ الهجري', 'value' => $dcHijri ?: '—'],
                ['en' => 'Time', 'ar' => 'الوقت', 'value' => $dcTime],
                ['en' => 'Gregorian Date', 'ar' => 'التاريخ الميلادي', 'value' => \App\Support\PlatformFormat::date($doc['date'])],
            ],
            [
                ['en' => 'Customer No', 'ar' => 'رقم العميل', 'value' => $dcParty->client_code ?? ($dcParty->supplier_code ?? '—')],
                ['en' => 'Customer Name', 'ar' => 'أسم العميل', 'value' => $bilingualName($dcParty->name, $dcParty->name_ar ?? null)],
            ],
            [
                ['en' => 'Customer C.R.', 'ar' => 'السجل التجاري للعميل', 'value' => $dcParty->cr_number ?? '—'],
                ['en' => 'Customer VAT No', 'ar' => 'الرقم الضريبي للعميل', 'value' => $dcParty->vat_number ?? '—'],
            ],
            [
                ['en' => 'Tele No.', 'ar' => 'رقم الهاتف', 'value' => $dcParty->phone ?? $dcParty->mobile ?? '—'],
                ['en' => 'Address', 'ar' => 'العنوان', 'value' => method_exists($dcParty, 'fullAddress') ? ($dcParty->fullAddress() ?: '—') : '—'],
            ],
            [
                ['en' => 'Terms of Payment', 'ar' => 'طريقة الدفع', 'value' => ($doc['payment_method'] ?? null) ?: $primary('Credit', 'آجل')],
                ['en' => 'Supply Date', 'ar' => 'تاريخ التوريد', 'value' => \App\Support\PlatformFormat::date($doc['date'])],
                ['en' => 'Ref. No.', 'ar' => 'رقم المرجع', 'value' => '—'],
            ],
        ];
    @endphp

    <table>
        <tr>
            @if ($showEn)
                <td style="width: {{ $showAr ? '50%' : '100%' }}; vertical-align: top; font-size: 9pt;">
                    <div style="font-weight: bold; font-size: 11pt; color: #0f172a;">{{ $company->name }}</div>
                    @if ($company->address)<div>{{ $company->address }}</div>@endif
                    @if ($company->cr_number)<div>C - R - {{ $company->cr_number }}</div>@endif
                    @if ($company->vat_number)<div>Vat No. : {{ $company->vat_number }}</div>@endif
                </td>
            @endif
            @if ($showAr)
                <td class="ar" style="width: {{ $showEn ? '50%' : '100%' }}; vertical-align: top; font-size: 9pt;">
                    <div style="font-weight: bold; font-size: 11pt; color: #0f172a;">{{ $company->name_ar ?: $company->name }}</div>
                    @if ($company->address)<div>{{ $company->address }}</div>@endif
                    @if ($company->cr_number)<div>{{ $company->cr_number }} - س.ت</div>@endif
                    @if ($company->vat_number)<div>: الرقم الضريبي {{ $company->vat_number }}</div>@endif
                </td>
            @endif
        </tr>
    </table>

    <table style="margin-top: {{ $headerGap }}px;">
        <tr>
            <td style="width: 50%;">
                <table style="border: 0.75pt solid #d6a419; background-color: #fef9ec;">
                    <tr><td class="text-center" style="padding: 8px 20px;">
                        @if ($showAr)<div class="ar" style="font-weight: bold; color: #0f172a;">{{ $doc['type_label_ar'] ?? 'فاتورة ضريبية' }}</div>@endif
                        @if ($showEn)<div style="font-weight: 600; color: #334155;">{{ $doc['type_label'] }}</div>@endif
                    </td></tr>
                </table>
            </td>
            <td class="text-end" style="width: 50%; vertical-align: middle; font-weight: bold; font-size: 9.5pt; color: #0f172a;">{{ $lbl('Invoice No') }} : {{ $doc['number'] }}</td>
        </tr>
    </table>

    <table style="margin-top: {{ $notesGap }}px; border: 0.75pt dashed #94a3b8;">
        @foreach ($dcInfoRows as $i => $row)
            <tr style="{{ $i > 0 ? 'border-top: 0.5pt dashed #cbd5e1;' : '' }}">
                @foreach ($row as $j => $cell)
                    <td style="{{ $dcCell }} {{ $j < count($row) - 1 ? 'border-right: 0.5pt dashed #cbd5e1;' : '' }}">
                        @if ($showEn)<span style="color: #64748b;">{{ $cell['en'] }} :</span>@endif
                        <span style="font-weight: bold; color: #0f172a;">{{ $cell['value'] }}</span>
                        @if ($showAr)<span class="ar" style="color: #64748b;">: {{ $cell['ar'] }}</span>@endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <table style="margin-top: {{ $notesGap }}px; border: 0.75pt solid #94a3b8;">
        <thead>
            <tr style="background-color: #f1f5f9; color: #334155;">
                <th style="padding: 3px; font-size: 7pt; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Sr.') }}</th>
                <th style="padding: 3px; font-size: 7pt; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Item Code') }}</th>
                <th class="text-start" style="padding: 3px; font-size: 7pt; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Description') }}</th>
                <th style="padding: 3px; font-size: 7pt; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Qty') }}</th>
                @if ($showUnitLabels)<th style="padding: 3px; font-size: 7pt; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Unit') }}</th>@endif
                <th class="text-end" style="padding: 3px; font-size: 7pt; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Price') }}</th>
                <th class="text-end" style="padding: 3px; font-size: 7pt; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('Total') }}</th>
                @if ($showVatColumn)
                    <th class="text-end" style="padding: 3px; font-size: 7pt; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('VAT %') }}</th>
                    <th class="text-end" style="padding: 3px; font-size: 7pt; border-right: 0.5pt solid #cbd5e1;">{{ $lbl('VAT Value') }}</th>
                @endif
                <th class="text-end" style="padding: 3px; font-size: 7pt;">{{ $lbl('Total With VAT') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $index => $line)
                <tr style="border-top: 0.5pt solid #e2e8f0;">
                    <td class="text-center" style="padding: 3px; border-right: 0.5pt solid #f1f5f9;">{{ $index + 1 }}</td>
                    <td class="text-center" style="padding: 3px; border-right: 0.5pt solid #f1f5f9;">{{ $line->item?->sku ?: '—' }}</td>
                    <td style="padding: 3px; border-right: 0.5pt solid #f1f5f9;">
                        {{ $primary($line->description, $line->name_ar) }}
                        @if ($showItemDescription && $secondary($line->name_ar))<div class="ar" style="font-size: 7pt; color: #64748b;">{{ $line->name_ar }}</div>@endif
                    </td>
                    <td class="text-center" style="padding: 3px; border-right: 0.5pt solid #f1f5f9;">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                    @if ($showUnitLabels)<td class="text-center" style="padding: 3px; border-right: 0.5pt solid #f1f5f9;">{{ ($line->unit?->symbol ?: $line->unit?->nameFor(app()->getLocale())) ?? $line->item?->unit ?? '—' }}</td>@endif
                    <td class="text-end" style="padding: 3px; border-right: 0.5pt solid #f1f5f9;">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="text-end" style="padding: 3px; border-right: 0.5pt solid #f1f5f9;">{{ number_format($line->quantity * $line->unit_price, 2) }}</td>
                    @if ($showVatColumn)
                        <td class="text-end" style="padding: 3px; border-right: 0.5pt solid #f1f5f9;">{{ rtrim(rtrim(number_format($line->vat_rate, 2), '0'), '.') }}</td>
                        <td class="text-end" style="padding: 3px; border-right: 0.5pt solid #f1f5f9;">{{ number_format($line->vat_amount, 2) }}</td>
                    @endif
                    <td class="text-end" style="padding: 3px; font-weight: bold;">{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: {{ $notesGap }}px;">
        <tr>
            <td style="width: 33%; vertical-align: top;">
                <table style="border: 0.5pt solid #cbd5e1;">
                    <tr style="border-bottom: 0.5pt solid #e2e8f0;"><td style="{{ $dcCell }} font-weight: bold;">{{ number_format($doc['subtotal'], 2) }}</td><td class="text-end" style="{{ $dcCell }} color: #64748b;">{{ $lbl('Amount') }}</td></tr>
                    <tr style="border-bottom: 0.5pt solid #e2e8f0;"><td style="{{ $dcCell }} font-weight: bold;">{{ number_format($doc['discount_total'] ?? 0, 2) }}</td><td class="text-end" style="{{ $dcCell }} color: #64748b;">{{ $lbl('Discount') }}</td></tr>
                    <tr><td style="{{ $dcCell }} font-weight: bold;">{{ number_format($doc['subtotal'] - ($doc['discount_total'] ?? 0), 2) }}</td><td class="text-end" style="{{ $dcCell }} color: #64748b;">{{ $lbl('Amount After Discount') }}</td></tr>
                </table>
            </td>
            <td style="width: 1%;"></td>
            <td style="width: 33%; vertical-align: top;">
                <table style="border: 0.5pt solid #cbd5e1;">
                    <tr style="border-bottom: 0.5pt solid #e2e8f0;"><td style="{{ $dcCell }} font-weight: bold;">{{ rtrim(rtrim(number_format($dcTotalQty, 2), '0'), '.') }}</td><td class="text-end" style="{{ $dcCell }} color: #64748b;">{{ $lbl('Total Qty') }}</td></tr>
                    <tr style="border-bottom: 0.5pt solid #e2e8f0;"><td style="{{ $dcCell }} font-weight: bold;">{{ number_format($dcPaid, 2) }}</td><td class="text-end" style="{{ $dcCell }} color: #64748b;">{{ $lbl('Payment') }}</td></tr>
                    <tr><td style="{{ $dcCell }} font-weight: bold;">{{ number_format($dcBalance, 2) }}</td><td class="text-end" style="{{ $dcCell }} color: #64748b;">{{ $lbl('Cust Balance') }}</td></tr>
                </table>
            </td>
            <td style="width: 1%;"></td>
            <td style="width: 33%; vertical-align: top;">
                <table style="border: 0.5pt solid #cbd5e1;">
                    <tr style="border-bottom: 0.5pt solid #e2e8f0;"><td style="{{ $dcCell }} font-weight: bold;">0.00</td><td class="text-end" style="{{ $dcCell }} color: #64748b;">{{ $lbl('Shipping Cost') }}</td></tr>
                    <tr style="border-bottom: 0.5pt solid #e2e8f0;"><td style="{{ $dcCell }} font-weight: bold;">0.00</td><td class="text-end" style="{{ $dcCell }} color: #64748b;">{{ $lbl('Other expenses') }}</td></tr>
                    <tr><td style="{{ $dcCell }} font-weight: bold;">{{ number_format($doc['vat_total'], 2) }}</td><td class="text-end" style="{{ $dcCell }} color: #64748b;">{{ $dcVatLabel }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 4px; border: 0.5pt solid #cbd5e1;">
        <tr>
            <td style="width: 78%; padding: 6px 10px; border-right: 0.5pt solid #cbd5e1;">
                @if ($showAr)<div class="ar">{{ \App\Support\NumberToWords::arabicRiyals($doc['total']) }}</div>@endif
                @if ($showEn)<div>{{ \App\Support\NumberToWords::sar($doc['total'], $doc['currency'] ?? 'SAR') }}</div>@endif
            </td>
            <td class="text-center" style="width: 22%; font-weight: bold; color: #64748b; font-size: 8pt;">{{ $lbl('Writing Amount') }} :</td>
        </tr>
    </table>

    @if ($dcBank)
        <table style="margin-top: 4px; border: 0.5pt solid #cbd5e1;">
            <tr><td class="text-center" style="padding: 5px; font-weight: bold; font-size: 8.5pt;">{{ $lbl('IBAN') }} - {{ $dcBank->iban }} @if ($dcBank->bank_name) {{ $dcBank->bank_name }} @endif</td></tr>
        </table>
    @endif

    <table style="margin-top: {{ $signatureGap }}px;">
        <tr>
            <td style="width: 33%; vertical-align: top; font-size: 8pt;">
                <div style="font-weight: bold; color: #334155;">{{ $lbl('Received By') }} :</div>
                <div style="margin-top: 10px; color: #64748b;">{{ $lbl('Name') }} : ______________________</div>
                <div style="margin-top: 10px; color: #64748b;">{{ $lbl('Signature') }} : ______________________</div>
            </td>
            <td class="text-center" style="width: 34%; vertical-align: top;">
                @if (!empty($doc['qr_code']))
                    <img src="data:image/png;base64,{{ $doc['qr_code'] }}" style="width: 120px; height: 120px;" alt="">
                @endif
            </td>
            <td class="text-end" style="width: 33%; vertical-align: top; font-size: 8pt;">
                <div style="font-weight: bold; color: #334155;">{{ $lbl('Issued By') }} :</div>
                <div style="margin-top: 10px; color: #64748b;">{{ $lbl('Name') }} : {{ $doc['salesperson']->name ?? '______________________' }}</div>
                <div style="margin-top: 10px; color: #64748b;">{{ $lbl('Signature') }} : ______________________</div>
            </td>
        </tr>
    </table>

    <table style="margin-top: {{ $footerGap + 4 }}px;"><tr><td class="footer-note">{{ __('Page 1 of 1') }}</td></tr></table>

@else
    {{-- minimal / bordered / boxed — a single professional foundation
         shared by all three, differentiated only by the left accent
         stripe (bordered) and the fully accent-filled totals card
         (boxed). This is what every zero-config company sees, including
         every real ZATCA tax invoice PlatformInvoiceService generates
         for a subscription payment — so it needed to look like a real
         business's invoice, not a bare table. --}}
    <table style="margin-bottom: 14px;"><tr><td style="height: 4px; background-color: {{ $accent }};"></td></tr></table>

    <table style="@if ($layout === 'bordered') border-left: 3pt solid {{ $accent }}; @endif">
        <tr>
            <td style="width: 60%; vertical-align: top; @if ($layout === 'bordered') padding-left: 10px; @endif">
                @if ($showLogo && $logoData)
                    <img src="{{ $logoData }}" style="height: 48px; margin-bottom: 8px;" alt="">
                @endif
                <div style="font-size: 16pt; font-weight: bold; color: #0f172a;">{{ $primary($company->name, $company->name_ar) }}</div>
                @if ($secondary($company->name_ar))<div class="ar" style="font-weight: 600;">{{ $company->name_ar }}</div>@endif
                @if ($company->vat_number)
                    <table style="margin-top: 5px;"><tr><td style="background-color: #f1f5f9; color: #475569; font-size: 8pt; font-weight: bold; padding: 3px 9px;">{{ $lbl('VAT') }} {{ $company->vat_number }}</td></tr></table>
                @endif
                @if ($company->address)<div class="muted" style="margin-top: 4px;">{{ $company->address }}</div>@endif
            </td>
            <td style="width: 40%; vertical-align: top; text-align: right;">
                <table style="margin-left: auto;"><tr><td style="background-color: {{ $accent }}; color: #ffffff; font-size: 11pt; font-weight: bold; padding: 6px 14px; border-radius: 999px;">{{ $primary($doc['type_label'], $doc['type_label_ar'] ?? null) }}</td></tr></table>
                @if ($secondary($doc['type_label_ar'] ?? null))<div class="muted ar" style="margin-top: 4px;">{{ $doc['type_label_ar'] }}</div>@endif
                <div style="margin-top: 6px; font-weight: bold; color: #334155;">{{ $doc['number'] }}</div>
                <div class="muted">{{ $doc['date_label'] }}: {{ $issuedDisplay }}</div>
                @if (!empty($doc['date2']))<div class="muted">{{ $doc['date2_label'] }}: {{ \App\Support\PlatformFormat::date($doc['date2']) }}</div>@endif
                @if (!empty($doc['payment_method']))<div class="muted">{{ $lbl('Payment method') }}: {{ $doc['payment_method'] }}</div>@endif
            </td>
        </tr>
    </table>

    @include('documents.print.pdf-payment-status-badge', ['doc' => $doc])

    <table style="margin-top: {{ $notesGap }}px;">
        <tr>
            <td style="width: 49%; vertical-align: top; background-color: #f8fafc; border: 0.5pt solid #f1f5f9; border-radius: 14px; padding: 3px 6px; font-size: 8.5pt;">
                <div style="font-size: 7.5pt; font-weight: bold; text-transform: uppercase; color: #94a3b8;">{{ $primary($doc['party_label'], $doc['party_label_ar'] ?? null) }}</div>
                <div style="margin-top: 2px; font-weight: bold; color: #1e293b;">{{ $primary($doc['party']->name, $doc['party']->name_ar ?? null) }}</div>
                @if ($secondary($doc['party']->name_ar ?? null))<div class="ar" style="color: #64748b;">{{ $doc['party']->name_ar }}</div>@endif
                @if ($showPartyVatNumber && $doc['party']->vat_number)<div style="margin-top: 1px; color: #64748b;">{{ $lbl('VAT') }}: {{ $doc['party']->vat_number }}</div>@endif
                @if (method_exists($doc['party'], 'fullAddress') && $doc['party']->fullAddress())<div style="color: #64748b;">{{ $doc['party']->fullAddress() }}</div>@endif
                @if (!empty($doc['party']->email))<div style="color: #64748b;">{{ $doc['party']->email }}</div>@endif
            </td>
        </tr>
    </table>

    <table style="margin-top: {{ $sectionGap }}px; border: 0.5pt solid #e2e8f0;">
        <thead>
            <tr style="text-align: left; color: #ffffff; font-size: 9pt; background-color: {{ $tableHeaderColor ?: $accent }};">
                <th style="padding: 2px 6px; border-right: 0.5pt solid rgba(255,255,255,0.4);">{{ $lbl('Description') }}</th>
                <th class="text-end" style="padding: 2px 6px; width: 10%; border-right: 0.5pt solid rgba(255,255,255,0.4);">{{ $lbl('Qty') }}</th>
                <th class="text-end" style="padding: 2px 6px; width: 18%; @if ($showVatColumn) border-right: 0.5pt solid rgba(255,255,255,0.4); @endif">{{ $lbl('Unit price') }}</th>
                @if ($showVatColumn)
                    <th class="text-end" style="padding: 2px 6px; width: 17%; border-right: 0.5pt solid rgba(255,255,255,0.4);">{{ $lbl('VAT') }}</th>
                @endif
                <th class="text-end" style="padding: 2px 6px; width: 16%;">{{ $lbl('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $i => $line)
                <tr style="border-bottom: 0.5pt solid #f1f5f9; font-size: 8.5pt; @if ($i % 2 === 1) background-color: #f8fafc; @endif">
                    <td style="padding: 2px 6px; border-right: 0.5pt solid #e2e8f0;">
                        {{ $primary($line->description, $line->name_ar) }}
                        @if ($secondary($line->name_ar))<div class="ar" style="font-size: 8pt;">{{ $line->name_ar }}</div>@endif
                        @if ($showItemDescription && !empty($line->item_description))<div class="muted" style="font-size: 8pt;">{{ $line->item_description }}</div>@endif
                    </td>
                    <td class="text-end" style="padding: 2px 6px; border-right: 0.5pt solid #e2e8f0;">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }} @if ($showUnitLabels)<span class="muted">{{ ($line->unit?->symbol ?: $line->unit?->nameFor(app()->getLocale())) ?? $line->item?->unit }}</span>@endif</td>
                    <td class="text-end" style="padding: 2px 6px; @if ($showVatColumn) border-right: 0.5pt solid #e2e8f0; @endif">{{ \App\Support\Money::format($line->unit_price) }}</td>
                    @if ($showVatColumn)
                        <td class="text-end" style="padding: 2px 6px; border-right: 0.5pt solid #e2e8f0;">{{ \App\Support\Money::format($line->vat_amount) }}</td>
                    @endif
                    <td class="text-end" style="padding: 2px 6px; font-weight: bold; color: #0f172a;">{{ \App\Support\Money::format($line->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php $boxed = $layout === 'boxed'; @endphp
    <table style="margin-top: {{ $sectionGap }}px;">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%; border: 0.5pt solid #e2e8f0; border-radius: 14px; @if ($boxed) background-color: {{ $totalsColor }}; border-color: {{ $totalsColor }}; @endif padding: 8px 10px;">
                <table>
                    <tr><td style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">{{ $lbl('Subtotal') }}</td><td class="text-end" style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">{{ \App\Support\Money::format($doc['subtotal']) }}</td></tr>
                    @if (($doc['discount_total'] ?? 0) > 0)
                        <tr><td style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">{{ $lbl('Discount') }}@if (! empty($doc['discount_percent'])) ({{ rtrim(rtrim(number_format($doc['discount_percent'], 2), '0'), '.') }}%)@endif</td><td class="text-end" style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">-{{ \App\Support\Money::format($doc['discount_total']) }}</td></tr>
                    @endif
                    <tr><td style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">{{ $lbl('VAT') }}</td><td class="text-end" style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">{{ \App\Support\Money::format($doc['vat_total']) }}</td></tr>
                    @if ($boxed)
                        <tr><td style="padding: 4px 0 2px; font-weight: bold; font-size: 11pt; border-top: 1pt solid #ffffff; color: #ffffff;">{{ $lbl('Total') }}</td><td class="text-end" style="padding: 4px 0 2px; font-weight: bold; font-size: 11pt; border-top: 1pt solid #ffffff; color: #ffffff;">{{ \App\Support\Money::format($doc['total']) }}</td></tr>
                    @else
                        <tr><td colspan="2" style="padding: 4px 0 0;"><table style="background-color: {{ $totalsColor }};"><tr><td style="padding: 2px 6px; font-weight: bold; font-size: 11pt; color: #ffffff; border-radius: 10px;">{{ $lbl('Total') }}</td><td class="text-end" style="padding: 2px 6px; font-weight: bold; font-size: 11pt; color: #ffffff; border-radius: 10px;">{{ \App\Support\Money::format($doc['total']) }}</td></tr></table></td></tr>
                    @endif
                    @foreach ($doc['extra_rows'] ?? [] as $row)
                        @php
                            $rowColor = match ($row['variant'] ?? null) { 'red' => '#dc2626', 'green' => $boxed ? '#a7f3d0' : '#059669', default => $boxed ? '#ffffff' : '#64748b' };
                            $rowWeight = ! empty($row['emphasis']) ? 'font-weight: bold;' : '';
                        @endphp
                        <tr>
                            <td style="padding: 3px 0; color: {{ $rowColor }}; {{ $rowWeight }}">
                                {{ $primary($row['label'], $row['label_ar'] ?? null) }}
                                @if ($secondary($row['label_ar'] ?? null))<div class="ar" style="font-size: 8pt;">{{ $row['label_ar'] }}</div>@endif
                            </td>
                            <td class="text-end" style="padding: 3px 0; color: {{ $rowColor }}; {{ $rowWeight }}">{{ \App\Support\Money::format($row['value']) }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    @if ($bankAccounts->isNotEmpty())
        @php $ba = $bankAccounts->first(); @endphp
        <table style="margin-top: {{ $notesGap }}px;">
            <tr><td style="background-color: #f8fafc; border: 0.5pt solid #f1f5f9; border-radius: 14px; padding: 3px 6px; font-size: 8.5pt;">
                <div style="font-size: 7.5pt; font-weight: bold; text-transform: uppercase; color: #94a3b8; margin-bottom: 1px;">{{ $lbl('Payment details') }}</div>
                <div style="color: #64748b;">{{ $ba->name }}@if ($ba->bank_name) — {{ $ba->bank_name }} @endif @if ($ba->iban) — {{ $lbl('IBAN') }}: {{ $ba->iban }}@endif</div>
            </td></tr>
        </table>
    @endif

    @include('documents.print.pdf-notes-stamp', ['doc' => $doc, 'template' => $template, 'stampData' => $stampData, 'stampSize' => $stampSizeOverride ?: 70])

    @include('documents.print.pdf-signature', ['signatureGap' => 2])

    {{-- Table-wrapped, not a bare trailing <div> — see the matching
         comment on bilingual_classic's own footer above for why. --}}
    <table style="margin-top: 2px;"><tr><td class="footer-note">
        {{ $company->name }}@if ($company->name_ar) — <span class="ar">{{ $company->name_ar }}</span>@endif &nbsp;·&nbsp; {{ $doc['number'] }}
    </td></tr></table>
@endif

@if ($tplBorder)
</div>
@endif

</body>
</html>
