{{--
    mPDF-compatible bilingual letter/agreement PDF. Reuses the template's
    cosmetic fields (logo, footer, page size) exactly like voucher-pdf/
    project-cash-flow-pdf — it does not branch on $template->layout for
    those. It DOES branch on layout for the bilingual arrangement:
    'bilingual_stacked' renders the full English section then the full
    Arabic section; anything else (including the default
    'bilingual_side_by_side') renders each paragraph as an EN|AR table
    row, the same two-column technique as pdf-bilingual-header.blade.php.
    $letter->language_mode overrides both when set to a single language.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $footerData = $embed($template->footer_path ?? null);
    $stacked = ($template->layout ?? null) === 'bilingual_stacked';
    $showEn = $letter->language_mode !== 'arabic_only';
    $showAr = $letter->language_mode !== 'english_only';
    $bilingual = $showEn && $showAr;
    // The Cairo font used for Arabic has no glyph for a few Latin
    // punctuation marks seen in free-typed content (matches the guard
    // already used by project-cash-flow-pdf.blade.php for '→').
    $safe = fn (?string $text) => str_replace('→', '->', (string) $text);
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: 9.5pt; line-height: 1.5; }
    table { border-collapse: collapse; width: 100%; }
    .ar { direction: rtl; text-align: right; }
    .muted { color: #64748b; font-size: 8.5pt; }
    .info-table td { padding: 5px 8px; border: 0.5pt solid #e2e8f0; }
    .info-table .k { color: #64748b; font-size: 8pt; }
    .title-bar { background: #0f172a; color: #fff; padding: 8px 10px; font-size: 11pt; font-weight: bold; margin-top: 14px; }
    .title-bar.split td { width: 50%; }
    .body-table td { vertical-align: top; padding: 8px; border-bottom: 0.5pt solid #e2e8f0; }
    .body-table td.heading { font-weight: bold; color: #0f172a; }
    .section-heading { font-weight: bold; color: #0f172a; margin-top: 12px; }
    .sig-table td { width: 50%; vertical-align: top; padding: 10px; border: 0.5pt solid #e2e8f0; }
    .sig-table .sig-header { background: #f1f5f9; font-weight: bold; padding: 6px 8px; }
    .sig-line { margin-top: 22px; border-top: 0.5pt solid #94a3b8; padding-top: 3px; font-size: 8.5pt; color: #64748b; }
</style>
</head>
<body>

@include('documents.print.pdf-bilingual-header', ['company' => $company, 'logoData' => $logoData, 'showLogo' => true])

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
        <td class="{{ $showEn ? '' : 'ar' }}"><div class="k">{{ $showEn ? __('Subject') : 'الموضوع' }}</div>{{ $letter->title }}</td>
        @if ($bilingual)<td class="ar"><div class="k">الموضوع</div>{{ $letter->title }}</td>@endif
    </tr>
    @if ($letter->project)
        <tr>
            <td colspan="{{ $bilingual ? 2 : 1 }}" class="{{ $showEn ? '' : 'ar' }}"><div class="k">{{ $showEn ? __('Project') : 'المشروع' }}</div>{{ $letter->project->name }}</td>
            @if ($bilingual)<td colspan="2" class="ar"><div class="k">المشروع</div>{{ $letter->project->name }}</td>@endif
        </tr>
    @endif
</table>

@if ($bilingual)
    <table class="title-bar split"><tr><td>{{ $letter->title }}</td><td class="ar">{{ $letter->title }}</td></tr></table>
@else
    <div class="title-bar {{ $showAr ? 'ar' : '' }}">{{ $letter->title }}</div>
@endif

<table style="margin-top: 10px;">
    @foreach ($letter->content as $block)
        @if ($bilingual && ! $stacked)
            <tr>
                <td class="{{ $block['is_heading'] ? 'heading' : '' }}" style="width: 50%; vertical-align: top; padding: 8px; border-bottom: 0.5pt solid #e2e8f0;">{{ $safe($block['text_en']) }}</td>
                <td class="ar {{ $block['is_heading'] ? 'heading' : '' }}" style="width: 50%; vertical-align: top; padding: 8px; border-bottom: 0.5pt solid #e2e8f0;">{{ $safe($block['text_ar']) }}</td>
            </tr>
        @endif
    @endforeach
</table>

@if ($bilingual && $stacked)
    <div class="section-heading">{{ __('English') }}</div>
    @foreach ($letter->content as $block)
        <p class="{{ $block['is_heading'] ? 'heading' : '' }}" style="{{ $block['is_heading'] ? 'font-weight:bold;color:#0f172a;' : '' }}">{{ $safe($block['text_en']) }}</p>
    @endforeach
    <div class="section-heading ar">النص العربي</div>
    @foreach ($letter->content as $block)
        <p class="ar {{ $block['is_heading'] ? 'heading' : '' }}" style="{{ $block['is_heading'] ? 'font-weight:bold;color:#0f172a;' : '' }}">{{ $safe($block['text_ar']) }}</p>
    @endforeach
@elseif (! $bilingual)
    @foreach ($letter->content as $block)
        <p class="{{ $showAr ? 'ar' : '' }} {{ $block['is_heading'] ? 'heading' : '' }}" style="{{ $block['is_heading'] ? 'font-weight:bold;color:#0f172a;' : '' }}">{{ $showAr ? $safe($block['text_ar']) : $safe($block['text_en']) }}</p>
    @endforeach
@endif

<table class="sig-table" style="margin-top: 24px;">
    <tr>
        <td class="sig-header" colspan="1">{{ $letter->party_a_role }}{{ $bilingual ? ' / '.$letter->party_a_role : '' }}</td>
        <td class="sig-header">{{ $letter->party_b_role }}{{ $bilingual ? ' / '.$letter->party_b_role : '' }}</td>
    </tr>
    <tr>
        <td>
            <div>{{ $letter->partyAName() }}</div>
            <div class="sig-line">{{ __('Name and title') }}</div>
            <div class="sig-line">{{ __('Signature') }}</div>
            <div class="sig-line">{{ __('Date') }}</div>
        </td>
        <td>
            <div>{{ $letter->party_b_name }}</div>
            <div class="sig-line">{{ __('Name and title') }}</div>
            <div class="sig-line">{{ __('Signature') }}</div>
            <div class="sig-line">{{ __('Date') }}</div>
        </td>
    </tr>
</table>

<p class="muted" style="margin-top: 16px;">{{ __('This letter was generated from a starting template and should be reviewed against your own agreement before relying on it.') }}</p>

@if ($footerData)
    <img src="{{ $footerData }}" style="width: 100%; margin-top: 20px;" alt="">
@endif

</body>
</html>
