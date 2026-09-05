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
    $layout = $template->layout ?? 'minimal';
    $showLogo = $template->show_logo ?? true;
    $bankAccounts = $doc['bank_accounts'] ?? (($doc['bank_account'] ?? null) ? collect([$doc['bank_account']]) : collect());
    $logoData = $embed($company->logo_path ?? null);
    $stampData = $embed($company->stamp_path ?? null);
    $letterheadData = $embed($template->letterhead_path ?? null);
    $footerData = $embed($template->footer_path ?? null);

    $languageMode = $template->language_mode ?? 'bilingual';
    $tableDirection = $template->table_direction ?? 'ltr';
    $showEn = $languageMode !== 'arabic_only';
    $showAr = $languageMode !== 'english_only';
    $lbl = fn (string $key) => $languageMode === 'arabic_only'
        ? \Illuminate\Support\Facades\Lang::get($key, [], 'ar')
        : \Illuminate\Support\Facades\Lang::get($key, [], 'en');
    $primary = fn (string $en, ?string $ar = null) => $languageMode === 'arabic_only' && $ar ? $ar : $en;
    $secondary = fn (?string $ar = null) => $showAr && $languageMode !== 'arabic_only' ? $ar : null;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: 10pt; }
    table { border-collapse: collapse; width: 100%; }
    .muted { color: #64748b; font-size: 9pt; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .ar { direction: rtl; text-align: right; }
    .zatca-badge { background-color: #d1fae5; color: #047857; font-size: 8pt; font-weight: bold; padding: 3px 10px; margin-bottom: 4px; }
    /* The Phase 2 QR is a dense 9-tag payload (~450-500 base64 chars,
       version ~25+ even at Quartile EC) — printed too small, its modules
       shrink below what a phone camera can resolve and every scan comes
       back corrupted, even though the underlying data is correct. 85px
       (~22mm at 96dpi) was sized for the old 5-tag Phase 1 QR; Phase 2
       needs meaningfully more physical area for the same module count to
       stay scannable. */
    .qr-img { width: 180px; height: 180px; }
    .footer-note { margin-top: 18px; font-size: 8.5pt; color: #94a3b8; text-align: center; border-top: 0.5pt solid #e2e8f0; padding-top: 8px; }
    .stamp-img { width: 130px; height: 130px; }
    .notes-block { margin-top: 14px; font-size: 9pt; }
</style>
</head>
<body>
{{-- Note: mPDF's table renderer has known instability combining a
     dir="rtl" <body> with certain colspan/border table shapes (a real
     "Undefined array key BORDER-RIGHT" crash was hit here), so
     table_direction only flips column order in the browser-rendered
     documents.print.body view. Arabic text itself still renders
     correctly in the PDF via the per-element dir="rtl" spans below,
     regardless of the body's own direction. --}}

@if ($layout === 'bilingual_classic')
    <table style="border: 1.5pt solid {{ $accent }}; margin-bottom: 12px;"><tr><td style="height: 6px;"></td></tr></table>

    @include('documents.print.pdf-bilingual-header', ['company' => $company, 'showLogo' => $showLogo, 'logoData' => $logoData])

    <table style="margin-top: 16px; border-bottom: 1.5pt solid #1e293b; padding-bottom: 8px;">
        <tr><td style="text-align: center; font-size: 17pt; font-weight: bold; color: #0f172a; padding-bottom: 8px;">
            <span class="ar">{{ $doc['type_label_ar'] ?? '' }}</span> &nbsp; {{ $doc['type_label'] }}
        </td></tr>
    </table>

    <table style="margin-top: 12px; border: 0.5pt solid #cbd5e1;">
        <tr style="border-bottom: 0.5pt solid #cbd5e1;">
            <td style="width: 18%; padding: 6px 8px; font-weight: bold; font-size: 9.5pt;">{{ $doc['party_label'] }}</td>
            <td style="padding: 6px 8px; text-align: center; font-size: 9.5pt;">
                {{ $doc['party']->name }}
                @if (!empty($doc['party']->name_ar))<div class="ar">{{ $doc['party']->name_ar }}</div>@endif
            </td>
            <td class="ar" style="width: 18%; padding: 6px 8px; font-weight: bold; font-size: 9.5pt;">{{ $doc['party_label_ar'] ?? __('Party') }}</td>
        </tr>
        <tr style="border-bottom: 0.5pt solid #cbd5e1;">
            <td style="padding: 6px 8px; font-weight: bold; font-size: 9.5pt;">{{ __('VAT number') }}</td>
            <td style="padding: 6px 8px; text-align: center; font-size: 9.5pt;">{{ $doc['party']->vat_number ?: '—' }}</td>
            <td class="ar" style="padding: 6px 8px; font-weight: bold; font-size: 9.5pt;">رقم التسجيل الضريبي</td>
        </tr>
        @if (method_exists($doc['party'], 'fullAddress') && $doc['party']->fullAddress())
            <tr style="border-bottom: 0.5pt solid #cbd5e1;">
                <td style="padding: 6px 8px; font-weight: bold; font-size: 9.5pt;">{{ __('Address') }}</td>
                <td style="padding: 6px 8px; text-align: center; font-size: 9.5pt;">{{ $doc['party']->fullAddress() }}</td>
                <td class="ar" style="padding: 6px 8px; font-weight: bold; font-size: 9.5pt;">العنوان</td>
            </tr>
        @endif
        <tr>
            <td style="padding: 6px 8px; font-weight: bold; font-size: 9.5pt;">{{ __('Number') }}</td>
            <td style="padding: 6px 8px; text-align: center; font-size: 9.5pt;">{{ $doc['number'] }} | {{ $doc['date_label'] }} {{ \App\Support\PlatformFormat::date($doc['date']) }}</td>
            <td class="ar" style="padding: 6px 8px; font-weight: bold; font-size: 9.5pt;">رقم | التاريخ</td>
        </tr>
    </table>

    <table style="margin-top: 14px;">
        <thead>
            <tr style="border-bottom: 1.5pt solid #1e293b; text-align: left; font-size: 8.5pt;">
                <th style="padding: 6px 2px;">#</th>
                <th style="padding: 6px 2px;">{{ __('Description') }}<br><span class="ar" style="font-weight: normal;">الوصف</span></th>
                <th class="text-end" style="padding: 6px 2px;">{{ __('Qty') }}<br><span class="ar" style="font-weight: normal;">الكمية</span></th>
                <th class="text-end" style="padding: 6px 2px;">{{ __('Price') }}<br><span class="ar" style="font-weight: normal;">السعر</span></th>
                <th class="text-end" style="padding: 6px 2px;">{{ __('Taxable amount') }}<br><span class="ar" style="font-weight: normal;">المبلغ الخاضع للضريبة</span></th>
                <th class="text-end" style="padding: 6px 2px;">{{ __('VAT amount') }}<br><span class="ar" style="font-weight: normal;">القيمة المضافة</span></th>
                <th class="text-end" style="padding: 6px 2px;">{{ __('Line amount') }}<br><span class="ar" style="font-weight: normal;">المجموع</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $index => $line)
                <tr style="border-bottom: 0.5pt solid #e2e8f0;">
                    <td style="padding: 6px 2px; vertical-align: top; color: #64748b;">{{ $index + 1 }}</td>
                    <td style="padding: 6px 2px; vertical-align: top;">
                        <strong>{{ $primary($line->description, $line->item?->name_ar) }}</strong>
                        @if ($secondary($line->item?->name_ar))<div class="ar">{{ $line->item->name_ar }}</div>@endif
                        @if (!empty($line->item?->description))<div class="muted">{{ $line->item->description }}</div>@endif
                    </td>
                    <td class="text-end" style="padding: 6px 2px; vertical-align: top;">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }} <span class="muted">{{ $line->unit?->nameFor(app()->getLocale()) ?? $line->item?->unit }}</span></td>
                    <td class="text-end" style="padding: 6px 2px; vertical-align: top;">{{ number_format($line->unit_price, 2) }}</td>
                    <td class="text-end" style="padding: 6px 2px; vertical-align: top;">{{ number_format($line->quantity * $line->unit_price, 2) }}</td>
                    <td class="text-end" style="padding: 6px 2px; vertical-align: top;">{{ number_format($line->vat_amount, 2) }}<br><span class="muted">{{ rtrim(rtrim(number_format($line->vat_rate, 2), '0'), '.') }}%</span></td>
                    <td class="text-end" style="padding: 6px 2px; vertical-align: top; font-weight: bold;">{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @include('documents.print.pdf-bank-totals', ['doc' => $doc, 'bankAccounts' => $bankAccounts])

    @include('documents.print.pdf-notes-stamp', ['doc' => $doc, 'template' => $template, 'stampData' => $stampData, 'stampSize' => 140])

    <div class="footer-note">
        {{ $company->name }}@if ($company->name_ar) — <span class="ar">{{ $company->name_ar }}</span>@endif &nbsp;·&nbsp; {{ __('Page 1 of 1') }} &nbsp;·&nbsp; {{ $doc['number'] }}
    </div>

@elseif ($layout === 'custom_letterhead')
    @if ($letterheadData)
        <img src="{{ $letterheadData }}" style="width: 100%;" alt="">
    @else
        @include('documents.print.pdf-bilingual-header', ['company' => $company, 'showLogo' => $showLogo, 'logoData' => $logoData])
    @endif

    <table style="margin-top: 12px;">
        <tr><td style="text-align: center; font-size: 12pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt;">
            {{ strtoupper($doc['type_label']) }} <span class="ar" style="font-weight: normal;">/ ({{ $doc['type_label_ar'] ?? '' }})</span>
        </td></tr>
    </table>

    <table style="margin-top: 12px; font-size: 9.5pt;">
        <tr>
            <td style="width: 25%; padding: 3px 0; font-weight: bold; color: #475569;">{{ $doc['party_label'] }}</td>
            <td style="width: 25%; padding: 3px 0;">{{ $doc['party']->name }}</td>
            <td style="width: 25%; padding: 3px 0; font-weight: bold; color: #475569;">{{ $lbl('Number') }}</td>
            <td style="padding: 3px 0;">{{ $doc['number'] }}</td>
        </tr>
        <tr>
            <td style="padding: 3px 0; font-weight: bold; color: #475569;">{{ $doc['date_label'] }}</td>
            <td style="padding: 3px 0;">{{ \App\Support\PlatformFormat::date($doc['date']) }}</td>
            @if (!empty($doc['date2']))
                <td style="padding: 3px 0; font-weight: bold; color: #475569;">{{ $doc['date2_label'] }}</td>
                <td style="padding: 3px 0;">{{ \App\Support\PlatformFormat::date($doc['date2']) }}</td>
            @endif
        </tr>
        @if (!empty($doc['ref_no']))
            <tr><td style="padding: 3px 0; font-weight: bold; color: #475569;">{{ $lbl('Ref No') }}</td><td style="padding: 3px 0;" colspan="3">{{ $doc['ref_no'] }}</td></tr>
        @endif
        @if (method_exists($doc['party'], 'fullAddress') && $doc['party']->fullAddress())
            <tr><td style="padding: 3px 0; font-weight: bold; color: #475569;">{{ $lbl('Address') }}</td><td style="padding: 3px 0;" colspan="3">{{ $doc['party']->fullAddress() }}</td></tr>
        @endif
    </table>

    <table style="margin-top: 12px; border: 0.5pt solid #cbd5e1;">
        <thead>
            <tr style="background-color: #f8fafc; text-align: left; font-size: 8.5pt;">
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
                        {{ $primary($line->description, $line->item?->name_ar) }}
                        @if ($secondary($line->item?->name_ar))<div class="ar">{{ $line->item->name_ar }}</div>@endif
                    </td>
                    <td class="text-end" style="border: 0.5pt solid #cbd5e1; padding: 5px; vertical-align: top;">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }} {{ $line->unit?->nameFor(app()->getLocale()) ?? $line->item?->unit }}</td>
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
                        <tr><td style="padding: 2px 0;">{{ $row['label'] }}</td><td class="text-end" style="padding: 2px 0;">{{ number_format($row['value'], 2) }}</td></tr>
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

    @include('documents.print.pdf-notes-stamp', ['doc' => $doc, 'template' => $template, 'stampData' => $stampData, 'stampSize' => 130])

    <div class="footer-note">{{ $lbl('Page 1 of 1') }}</div>

@else
    {{-- minimal / bordered / boxed --}}
    @if ($layout === 'bordered' && $accent)
        <table style="margin-bottom: 12px;"><tr><td style="height: 4px; background-color: {{ $accent }};"></td></tr></table>
    @endif
    <table style="@if ($layout === 'bordered' && $accent) border-left: 3pt solid {{ $accent }}; @endif">
        <tr>
            <td style="width: 60%; vertical-align: top; @if ($layout === 'bordered' && $accent) padding-left: 10px; @endif">
                @if ($showLogo && $logoData)
                    <img src="{{ $logoData }}" style="height: 42px; margin-bottom: 6px;" alt="">
                @endif
                <div style="font-size: 15pt; font-weight: bold; color: #0f172a;">{{ $primary($company->name, $company->name_ar) }}</div>
                @if ($secondary($company->name_ar))<div class="ar" style="font-weight: 600;">{{ $company->name_ar }}</div>@endif
                @if ($company->vat_number)<div class="muted">{{ $lbl('VAT') }}: {{ $company->vat_number }}</div>@endif
                @if ($company->address)<div class="muted">{{ $company->address }}</div>@endif
            </td>
            <td style="width: 40%; vertical-align: top; text-align: right;">
                <div style="font-size: 13pt; font-weight: bold; color: {{ $layout === 'minimal' && $accent ? $accent : '#0f172a' }};">{{ $primary($doc['type_label'], $doc['type_label_ar'] ?? null) }}</div>
                @if ($secondary($doc['type_label_ar'] ?? null))<div class="muted ar">{{ $doc['type_label_ar'] }}</div>@endif
                <div class="muted">{{ $doc['number'] }}</div>
                <div class="muted">{{ $doc['date_label'] }}: {{ \App\Support\PlatformFormat::date($doc['date']) }}</div>
                @if (!empty($doc['date2']))<div class="muted">{{ $doc['date2_label'] }}: {{ \App\Support\PlatformFormat::date($doc['date2']) }}</div>@endif
            </td>
        </tr>
    </table>

    <table style="margin-top: 16px;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <div style="font-size: 8pt; font-weight: bold; text-transform: uppercase; color: #94a3b8;">{{ $primary($doc['party_label'], $doc['party_label_ar'] ?? null) }}</div>
                <div style="margin-top: 3px; font-weight: bold; color: #1e293b;">{{ $primary($doc['party']->name, $doc['party']->name_ar ?? null) }}</div>
                @if ($secondary($doc['party']->name_ar ?? null))<div class="muted ar">{{ $doc['party']->name_ar }}</div>@endif
                @if ($doc['party']->vat_number)<div class="muted">{{ $lbl('VAT') }}: {{ $doc['party']->vat_number }}</div>@endif
                @if (method_exists($doc['party'], 'fullAddress') && $doc['party']->fullAddress())<div class="muted">{{ $doc['party']->fullAddress() }}</div>@endif
                @if (!empty($doc['party']->email))<div class="muted">{{ $doc['party']->email }}</div>@endif
            </td>
            <td style="width: 50%; vertical-align: top; text-align: right;">
                @if (!empty($doc['zatca_status']))
                    <div class="zatca-badge">{{ $doc['zatca_status'] === 'cleared' ? $lbl('ZATCA Cleared') : $lbl('ZATCA Reported') }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table style="margin-top: 16px;">
        <thead>
            <tr style="border-bottom: 0.5pt solid #e2e8f0; text-align: left; color: #64748b; font-size: 9pt;">
                <th style="padding: 5px 0;">{{ $lbl('Description') }}</th>
                <th class="text-end" style="padding: 5px 0;">{{ $lbl('Qty') }}</th>
                <th class="text-end" style="padding: 5px 0;">{{ $lbl('Unit price') }}</th>
                <th class="text-end" style="padding: 5px 0;">{{ $lbl('VAT') }}</th>
                <th class="text-end" style="padding: 5px 0;">{{ $lbl('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $line)
                <tr style="border-bottom: 0.5pt solid #f1f5f9;">
                    <td style="padding: 5px 0;">
                        {{ $primary($line->description, $line->item?->name_ar) }}
                        @if ($secondary($line->item?->name_ar))<div class="ar" style="font-size: 8pt;">{{ $line->item->name_ar }}</div>@endif
                    </td>
                    <td class="text-end" style="padding: 5px 0;">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }} <span class="muted">{{ $line->unit?->nameFor(app()->getLocale()) ?? $line->item?->unit }}</span></td>
                    <td class="text-end" style="padding: 5px 0;">{{ \App\Support\Money::format($line->unit_price) }}</td>
                    <td class="text-end" style="padding: 5px 0;">{{ \App\Support\Money::format($line->vat_amount) }}</td>
                    <td class="text-end" style="padding: 5px 0;">{{ \App\Support\Money::format($line->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php $boxed = $layout === 'boxed' && $accent; @endphp
    <table style="margin-top: 12px;">
        <tr>
            <td style="width: 55%;"></td>
            <td style="width: 45%; @if ($boxed) background-color: {{ $accent }}; border-radius: 6px; padding: 12px; @endif">
                <table>
                    <tr><td style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">{{ $lbl('Subtotal') }}</td><td class="text-end" style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">{{ \App\Support\Money::format($doc['subtotal']) }}</td></tr>
                    @if (($doc['discount_total'] ?? 0) > 0)
                        <tr><td style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">{{ $lbl('Discount') }}@if (! empty($doc['discount_percent'])) ({{ rtrim(rtrim(number_format($doc['discount_percent'], 2), '0'), '.') }}%)@endif</td><td class="text-end" style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">-{{ \App\Support\Money::format($doc['discount_total']) }}</td></tr>
                    @endif
                    <tr><td style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">{{ $lbl('VAT') }}</td><td class="text-end" style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">{{ \App\Support\Money::format($doc['vat_total']) }}</td></tr>
                    <tr><td style="padding: 6px 0 2px; font-weight: bold; font-size: 11pt; border-top: 1pt solid {{ $boxed ? '#ffffff' : '#0f172a' }}; @if ($boxed) color: #ffffff; @endif">{{ $lbl('Total') }}</td><td class="text-end" style="padding: 6px 0 2px; font-weight: bold; font-size: 11pt; border-top: 1pt solid {{ $boxed ? '#ffffff' : '#0f172a' }}; @if ($boxed) color: #ffffff; @endif">{{ \App\Support\Money::format($doc['total']) }}</td></tr>
                    @foreach ($doc['extra_rows'] ?? [] as $row)
                        <tr><td style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">{{ $row['label'] }}</td><td class="text-end" style="padding: 2px 0; @if ($boxed) color: #ffffff; @else color: #64748b; @endif">{{ \App\Support\Money::format($row['value']) }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    @if ($bankAccounts->isNotEmpty())
        @php $ba = $bankAccounts->first(); @endphp
        <div style="margin-top: 16px; padding-top: 8px; border-top: 0.5pt solid #f1f5f9; font-size: 9pt;">
            <div style="font-size: 8pt; font-weight: bold; text-transform: uppercase; color: #94a3b8; margin-bottom: 3px;">{{ $lbl('Payment details') }}</div>
            <div class="muted">{{ $ba->name }}@if ($ba->bank_name) — {{ $ba->bank_name }} @endif @if ($ba->iban) — {{ $lbl('IBAN') }}: {{ $ba->iban }}@endif</div>
        </div>
    @endif

    @include('documents.print.pdf-notes-stamp', ['doc' => $doc, 'template' => $template, 'stampData' => $stampData, 'stampSize' => 120])
@endif

@if ($template && $template->show_signature)
    <table style="margin-top: 40px;">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%; text-align: center;">
                <div style="border-bottom: 0.75pt solid #94a3b8; height: 40px;"></div>
                <div class="muted" style="margin-top: 6px;">{{ $primary($template->signature_label_en ?: __('Authorized Signature'), $template->signature_label_ar) }}</div>
                @if ($secondary($template->signature_label_ar))<div class="muted ar">{{ $template->signature_label_ar }}</div>@endif
            </td>
        </tr>
    </table>
@endif

@if ($footerData)
    <img src="{{ $footerData }}" style="width: 100%; margin-top: 20px;" alt="">
@endif

</body>
</html>
