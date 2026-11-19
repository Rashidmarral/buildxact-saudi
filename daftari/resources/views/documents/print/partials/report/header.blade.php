{{--
    Navy header band: logo + company name + bilingual report title.
    $titleEn/$titleAr required; $subtitleEn/$subtitleAr optional (e.g.
    "Q3 2026" or a tab label).

    An optional $template (any report PDF's own Invoice Template, by
    whatever document_type it was called with — e.g. 'vat_return',
    'trial_balance') set to the 'custom_letterhead' layout with a
    letterhead image replaces this whole band with that company's own
    full-bleed header, registered as mPDF's own repeating
    <htmlpageheader> so it repeats on every page a report spans — the
    same mechanism letter-pdf.blade.php/account-statement.blade.php
    already use for their own document types, centralized here once
    since every report PDF includes this same partial right after
    <body> already.
--}}
@php
    $subtitleEn = $subtitleEn ?? null;
    $subtitleAr = $subtitleAr ?? null;
    $template = $template ?? null;
    $reportLetterheadData = $embed($template->letterhead_path ?? null);
@endphp
@if (($template->layout ?? null) === 'custom_letterhead' && $reportLetterheadData)
    <htmlpageheader name="report-letterhead">
        <img src="{{ $reportLetterheadData }}" style="width: 100%;" alt="">
    </htmlpageheader>
    <sethtmlpageheader name="report-letterhead" page="ALL" show-this-page="1" />
@else
    <table class="header-band">
        <tr>
            <td style="width: 60%;">
                @if ($logoData ?? null)<img src="{{ $logoData }}" style="height: 26px; margin-bottom: 6px;" alt="">@endif
                <div class="header-company">{{ $company->name }}</div>
                <div class="header-title">{{ $titleEn }}</div>
                @if ($subtitleEn)<div class="header-sub">{{ strtoupper($subtitleEn) }}</div>@endif
            </td>
            <td style="width: 40%;" class="ar">
                @if ($company->name_ar)<div class="header-company">{{ $company->name_ar }}</div>@endif
                <div class="header-title">{{ $titleAr }}</div>
                @if ($subtitleAr)<div class="header-sub ar">{{ $subtitleAr }}</div>@endif
            </td>
        </tr>
    </table>
@endif
