{{--
    mPDF-compatible bilingual letter/agreement PDF. Reuses the template's
    cosmetic fields (logo, footer, page size) exactly like voucher-pdf/
    project-cash-flow-pdf — it does not branch on $template->layout for
    those, except for one: 'custom_letterhead' swaps the auto-generated
    bilingual header for the company's own uploaded letterhead image
    (same optional-letterhead mechanism pdf.blade.php already offers
    invoices — every company can upload its own, or leave this layout
    unset and keep the auto-generated header). It DOES also branch on
    layout for the bilingual arrangement: 'bilingual_stacked' renders
    the full English section then the full Arabic section; anything
    else (including the default 'bilingual_side_by_side') renders each
    paragraph as an EN|AR table row, the same two-column technique as
    pdf-bilingual-header.blade.php. $letter->language_mode overrides
    both when set to a single language.

    The letterhead image and footer image are registered as mPDF's own
    repeating <htmlpageheader>/<htmlpagefooter> (not a plain inline
    <img>) so they're pinned to the top/bottom of EVERY page a letter
    spans, instead of just flowing once wherever the content happens to
    end — which, for any letter long enough to reach a second page,
    used to land the footer mid-page instead of at the bottom. mPDF's
    setAutoTopMargin/setAutoBottomMargin (see MpdfRenderer::makeMpdf())
    grow the page margins to match, so body content never runs under
    either image.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $footerData = $embed($template->footer_path ?? null);
    $letterheadData = $embed($template->letterhead_path ?? null);
    $layout = $template->layout ?? null;
    $stacked = $layout === 'bilingual_stacked';
    $showEn = $letter->language_mode !== 'arabic_only';
    $showAr = $letter->language_mode !== 'english_only';
    $bilingual = $showEn && $showAr;
    // The Cairo font used for Arabic has no glyph for a few Latin
    // punctuation marks seen in free-typed content (matches the guard
    // already used by project-cash-flow-pdf.blade.php for '→').
    $safe = fn (?string $text) => str_replace('→', '->', (string) $text);
    $partyBDetails = $letter->partyBDetails();
    $nl2br = fn (?string $text) => nl2br(e($safe($text)));
    // Saudi-style agreements commonly number every clause inline within
    // one run of text ("1.1 ... 1.2 ... 1.3 ..." all typed as a single
    // paragraph, not split into separate blocks) — without this, that
    // reads as one undifferentiated paragraph with no visual break
    // between points. A sentence-ending period immediately followed by
    // a new "N.N " clause number is specific enough to break onto its
    // own line without false-triggering on an ordinary decimal (a price
    // like "2,944.00" has no preceding ". " to match, and mid-sentence
    // figures like "SAR 7,000" have no decimal point at all).
    $splitPoints = fn (?string $text) => preg_replace('/(?<=\.)\s(?=\d{1,2}\.\d{1,2}\s)/u', "\n", (string) $text);
    $withPointBreaks = fn (?string $text) => nl2br(e($safe($splitPoints($text))));
    $arabicOnly = $letter->language_mode === 'arabic_only';
    // A static two-language caption (never the letter's own free-typed
    // content, so always safe to hardcode the Arabic side like every
    // other fixed label in this file): both languages in bilingual
    // mode, just the one language otherwise.
    $bilabel = fn (string $en, string $ar) => $bilingual ? $en.' / '.$ar : ($showEn ? $en : $ar);
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: 9.5pt; line-height: 1.5; }
    table { border-collapse: collapse; width: 100%; }
    .ar { direction: rtl; text-align: right; }
    .info-table td { padding: 5px 8px; border: 0.5pt solid #e2e8f0; }
    .info-table .k { color: #64748b; font-size: 8pt; }
    .title-bar { background: #0f172a; color: #fff; padding: 8px 10px; font-size: 11pt; font-weight: bold; margin-top: 14px; }
    .title-bar.split td { width: 50%; }
    .body-table td { vertical-align: top; padding: 8px; border-bottom: 0.5pt solid #e2e8f0; }
    .body-table td.heading { font-weight: bold; color: #0f172a; }
    .section-heading { font-weight: bold; color: #0f172a; margin-top: 12px; }
    .sig-table { margin-top: 24px; }
    .sig-table td { width: 50%; vertical-align: top; padding: 0; border: 0.75pt solid #cbd5e1; }
    .sig-table .sig-header { background: #0f172a; color: #fff; font-weight: bold; font-size: 9pt; letter-spacing: 0.4pt; text-transform: uppercase; padding: 7px 14px; }
    .sig-body { padding: 16px 14px 14px; }
    .sig-party-name { font-weight: bold; font-size: 10.5pt; color: #0f172a; }
    .sig-fields { margin-top: 10px; }
    .sig-fields td { border: none; padding: 5px 0; }
    .sig-fields .sig-top-blank { border-bottom: 0.75pt solid #475569; padding-top: 24px; }
    .sig-fields .sig-caption-cell { font-size: 7.5pt; color: #94a3b8; letter-spacing: 0.3pt; text-transform: uppercase; padding-top: 3px; padding-bottom: 12px; }
    .sig-fields .sig-label { width: 78px; color: #64748b; font-size: 8.5pt; white-space: nowrap; vertical-align: bottom; padding-bottom: 4px; }
    .sig-fields.ar .sig-label { width: 68px; }
    .sig-fields .sig-blank { width: 100%; border-bottom: 0.75pt solid #cbd5e1; }
</style>
</head>
<body>

@if ($layout === 'custom_letterhead' && $letterheadData)
    <htmlpageheader name="letterhead">
        <img src="{{ $letterheadData }}" style="width: 100%;" alt="">
    </htmlpageheader>
    <sethtmlpageheader name="letterhead" page="ALL" show-this-page="1" />
@else
    @include('documents.print.pdf-bilingual-header', ['company' => $company, 'logoData' => $logoData, 'showLogo' => true])
@endif

@if ($footerData)
    <htmlpagefooter name="letterfooter">
        <img src="{{ $footerData }}" style="width: 100%;" alt="">
    </htmlpagefooter>
    <sethtmlpagefooter name="letterfooter" page="ALL" />
@endif

{{--
    The primary column always renders — English labels/values normally,
    or the Arabic ones when language_mode is arabic_only (so this table
    is never blank in that mode). The secondary column only appears in
    bilingual mode and is always Arabic.
--}}
<table class="info-table" style="margin-top: 12px;">
    <tr>
        <td style="width: {{ $bilingual ? '25%' : '50%' }};" class="{{ $showEn ? '' : 'ar' }}">
            <div class="k">{{ $showEn ? __('Date') : 'التاريخ' }}</div>{{ $letter->letter_date->format('Y-m-d') }}
        </td>
        @if ($bilingual)
            <td class="ar" style="width: 25%;"><div class="k">التاريخ</div>{{ $letter->letter_date->format('Y-m-d') }}</td>
        @endif
        <td style="width: {{ $bilingual ? '25%' : '50%' }};" class="{{ $showEn ? '' : 'ar' }}">
            <div class="k">{{ $showEn ? __('Reference') : 'المرجع' }}</div>{{ $letter->reference_number }}
        </td>
        @if ($bilingual)
            <td class="ar" style="width: 25%;"><div class="k">المرجع</div>{{ $letter->reference_number }}</td>
        @endif
    </tr>
    <tr>
        <td class="{{ $showEn ? '' : 'ar' }}"><div class="k">{{ $showEn ? __('To') : 'إلى' }}</div>{{ $letter->party_b_name }}</td>
        @if ($bilingual)<td class="ar"><div class="k">إلى</div>{{ $letter->party_b_name }}</td>@endif
        <td class="{{ $showEn ? '' : 'ar' }}"><div class="k">{{ $showEn ? __('Subject') : 'الموضوع' }}</div>{{ $showEn ? $letter->title : $letter->titleAr() }}</td>
        @if ($bilingual)<td class="ar"><div class="k">الموضوع</div>{{ $letter->titleAr() }}</td>@endif
    </tr>
    @if ($partyBDetails)
        <tr>
            <td colspan="{{ $bilingual ? 2 : 1 }}" class="{{ $showEn ? '' : 'ar' }}"><div class="k">{{ $showEn ? __('Their details') : 'بيانات الطرف الثاني' }}</div>{!! $nl2br($partyBDetails) !!}</td>
            @if ($bilingual)<td colspan="2" class="ar"><div class="k">بيانات الطرف الثاني</div>{!! $nl2br($partyBDetails) !!}</td>@endif
        </tr>
    @endif
    @if ($letter->project)
        <tr>
            <td colspan="{{ $bilingual ? 2 : 1 }}" class="{{ $showEn ? '' : 'ar' }}"><div class="k">{{ $showEn ? __('Project') : 'المشروع' }}</div>{{ $letter->project->name }}</td>
            @if ($bilingual)<td colspan="2" class="ar"><div class="k">المشروع</div>{{ $letter->project->name }}</td>@endif
        </tr>
    @endif
    @if ($letter->reference_details)
        <tr>
            <td colspan="{{ $bilingual ? 2 : 1 }}" class="{{ $showEn ? '' : 'ar' }}"><div class="k">{{ $showEn ? __('Reference details') : 'تفاصيل مرجعية' }}</div>{!! $nl2br($letter->reference_details) !!}</td>
            @if ($bilingual)<td colspan="2" class="ar"><div class="k">تفاصيل مرجعية</div>{!! $nl2br($letter->reference_details) !!}</td>@endif
        </tr>
    @endif
</table>

@if ($bilingual)
    <table class="title-bar split"><tr><td>{{ $letter->title }}</td><td class="ar">{{ $letter->titleAr() }}</td></tr></table>
@else
    <div class="title-bar {{ $showAr ? 'ar' : '' }}">{{ $showEn ? $letter->title : $letter->titleAr() }}</div>
@endif

<table class="body-table" style="margin-top: 10px;">
    @foreach ($letter->content as $block)
        @if ($bilingual && ! $stacked)
            <tr>
                <td class="{{ $block['is_heading'] ? 'heading' : '' }}" style="width: 50%; vertical-align: top; padding: 8px; border-bottom: 0.5pt solid #e2e8f0;">{!! $withPointBreaks($block['text_en']) !!}</td>
                <td class="ar {{ $block['is_heading'] ? 'heading' : '' }}" style="width: 50%; vertical-align: top; padding: 8px; border-bottom: 0.5pt solid #e2e8f0;">{!! $withPointBreaks($block['text_ar']) !!}</td>
            </tr>
        @endif
    @endforeach
</table>

@if ($bilingual && $stacked)
    <div class="section-heading">{{ __('English') }}</div>
    @foreach ($letter->content as $block)
        <p class="{{ $block['is_heading'] ? 'heading' : '' }}" style="{{ $block['is_heading'] ? 'font-weight:bold;color:#0f172a;' : '' }}">{!! $withPointBreaks($block['text_en']) !!}</p>
    @endforeach
    <div class="section-heading ar">النص العربي</div>
    @foreach ($letter->content as $block)
        <p class="ar {{ $block['is_heading'] ? 'heading' : '' }}" style="{{ $block['is_heading'] ? 'font-weight:bold;color:#0f172a;' : '' }}">{!! $withPointBreaks($block['text_ar']) !!}</p>
    @endforeach
@elseif (! $bilingual)
    @foreach ($letter->content as $block)
        <p class="{{ $showAr ? 'ar' : '' }} {{ $block['is_heading'] ? 'heading' : '' }}" style="{{ $block['is_heading'] ? 'font-weight:bold;color:#0f172a;' : '' }}">{!! $showAr ? $withPointBreaks($block['text_ar']) : $withPointBreaks($block['text_en']) !!}</p>
    @endforeach
@endif

@php
    $signers = [
        ['role' => $letter->party_a_role, 'name' => $letter->partyAName()],
        ['role' => $letter->party_b_role, 'name' => $letter->party_b_name],
    ];
@endphp
<table class="sig-table">
    <tr>
        @foreach ($signers as $signer)
            <td class="sig-header">{{ $signer['role'] }}{{ $bilingual ? ' / '.$signer['role'] : '' }}</td>
        @endforeach
    </tr>
    <tr>
        @foreach ($signers as $signer)
            <td>
                <div class="sig-body">
                    <div class="sig-party-name">{{ $signer['name'] }}</div>
                    <table class="sig-fields {{ $arabicOnly ? 'ar' : '' }}" {!! $arabicOnly ? 'dir="rtl"' : '' !!}>
                        <tr>
                            <td colspan="2" class="sig-top-blank">&nbsp;</td>
                        </tr>
                        <tr>
                            <td colspan="2" class="sig-caption-cell">{{ $bilabel(__('Signature'), 'التوقيع') }}</td>
                        </tr>
                        <tr>
                            <td class="sig-label">{{ $bilabel(__('Name and title'), 'الاسم والصفة') }}</td>
                            <td class="sig-blank">&nbsp;</td>
                        </tr>
                        <tr>
                            <td class="sig-label">{{ $bilabel(__('Date'), 'التاريخ') }}</td>
                            <td class="sig-blank">&nbsp;</td>
                        </tr>
                    </table>
                </div>
            </td>
        @endforeach
    </tr>
</table>

</body>
</html>
